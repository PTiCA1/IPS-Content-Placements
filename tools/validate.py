#!/usr/bin/env python3
"""Validate distributable metadata and syntax without loading proprietary IPS code."""
from __future__ import annotations

import argparse
import json
import os
from pathlib import Path
import re
import shutil
import subprocess
import sys
import tarfile
import xml.etree.ElementTree as ET

ROOT = Path(__file__).resolve().parents[1]
APP = ROOT / "application"
KEY = "rqblockplacements"


def require(condition: bool, message: str) -> None:
    if not condition:
        raise ValueError(message)


def validate_acp_menu(menu: dict, words: dict[str, str]) -> None:
    """IPS hides ACP tab labels on compact screens, so a real icon is required."""
    for items in menu.values():
        for item in items.values():
            tab = item.get("tab", "")
            require(bool(words.get(f"menutab__{tab}")), f"ACP tab label missing: {tab}")
            icon = words.get(f"menutab__{tab}_icon", "")
            require(bool(re.fullmatch(r"[a-z0-9]+(?:-[a-z0-9]+)*", icon)), f"ACP tab icon missing or invalid: {tab}")


def signature(source: str, name: str) -> tuple[str, str, str]:
    match = re.search(r"public\s+(static\s+)?function\s+" + re.escape(name) + r"\s*\(([^)]*)\)\s*(?::\s*([\\\w?|]+))?", source)
    require(match is not None, f"Public method not found: {name}")
    return tuple(re.sub(r"\s+", "", item or "") for item in match.groups())


def validate(ips_source: Path | None = None, archive: Path | None = None) -> None:
    for path in APP.rglob("*"):
        require(not path.is_symlink(), f"Symlink not allowed: {path}")
        require(not any(part in {"ips-source", "ips_v5.0.20", ".git", "node_modules"} for part in path.relative_to(APP).parts), f"Unexpected bundled directory: {path}")
        require(path.name not in {"conf_global.php", "constants.php", ".env"}, f"Private configuration in application: {path}")
    data = {}
    for path in sorted((APP / "data").glob("*.json")):
        data[path.stem] = json.loads(path.read_text(encoding="utf-8"))
    required = {"application", "versions", "schema", "modules", "acpmenu", "acprestrictions", "settings", "extensions", "customtemplates", "installLang"}
    require(required <= data.keys(), f"Missing data files: {sorted(required - data.keys())}")
    require(data["application"].get("app_directory") == KEY, "Application key mismatch")
    require(data["application"].get("app_protected") == 0, "Application must be uninstallable")
    require(data["versions"] and all(str(k).isdigit() and int(k) > 0 and isinstance(v, str) for k, v in data["versions"].items()), "Invalid versions.json")
    ids = list(map(int, data["versions"]))
    require(ids == sorted(set(ids)), "Version build IDs must be unique and increasing")
    require(data["modules"]["admin"]["placements"]["default_controller"] == "rules", "Admin module default must be rules")
    require(data["extensions"]["core"]["Loader"]["Placements"] == f"IPS\\{KEY}\\extensions\\core\\Loader\\Placements", "Loader metadata mismatch")
    require(any(setting.get("key") == "rqbp_enabled" and str(setting.get("default")) == "1" for setting in data["settings"]), "Global enable setting missing")
    require(all(str(table).startswith("rqbp_") for table in data["schema"]), "Application schema must contain only its own rqbp_ tables")
    require(set(data["customtemplates"]) == {"rqbp_prepare", "rqbp_payload"}, "Only the two static lifecycle templates may ship")
    for name in data["customtemplates"]:
        require(not re.search(r"(?:rule|placement)[_-]?\d+", name, re.I), "Runtime rule markers must not enter the release")
    en = ET.parse(APP / "data/lang.xml").getroot()
    cs = ET.parse(APP / "resources/lang-cs.xml").getroot()
    for language in (en, cs):
        application = language.find("app")
        require(application is not None and application.get("key") == KEY, "Language app key mismatch")
        require(application.get("version") == str(ids[-1]), "Language build version mismatch")
        words = application.findall("word")
        keys = [word.get("key") for word in words]
        require(len(keys) == len(set(keys)) and bool(keys), "Language keys missing or duplicated")
        validate_acp_menu(data["acpmenu"], {word.get("key"): word.text or "" for word in words})
    require({w.get("key") for w in en.find("app")} == {w.get("key") for w in cs.find("app")}, "English/Czech translation keys differ")
    for name in ("Application.php", "sources/Policy/Policy.php", "sources/Translation/Translation.php", "modules/admin/placements/rules.php", "modules/admin/placements/settings.php", "extensions/core/Loader/Placements.php"):
        require((APP / name).is_file(), f"Missing runtime file {name}")
    for name in ("resources/admin.js", "resources/placements.js"):
        require((APP / name).is_file(), f"Missing original browser asset {name}")
    for path in APP.rglob("*.xml"):
        ET.parse(path)
    php = os.environ.get("PHP_BINARY") or shutil.which("php")
    require(bool(php), "PHP is required for syntax validation")
    php_files = sorted(APP.rglob("*.php"))
    for path in php_files:
        result = subprocess.run([php, "-l", str(path)], capture_output=True, text=True)
        require(result.returncode == 0, result.stdout + result.stderr)
        require(not re.search(r"@copyright[^\n]*Invision Power Services", path.read_text(encoding="utf-8")), f"IPS source copyright found in original app: {path}")
    if ips_source:
        contracts = {
            "applications/cms/sources/Blocks/Block.php": r"public static function display\(\s*int\|string\|Block \$block",
            "system/Theme/Theme.php": r"public function getHookPoints\(",
            "system/Application/Application.php": r"public function installCustomTemplates\(",
        }
        for name, pattern in contracts.items():
            require(re.search(pattern, (ips_source / name).read_text(encoding="utf-8")) is not None, f"IPS contract changed: {name}")
        parent = (ips_source / "system/Application/Application.php").read_text(encoding="utf-8")
        child = (APP / "Application.php").read_text(encoding="utf-8")
        for method in ("installCustomTemplates", "buildCustomTemplates", "installLanguages"):
            require(signature(parent, method) == signature(child, method), f"App override signature differs from native IPS: {method}")
        parent_loader = (ips_source / "system/Extensions/LoaderAbstract.php").read_text(encoding="utf-8")
        child_loader = (APP / "extensions/core/Loader/Placements.php").read_text(encoding="utf-8")
        require(signature(parent_loader, "onFinish") == signature(child_loader, "onFinish"), "Loader onFinish signature mismatch")
        print("Optional IPS source contracts checked locally; no source files copied.")
    if archive:
        from build import distribution_files
        expected = {name: content for name, content, _ in distribution_files()}
        with tarfile.open(archive) as bundle:
            names = bundle.getnames()
            require("data/application.json" in names and "Application.php" in names, "Archive must have app-relative root")
            require(len(names) == len(set(names)) and set(names) == set(expected), "Archive contents differ from original application allowlist")
            for member in bundle.getmembers():
                path = Path(member.name)
                require(member.isfile() and not path.is_absolute() and ".." not in path.parts, f"Unsafe archive entry: {member.name}")
                require(not set(path.parts) & {"dev", ".git", "node_modules", "ips-source", "ips_v5.0.20"}, f"Forbidden archive entry: {member.name}")
                require(member.uid == 0 and member.gid == 0, "Archive owner metadata must be normalized")
                require(bundle.extractfile(member).read() == expected[member.name], f"Archive is stale or differs from source: {member.name}")
    print(f"Validated {len(data)} metadata files, languages and {len(php_files)} PHP files.")


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--ips-source", type=Path)
    parser.add_argument("--archive", type=Path)
    args = parser.parse_args()
    try:
        validate(args.ips_source, args.archive)
        return 0
    except (ValueError, KeyError, TypeError, OSError, ET.ParseError) as error:
        print(f"Validation failed: {error}", file=sys.stderr)
        return 1


if __name__ == "__main__":
    raise SystemExit(main())
