#!/usr/bin/env python3
"""Build the original application only; no IPS installation is needed."""
from __future__ import annotations

import argparse
import hashlib
import io
import json
import os
from pathlib import Path
import shutil
import subprocess
import sys
import tarfile
import xml.etree.ElementTree as ET

ROOT = Path(__file__).resolve().parents[1]
APP = ROOT / "application"
KEY = "rqblockplacements"
TRANSLATIONS = {"cs": "Čeština", "sk": "Slovenčina", "pl": "Polski", "de": "Deutsch"}


def load_package_files(path: Path) -> frozenset[str]:
    entries = json.loads(path.read_text(encoding="utf-8"))
    if not isinstance(entries, list) or not entries or not all(isinstance(item, str) for item in entries):
        raise ValueError("Package manifest must be a nonempty list of relative file paths")
    if len(entries) != len(set(entries)):
        raise ValueError("Package manifest contains duplicate paths")
    for name in entries:
        relative = Path(name)
        if (relative.is_absolute() or '..' in relative.parts or relative.as_posix() != name or not relative.parts
                or '\\' in name or ':' in name or any(ord(char) < 32 or ord(char) == 127 for char in name)):
            raise ValueError(f"Invalid package path: {name}")
        if relative.parts[:2] == ('resources', 'lang'):
            raise ValueError("Authoring language arrays must not be packaged")
    return frozenset(entries)


PACKAGE_FILES = load_package_files(ROOT / "tools/package-files.json")


def php_words(path: Path) -> dict[str, str]:
    php = os.environ.get("PHP_BINARY") or shutil.which("php")
    if not php:
        raise ValueError("PHP 8.1+ is required to read the original language arrays.")
    code = "$words = require $argv[1]; echo json_encode($words, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);"
    result = subprocess.run([php, "-r", code, str(path)], check=True, capture_output=True, text=True)
    words = json.loads(result.stdout)
    if not isinstance(words, dict) or not words or not all(isinstance(k, str) and isinstance(v, str) for k, v in words.items()):
        raise ValueError(f"{path}: expected a nonempty string-to-string array")
    return dict(sorted(words.items()))


def xml_bytes(words: dict[str, str], build: int, version: str, translated: bool = False,
              language_name: str = "Čeština") -> bytes:
    attrs = ({"name": language_name, "rtl": "0", "author_name": "Pages Block Placements contributors",
              "author_url": "", "version": version, "long_version": str(build), "update_check": ""}
             if translated else {})
    language = ET.Element("language", attrs)
    app = ET.SubElement(language, "app", {"key": KEY, "version": str(build)})
    for key, value in words.items():
        ET.SubElement(app, "word", {"key": key, "js": "0"}).text = value
    ET.indent(language, space=" ")
    return ET.tostring(language, encoding="utf-8", xml_declaration=True) + b"\n"


def generate_languages(build: int, version: str) -> None:
    english = php_words(APP / "resources/lang/en.php")
    translated = {}
    for code in TRANSLATIONS:
        words = php_words(APP / f"resources/lang/{code}.php")
        if english.keys() != words.keys():
            missing = sorted(english.keys() - words.keys())
            extra = sorted(words.keys() - english.keys())
            raise ValueError(f"Translation key mismatch ({code}): missing={missing}, extra={extra}")
        translated[code] = words
    (APP / "data/lang.xml").write_bytes(xml_bytes(english, build, version))
    # The native installer accepts this bootstrap map before importing lang.xml.
    # Keep only identity strings here; normal UI strings remain translatable defaults.
    bootstrap = {k: v for k, v in english.items() if k in {f"__app_{KEY}", f"__app_{KEY}_description"}}
    (APP / "data/installLang.json").write_text(json.dumps(bootstrap, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    for code, words in translated.items():
        (APP / f"resources/lang-{code}.xml").write_bytes(
            xml_bytes(words, build, version, translated=True, language_name=TRANSLATIONS[code]))


def distribution_files() -> list[tuple[str, bytes, int]]:
    excluded = {".DS_Store", ".git", "dev", "node_modules", "__pycache__"}
    files = []
    if APP.is_symlink():
        raise ValueError("Symlinks are forbidden in the application root")
    for path in sorted(APP.rglob("*")):
        relative = path.relative_to(APP)
        if path.is_symlink():
            raise ValueError(f"Symlinks are forbidden in the application: {relative}")
        if not path.is_file() or any(part in excluded for part in relative.parts):
            continue
        if relative.parts[:2] == ("resources", "lang"):
            continue  # Authoring arrays are in Git; the installer receives generated XML.
        if relative.as_posix() not in PACKAGE_FILES:
            raise ValueError(f"File is not in the reviewed package manifest: {relative}")
        if relative.parts[0] == "setup" and "upg_working" in relative.parts:
            raise ValueError("Unversioned setup/upg_working cannot be released")
        files.append((relative.as_posix(), path.read_bytes(), 0o644))
    missing = PACKAGE_FILES - {name for name, _, _ in files}
    if missing:
        raise ValueError(f"Package files are missing: {', '.join(sorted(missing))}")
    if (ROOT / "LICENSE").is_symlink():
        raise ValueError("Symlinks are forbidden for the package license")
    files.append(("LICENSE", (ROOT / "LICENSE").read_bytes(), 0o644))
    return sorted(files)


def archive_bytes(files: list[tuple[str, bytes, int]], epoch: int) -> bytes:
    stream = io.BytesIO()
    with tarfile.open(fileobj=stream, mode="w", format=tarfile.USTAR_FORMAT) as archive:
        for name, content, mode in files:
            info = tarfile.TarInfo(name)
            info.size, info.mode, info.mtime = len(content), mode, epoch
            info.uid = info.gid = 0
            info.uname = info.gname = ""
            archive.addfile(info, io.BytesIO(content))
    return stream.getvalue()


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--output", type=Path, default=ROOT / "dist")
    parser.add_argument("--check-reproducible", action="store_true")
    args = parser.parse_args()
    try:
        versions = json.loads((APP / "data/versions.json").read_text(encoding="utf-8"))
        build, version = int(next(reversed(versions))), next(reversed(versions.values()))
        generate_languages(build, version)
        subprocess.run([sys.executable, str(ROOT / "tools/validate.py")], check=True)
        files = distribution_files()
        epoch = int(os.environ.get("SOURCE_DATE_EPOCH", "0"))
        if epoch < 0:
            raise ValueError("SOURCE_DATE_EPOCH must be nonnegative")
        content = archive_bytes(files, epoch)
        if args.check_reproducible and content != archive_bytes(distribution_files(), epoch):
            raise ValueError("Repeated build was not byte-identical")
        args.output.mkdir(parents=True, exist_ok=True)
        name = f"{KEY}-{version}.tar"
        (args.output / name).write_bytes(content)
        checksums = f"{hashlib.sha256(content).hexdigest()}  {name}\n"
        for code in TRANSLATIONS:
            translation_name = f"{KEY}-{version}-{code}.xml"
            translation = (APP / f"resources/lang-{code}.xml").read_bytes()
            (args.output / translation_name).write_bytes(translation)
            checksums += f"{hashlib.sha256(translation).hexdigest()}  {translation_name}\n"
        (args.output / "SHA256SUMS").write_text(checksums, encoding="ascii")
        print(f"Built {name} ({len(files)} original files, {len(content)} bytes)")
        print(checksums, end="")
        return 0
    except (ValueError, OSError, subprocess.CalledProcessError, ET.ParseError) as error:
        print(f"Build failed: {error}", file=sys.stderr)
        return 1


if __name__ == "__main__":
    raise SystemExit(main())
