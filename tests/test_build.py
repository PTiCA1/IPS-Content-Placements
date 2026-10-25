"""Packaging checks that do not need proprietary application sources."""
import importlib.util
import io
import json
from pathlib import Path
import tarfile
import tempfile
import unittest
import xml.etree.ElementTree as ET

SPEC = importlib.util.spec_from_file_location("rqbp_build", Path(__file__).resolve().parents[1] / "tools/build.py")
BUILD = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(BUILD)
VALIDATOR_SPEC = importlib.util.spec_from_file_location("rqbp_validate", Path(__file__).resolve().parents[1] / "tools/validate.py")
VALIDATOR = importlib.util.module_from_spec(VALIDATOR_SPEC)
VALIDATOR_SPEC.loader.exec_module(VALIDATOR)


class DistributionTests(unittest.TestCase):
    def test_compact_acp_menu_requires_an_icon(self):
        menu = {"placements": {"rules": {"tab": "rqblockplacements"}}}
        words = {"menutab__rqblockplacements": "Block placements"}
        with self.assertRaisesRegex(ValueError, "ACP tab icon missing"):
            VALIDATOR.validate_acp_menu(menu, words)
        words["menutab__rqblockplacements_icon"] = "table-cells-large"
        VALIDATOR.validate_acp_menu(menu, words)
        words["menutab__rqblockplacements_icon"] = '" invalid'
        with self.assertRaisesRegex(ValueError, "ACP tab icon missing or invalid"):
            VALIDATOR.validate_acp_menu(menu, words)

    def test_archive_is_reproducible_and_has_installable_root(self):
        files = [("Application.php", b"<?php // original\n", 0o644), ("data/application.json", b"{}\n", 0o644)]
        first = BUILD.archive_bytes(files, 0)
        self.assertEqual(first, BUILD.archive_bytes(files, 0))
        with tarfile.open(fileobj=io.BytesIO(first)) as archive:
            self.assertEqual(archive.getnames(), ["Application.php", "data/application.json"])
            self.assertTrue(all((entry.uid, entry.gid, entry.mtime, entry.uname) == (0, 0, 0, "") for entry in archive))

    def test_authoring_languages_are_excluded_but_all_imports_are_included(self):
        original_root, original_app, original_files = BUILD.ROOT, BUILD.APP, BUILD.PACKAGE_FILES
        try:
            with tempfile.TemporaryDirectory() as temporary:
                BUILD.ROOT = Path(temporary)
                BUILD.APP = BUILD.ROOT / "application"
                BUILD.PACKAGE_FILES = frozenset(f"resources/lang-{code}.xml" for code in VALIDATOR.TRANSLATIONS)
                (BUILD.APP / "resources/lang").mkdir(parents=True)
                for code in ("en", *VALIDATOR.TRANSLATIONS):
                    (BUILD.APP / f"resources/lang/{code}.php").write_text("<?php return [];", encoding="utf-8")
                for code in VALIDATOR.TRANSLATIONS:
                    (BUILD.APP / f"resources/lang-{code}.xml").write_text("<language/>", encoding="utf-8")
                (BUILD.ROOT / "LICENSE").write_text("MIT", encoding="utf-8")
                files = BUILD.distribution_files()
                with tarfile.open(fileobj=io.BytesIO(BUILD.archive_bytes(files, 0))) as archive:
                    self.assertEqual(set(archive.getnames()), set(BUILD.PACKAGE_FILES) | {"LICENSE"})
                    for code in ("en", *VALIDATOR.TRANSLATIONS):
                        self.assertNotIn(f"resources/lang/{code}.php", archive.getnames())
        finally:
            BUILD.ROOT, BUILD.APP, BUILD.PACKAGE_FILES = original_root, original_app, original_files

    def test_all_translation_sources_preserve_keys_examples_and_parameters(self):
        self.assertEqual(BUILD.TRANSLATIONS, VALIDATOR.TRANSLATIONS)
        english = BUILD.php_words(BUILD.APP / "resources/lang/en.php")
        for code, language_name in VALIDATOR.TRANSLATIONS.items():
            with self.subTest(language=code):
                translated = BUILD.php_words(BUILD.APP / f"resources/lang/{code}.php")
                VALIDATOR.validate_translation(english, translated, language_name)
                root = ET.fromstring(BUILD.xml_bytes(translated, 1000008, "1.0.0-beta.8", translated=True, language_name=language_name))
                self.assertEqual(VALIDATOR.validate_language(root, 1000008, "1.0.0-beta.8", language_name), translated)

    def test_translation_checks_reject_missing_keys_changed_examples_and_icons(self):
        english = {"message": 'Rule %s: <code>{block="click_brand"}</code>', "menutab__rqblockplacements_icon": "table-cells-large"}
        for changes, error in [
            ({"message": 'Pravidlo: <code>{block="click_brand"}</code>'}, "placeholder mismatch"),
            ({"message": 'Pravidlo %s: <code>{block="different"}</code>'}, "code example mismatch"),
            ({"menutab__rqblockplacements_icon": "table"}, "ACP icon differs"),
        ]:
            with self.subTest(error=error):
                with self.assertRaisesRegex(ValueError, error):
                    VALIDATOR.validate_translation(english, english | changes, "Test")
        with self.assertRaisesRegex(ValueError, "translation keys differ"):
            VALIDATOR.validate_translation(english, {"message": english["message"]}, "Test")

    def test_translated_xml_requires_its_own_name_and_current_versions(self):
        for code, language_name in VALIDATOR.TRANSLATIONS.items():
            xml = BUILD.xml_bytes({"label": "Translated label"}, 1000008, "1.0.0-beta.8", translated=True, language_name=language_name)
            for attribute, wrong in [("name", "Wrong language"), ("version", "1.0.0-beta.7"), ("long_version", "1000007")]:
                with self.subTest(language=code, attribute=attribute):
                    root = ET.fromstring(xml)
                    root.set(attribute, wrong)
                    with self.assertRaisesRegex(ValueError, "language (name|version metadata) mismatch"):
                        VALIDATOR.validate_language(root, 1000008, "1.0.0-beta.8", language_name)
            root = ET.fromstring(xml)
            root.find("app").set("version", "1000007")
            with self.assertRaisesRegex(ValueError, "language build version mismatch"):
                VALIDATOR.validate_language(root, 1000008, "1.0.0-beta.8", language_name)

    def test_unreviewed_nested_file_cannot_enter_package(self):
        original_root, original_app, original_files = BUILD.ROOT, BUILD.APP, BUILD.PACKAGE_FILES
        try:
            with tempfile.TemporaryDirectory() as temporary:
                BUILD.ROOT = Path(temporary)
                BUILD.APP = BUILD.ROOT / "application"
                BUILD.PACKAGE_FILES = frozenset({"Application.php"})
                (BUILD.APP / "resources").mkdir(parents=True)
                (BUILD.APP / "Application.php").write_text("<?php // original", encoding="utf-8")
                (BUILD.APP / "resources/private-backup.json").write_text("{}", encoding="utf-8")
                (BUILD.ROOT / "LICENSE").write_text("MIT", encoding="utf-8")
                with self.assertRaisesRegex(ValueError, "not in the reviewed package manifest"):
                    BUILD.distribution_files()
        finally:
            BUILD.ROOT, BUILD.APP, BUILD.PACKAGE_FILES = original_root, original_app, original_files

    def test_manifest_rejects_unsafe_or_duplicate_paths(self):
        with tempfile.TemporaryDirectory() as temporary:
            path = Path(temporary) / "files.json"
            for entries in [[], [""], ["../outside.php"], ["/tmp/outside.php"], ["a\\b.php"], ["C:/file.php"], ["a/./b.php"], ["a\nb.php"], ["resources/lang/cs.php"], ["a.php", "a.php"]]:
                with self.subTest(entries=entries):
                    path.write_text(json.dumps(entries), encoding="utf-8")
                    with self.assertRaises(ValueError):
                        BUILD.load_package_files(path)
            path.write_text('["Application.php"]', encoding="utf-8")
            self.assertEqual(BUILD.load_package_files(path), frozenset({"Application.php"}))

    def test_missing_manifest_file_stops_build(self):
        original_root, original_app, original_files = BUILD.ROOT, BUILD.APP, BUILD.PACKAGE_FILES
        try:
            with tempfile.TemporaryDirectory() as temporary:
                BUILD.ROOT = Path(temporary)
                BUILD.APP = BUILD.ROOT / "application"
                BUILD.APP.mkdir()
                BUILD.PACKAGE_FILES = frozenset({"Application.php"})
                with self.assertRaisesRegex(ValueError, "Package files are missing"):
                    BUILD.distribution_files()
        finally:
            BUILD.ROOT, BUILD.APP, BUILD.PACKAGE_FILES = original_root, original_app, original_files

    def test_symlink_cannot_include_files_from_parent_workspace(self):
        original_root, original_app = BUILD.ROOT, BUILD.APP
        try:
            with tempfile.TemporaryDirectory() as temporary:
                BUILD.ROOT = Path(temporary)
                BUILD.APP = BUILD.ROOT / "application"
                BUILD.APP.mkdir()
                secret = BUILD.ROOT / "private.php"
                secret.write_text("private", encoding="utf-8")
                (BUILD.APP / "Application.php").symlink_to(secret)
                with self.assertRaisesRegex(ValueError, "Symlinks"):
                    BUILD.distribution_files()
        finally:
            BUILD.ROOT, BUILD.APP = original_root, original_app


if __name__ == "__main__":
    unittest.main()
