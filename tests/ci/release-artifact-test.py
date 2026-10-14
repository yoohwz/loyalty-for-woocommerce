#!/usr/bin/env python3
"""Offline exact-artifact and metadata regressions in an external Git fixture."""
import hashlib
import importlib.util
import json
from pathlib import Path
import shutil
import subprocess
import sys
import tempfile
import unittest
import zipfile

sys.dont_write_bytecode = True
ROOT = Path(__file__).resolve().parents[2]
VERSION = json.loads((ROOT / 'config/free-import-manifest.json').read_text())['free']['version']
spec = importlib.util.spec_from_file_location('package', ROOT / 'scripts/verify-distribution.py')
package = importlib.util.module_from_spec(spec); spec.loader.exec_module(package)


class Metadata(unittest.TestCase):
    def test_version_boundaries(self):
        with tempfile.TemporaryDirectory() as work:
            root = Path(work) / 'loyalty-for-woocommerce'; root.mkdir()
            for path in ('loyalty-for-woocommerce.php', 'readme.txt', 'changelog.txt'):
                shutil.copyfile(ROOT / path, root / path)
            package.verify_version(work, VERSION)
            for path, old, new in (
                ('loyalty-for-woocommerce.php', f'Version: {VERSION}', 'Version: 9.9.9'),
                ('readme.txt', f'Stable tag: {VERSION}', 'Stable tag: trunk'),
                ('readme.txt', f'Stable tag: {VERSION}', f'Stable tag: {VERSION}\nStable tag: {VERSION}'),
                ('readme.txt', f'Stable tag: {VERSION}', ''),
                ('readme.txt', f'= {VERSION} (', '= 9.9.9 ('),
                ('changelog.txt', f'= {VERSION} (', '= 9.9.9 ('),
            ):
                original = (root / path).read_text()
                (root / path).write_text(original.replace(old, new, 1))
                with self.subTest(path=path, new=new), self.assertRaises(ValueError):
                    package.verify_version(work, VERSION)
                (root / path).write_text(original)


class Artifact(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.temp = tempfile.TemporaryDirectory(prefix='loyf-release-test-')
        cls.work = Path(cls.temp.name); cls.repo = cls.work / 'source'
        subprocess.run(['git', 'clone', '--quiet', '--no-hardlinks', str(ROOT), str(cls.repo)], check=True)
        for path in ('scripts/release-artifact.py', 'scripts/verify-distribution.py'):
            shutil.copyfile(ROOT / path, cls.repo / path)
        cls.git('config', 'user.email', 'fixture@example.invalid'); cls.git('config', 'user.name', 'Offline fixture')
        cls.git('add', 'scripts'); cls.git('commit', '--allow-empty', '-qm', 'Offline artifact fixture')
        cls.sha = cls.git('rev-parse', 'HEAD')
        cls.output = cls.work / 'artifact'; cls.output.mkdir()
        cls.invoke('build', '--output', str(cls.output))
        cls.receipt = json.loads((cls.output / 'receipt.json').read_text())
        cls.checksum = cls.receipt['archive_sha256']

    @classmethod
    def tearDownClass(cls): cls.temp.cleanup()

    @classmethod
    def git(cls, *args):
        return subprocess.check_output(['git', '-C', str(cls.repo), *args]).decode().strip()

    @classmethod
    def invoke(cls, command, *args, ok=True):
        result = subprocess.run([sys.executable, str(cls.repo / 'scripts/release-artifact.py'), command,
                                 '--sha', cls.sha, '--version', VERSION, '--purpose', 'VERIFICATION_ONLY', *args],
                                capture_output=True, text=True)
        if (result.returncode == 0) != ok:
            raise AssertionError(result.stdout + result.stderr)
        return result

    def copied(self):
        directory = self.work / self._testMethodName
        shutil.copytree(self.output, directory)
        return directory

    def verify(self, directory, *args, ok=True):
        return self.invoke('verify', '--receipt', str(directory / 'receipt.json'),
                           '--zip-sha256', self.checksum, *args, ok=ok)

    def test_retained_checksum_and_deterministic_build(self):
        archive = self.output / self.receipt['archive']; before = archive.read_bytes()
        self.verify(self.output)
        self.assertEqual(before, archive.read_bytes())
        other = self.work / 'second'; other.mkdir()
        self.invoke('build', '--output', str(other))
        self.assertEqual(before, (other / archive.name).read_bytes())
        self.assertEqual(self.receipt, json.loads((other / 'receipt.json').read_text()))
        manifest = json.loads((self.repo / 'config/free-import-manifest.json').read_text())
        self.assertEqual(len(manifest['imports']) + len(manifest['overlays']), len(self.receipt['inventory']))

    def test_corrupt_checksum(self):
        directory = self.copied(); archive = directory / self.receipt['archive']
        archive.write_bytes(archive.read_bytes() + b'drift')
        self.verify(directory, ok=False)

    def test_missing_archive(self):
        directory = self.copied(); (directory / self.receipt['archive']).unlink()
        self.verify(directory, ok=False)

    def test_missing_receipt(self):
        directory = self.copied(); (directory / 'receipt.json').unlink()
        self.verify(directory, ok=False)

    def test_receipt_identity_tamper(self):
        directory = self.copied()
        for key, value in [('version', '9.9.9'), ('source_sha', '0'*40), ('source_tree', '0'*40),
                           ('purpose', 'WORDPRESS_ORG'), ('archive_sha256', '0'*64), ('inventory', {})]:
            data = dict(self.receipt); data[key] = value
            (directory / 'receipt.json').write_text(json.dumps(data))
            with self.subTest(key=key): self.verify(directory, ok=False)

    def test_wrong_zip_root_and_packaged_drift(self):
        directory = self.copied(); archive = directory / self.receipt['archive']
        original = archive.read_bytes()
        for bad_path, content in [('wrong-root/readme.txt', b'bad'), ('loyalty-for-woocommerce/readme.txt', b'drift')]:
            with zipfile.ZipFile(archive, 'w') as bundle: bundle.writestr(bad_path, content)
            new_hash = hashlib.sha256(archive.read_bytes()).hexdigest()
            # Even a replacement checksum/receipt cannot replace the exact source inventory.
            data = dict(self.receipt); data['archive_sha256'] = new_hash
            (directory / 'receipt.json').write_text(json.dumps(data))
            self.invoke('verify', '--receipt', str(directory / 'receipt.json'), '--zip-sha256', new_hash, ok=False)
        archive.write_bytes(original)

    def test_external_and_no_overwrite(self):
        self.invoke('build', '--output', str(self.output), ok=False)
        self.invoke('build', '--output', str(self.repo), ok=False)
        self.invoke('verify', '--receipt', str(self.output / 'receipt.json'), ok=False)

    def test_dirty_source_refused(self):
        path = self.repo / 'readme.txt'; before = path.read_bytes()
        try:
            path.write_bytes(before + b'drift')
            self.verify(self.output, ok=False)
        finally: path.write_bytes(before)

    def test_merged_no_drift_and_ancestry(self):
        # Fixture-only refs/commits, never the real repository or WordPress.org.
        self.git('update-ref', 'refs/remotes/origin/main', self.sha)
        self.verify(self.output, '--merged-sha', self.sha)
        self.verify(self.output, '--merged-sha', '0'*40, ok=False)
        self.git('checkout', '--quiet', '-b', 'fixture-main')
        path = self.repo / 'readme.txt'; before = path.read_bytes()
        path.write_bytes(before + b'packaged drift')
        self.git('add', 'readme.txt'); self.git('commit', '-qm', 'Fixture drift')
        drift = self.git('rev-parse', 'HEAD')
        self.git('update-ref', 'refs/remotes/origin/main', drift)
        self.git('checkout', '--quiet', '--detach', self.sha)
        self.verify(self.output, '--merged-sha', drift, ok=False)
        self.git('update-ref', 'refs/remotes/origin/main', self.git('rev-parse', self.sha + '^'))
        self.verify(self.output, '--merged-sha', self.git('rev-parse', self.sha + '^'), ok=False)

    def test_offline_svn_layout(self):
        directory = self.work / 'offline-svn'; directory.mkdir()
        assets = directory / 'assets'; assets.mkdir(); (assets / 'keep.txt').write_text('unchanged')
        trunk = directory / 'trunk'; trunk.mkdir()
        with zipfile.ZipFile(self.output / self.receipt['archive']) as bundle:
            for path, expected in self.receipt['inventory'].items():
                target = trunk / path; target.parent.mkdir(parents=True, exist_ok=True)
                target.write_bytes(bundle.read('loyalty-for-woocommerce/' + path))
        self.assertEqual(self.receipt['inventory'], package.files_under(trunk))
        self.assertTrue((trunk / 'loyalty-for-woocommerce.php').is_file())
        self.assertFalse((trunk / 'loyalty-for-woocommerce').exists())
        self.assertEqual('unchanged', (assets / 'keep.txt').read_text())
        tag = directory / f'tags/{VERSION}'; tag.parent.mkdir(); shutil.copytree(trunk, tag)
        self.assertEqual(self.receipt['inventory'], package.files_under(tag))


if __name__ == '__main__': unittest.main()
