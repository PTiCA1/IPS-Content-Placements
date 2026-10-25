"""Packaging checks that do not need proprietary application sources."""
import importlib.util
import io
import json
from pathlib import Path
import tarfile
import tempfile
import unittest

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

    def test_authoring_languages_are_excluded_but_import_is_included(self):
        original_root, original_app, original_files = BUILD.ROOT, BUILD.APP, BUILD.PACKAGE_FILES
        try:
            with tempfile.TemporaryDirectory() as temporary:
                BUILD.ROOT = Path(temporary)
                BUILD.APP = BUILD.ROOT / "application"
                BUILD.PACKAGE_FILES = frozenset({"resources/lang-cs.xml"})
                (BUILD.APP / "resources/lang").mkdir(parents=True)
                (BUILD.APP / "resources/lang/en.php").write_text("<?php return [];", encoding="utf-8")
                (BUILD.APP / "resources/lang-cs.xml").write_text("<language/>", encoding="utf-8")
                (BUILD.ROOT / "LICENSE").write_text("MIT", encoding="utf-8")
                names = [name for name, _, _ in BUILD.distribution_files()]
                self.assertNotIn("resources/lang/en.php", names)
                self.assertIn("resources/lang-cs.xml", names)
                self.assertIn("LICENSE", names)
        finally:
            BUILD.ROOT, BUILD.APP, BUILD.PACKAGE_FILES = original_root, original_app, original_files

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
