#!/usr/bin/env python3
"""Real Git/file fixtures for exact provenance, drift, domains and forbidden boundaries."""
import copy
import importlib.util
import json
import os
from pathlib import Path
import subprocess
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[2]
spec = importlib.util.spec_from_file_location('free_import', ROOT / 'scripts/free-import.py')
contract = importlib.util.module_from_spec(spec)
spec.loader.exec_module(contract)


def git(repo, *args):
    return contract.git(repo, *args).decode().strip()


def initialize(repo, origin):
    repo.mkdir()
    git(repo, 'init', '-q'); git(repo, 'config', 'user.email', 'test@example.invalid')
    git(repo, 'config', 'user.name', 'Import contract test'); git(repo, 'remote', 'add', 'origin', origin)


def commit(repo):
    git(repo, 'add', '-A'); git(repo, 'commit', '-qm', 'Fixture', '--allow-empty')
    return git(repo, 'rev-parse', 'HEAD')


class ImportContractTest(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory(prefix='loyf-import-')
        self.work = Path(self.temp.name)
        self.upstream = self.work / 'upstream'; self.free = self.work / 'free'
        initialize(self.upstream, 'https://github.com/yoohwz/wc-loyalty.git')
        initialize(self.free, 'https://github.com/yoohwz/loyalty-for-woocommerce.git')
        self.source = 'inc/cores/helper/shared.php'
        file = self.upstream / self.source; file.parent.mkdir(parents=True)
        self.data = b"<?php\nclass Shared_Core { public static function label() { $owner = 'wc-loyalty'; return __('wc-loyalty', 'wc-loyalty'); } }\n"
        file.write_bytes(self.data); self.sha = commit(self.upstream)
        self.manifest = copy.deepcopy(contract.load_manifest())
        self.manifest['phase'] = 'contract-only'
        self.manifest['upstream']['sha'] = self.sha
        self.manifest['upstream']['tree'] = git(self.upstream, 'rev-parse', self.sha + '^{tree}')
        self.manifest['upstream_inventory'] = {self.source: dict(contract.git_inventory(self.upstream, self.sha)[self.source], decision='import')}
        output = self.data.replace(b"__('wc-loyalty', 'wc-loyalty')", b"__('wc-loyalty', 'loyalty-for-woocommerce')")
        self.manifest['imports'] = [{'source': self.source, 'target': self.source, 'sha256': contract.digest(self.data), 'output_sha256': contract.digest(output), 'domain_replacements': 1, 'transform': 'wp-text-domain-v1'}]
        self.manifest['blocked_mixed_modules'] = []
        for row in self.manifest['overlays']:
            target = self.free / row['target']; target.parent.mkdir(parents=True, exist_ok=True)
            target.write_bytes((ROOT / row['target']).read_bytes()); target.chmod(int(row['mode'][-3:], 8))
        self.head = commit(self.free)
        self.manifest['free']['baseline_sha'] = self.head
        self.output = self.work / 'output'; self.output.mkdir()

    def tearDown(self): self.temp.cleanup()

    def load_fixture(self, manifest):
        path = self.work / 'manifest.json'; path.write_text(json.dumps(manifest))
        return contract.load_manifest(path)

    def test_current_manifest_and_source_identity(self):
        manifest = contract.load_manifest()
        self.assertEqual(14, len(manifest['imports']))
        self.assertEqual(len(manifest['overlays']) + len(manifest['imports']), contract.verify_tree(ROOT, manifest, source=True))
        self.assertEqual({'import', 'reference-only', 'forbidden', 'excluded'}, {row['decision'] for row in manifest['upstream_inventory'].values()})

    def test_literal_translation_domain_only_and_determinism(self):
        output, count = contract.transform(self.data)
        self.assertEqual(1, count)
        self.assertIn(b"$owner = 'wc-loyalty'", output)
        self.assertIn(b"__('wc-loyalty', 'loyalty-for-woocommerce')", output)
        self.assertEqual((output, count), contract.transform(self.data))
        source = b'''<?php
// __('comment', 'wc-loyalty')
$x = "__('string', 'wc-loyalty')";
__ (foo(1, [2, 3]), /* domain */ "wc-loyalty");
_x('text', 'context', 'wc-loyalty');
_n('one', 'many', $n, 'wc-loyalty');
_nx('one', 'many', $n, 'context', 'wc-loyalty');
_n_noop('one', 'many', 'wc-loyalty');
_nx_noop('one', 'many', 'context', 'wc-loyalty');
esc_html__('text', 'wc-loyalty');
esc_attr_x('text', 'context', 'wc-loyalty');
$object->__('method', 'wc-loyalty');
Thing::__('static', 'wc-loyalty');
__ ('wc-loyalty', 'other-domain');
'''
        transformed, count = contract.transform(source)
        self.assertEqual(8, count)
        for preserved in [b"__('comment', 'wc-loyalty')", b"__('string', 'wc-loyalty')", b"->__('method', 'wc-loyalty')", b"::__('static', 'wc-loyalty')", b"__ ('wc-loyalty', 'other-domain')"]: self.assertIn(preserved, transformed)

    def test_interpolation_and_nested_calls_preserve_non_domain_bytes(self):
        cases = [
            (b'<?php __("hello {$name}", "wc-loyalty");', b'<?php __("hello {$name}", "loyalty-for-woocommerce");', 1),
            (b'<?php __("hello ${name}", "wc-loyalty");', b'<?php __("hello ${name}", "loyalty-for-woocommerce");', 1),
            (b'<?php __("hello {$names[0]}", "wc-loyalty");', b'<?php __("hello {$names[0]}", "loyalty-for-woocommerce");', 1),
            (b"<?php __(foo(__(\"hello {$name}\", 'wc-loyalty'), [1,2]), 'wc-loyalty');", b"<?php __(foo(__(\"hello {$name}\", 'loyalty-for-woocommerce'), [1,2]), 'loyalty-for-woocommerce');", 2),
        ]
        for source, expected, count in cases:
            with self.subTest(source=source): self.assertEqual((expected, count), contract.transform(source))

    def test_nullsafe_method_is_not_a_translation_call(self):
        version = subprocess.check_output(['php', '-r', 'echo PHP_VERSION_ID;']).decode()
        if int(version) < 80000: self.skipTest('Nullsafe syntax is unavailable under minimum PHP7.4; verified separately under PHP8.')
        source = b'<?php $o?->__("message", "wc-loyalty");'
        self.assertEqual((source, 0), contract.transform(source))

    def test_qualified_calls_are_preserved_across_tokenizer_versions(self):
        for source in [b"<?php \\__('x', 'wc-loyalty');", b"<?php Example\\__('x', 'wc-loyalty');"]:
            self.assertEqual((source, 0), contract.transform(source))

    def test_namespaced_resolution_fails_closed_before_transform(self):
        for source in [b'<?php namespace Example; function __($a,$b) { return $b; } __("message", "wc-loyalty");',
                       b'<?php namespace Example { __("message", "wc-loyalty"); }']:
            with self.assertRaises(subprocess.CalledProcessError) as error: contract.transform(source)
            self.assertIn(b'Namespaced source requires', error.exception.stderr)

    def test_manifest_rejects_collision_forbidden_path_and_duplicate_keys(self):
        for mutate in [lambda m: m['imports'][0].update(target=m['overlays'][0]['target']),
                       lambda m: m['imports'][0].update(target='../outside.php'),
                       lambda m: m['upstream_inventory'][self.source].update(decision='forbidden'),
                       lambda m: m['free'].update(text_domain='wc-loyalty'),
                       lambda m: m['imports'][0].update(transform='replace-everything')]:
            manifest = copy.deepcopy(self.manifest); mutate(manifest)
            with self.assertRaises(ValueError): self.load_fixture(manifest)
        path = self.work / 'duplicate.json'; path.write_text('{"schema_version":1,"schema_version":1}')
        with self.assertRaisesRegex(ValueError, 'Duplicate'): contract.load_manifest(path)

    def test_file_directory_and_portable_target_collisions_deny_before_writes(self):
        for target in ['inc/cores/database.php/nested.php', 'inc/cores/Database.php',
                       'inc/CORES/Database.php/nested.php', 'inc/cores']:
            with self.subTest(target=target):
                manifest = copy.deepcopy(self.manifest)
                manifest['imports'][0]['target'] = target
                with self.assertRaises(ValueError): self.load_fixture(manifest)
                # Exercise staging's own admission too: callers cannot bypass the
                # pre-write invariant by passing a modified in-memory manifest.
                with self.assertRaisesRegex(ValueError, 'collision'):
                    contract.stage(self.upstream, self.sha, self.free, self.head, self.output, manifest)
                self.assertEqual([], list(self.output.iterdir()))
                self.assertEqual('', git(self.free, 'status', '--porcelain'))

    def test_exact_sha_origin_tree_and_transform_proof(self):
        self.assertEqual({self.source}, set(contract.transformed_imports(self.upstream, self.sha, self.manifest)))
        for sha in ['main', self.sha[:12], '0' * 40]:
            with self.assertRaises(ValueError): contract.transformed_imports(self.upstream, sha, self.manifest)
        for mutate in [lambda m: m['upstream'].update(tree='0' * 40),
                       lambda m: m['imports'][0].update(output_sha256='0' * 64),
                       lambda m: m['imports'][0].update(domain_replacements=2),
                       lambda m: m['imports'][0].update(sha256='0' * 64)]:
            manifest = copy.deepcopy(self.manifest); mutate(manifest)
            with self.assertRaises(ValueError): contract.transformed_imports(self.upstream, self.sha, manifest)
        git(self.upstream, 'remote', 'set-url', 'origin', 'https://github.com/other/wc-loyalty.git')
        with self.assertRaisesRegex(ValueError, 'binding'): contract.transformed_imports(self.upstream, self.sha, self.manifest)

    def test_refresh_reports_added_removed_content_and_mode_before_import(self):
        self.assertFalse(contract.drift(self.upstream, self.sha, self.manifest)['readmission_required'])
        file = self.upstream / self.source; file.write_bytes(self.data + b'// changed\n'); file.chmod(0o755)
        forbidden = self.upstream / 'inc/cores/runtime.php'; forbidden.write_text('<?php class YOWCL_License_Runtime {}\n')
        new = commit(self.upstream)
        report = contract.drift(self.upstream, new, self.manifest)
        self.assertTrue(report['readmission_required'])
        self.assertEqual({'added', 'modified'}, {row['change'] for row in report['changes']})
        self.assertEqual('unclassified', report['changes'][0]['decision'] if report['changes'][0]['path'] == 'inc/cores/runtime.php' else report['changes'][1]['decision'])
        with self.assertRaisesRegex(ValueError, 'SHA drift'): contract.transformed_imports(self.upstream, new, self.manifest)
        file.unlink(); report = contract.drift(self.upstream, commit(self.upstream), self.manifest)
        self.assertIn('removed', {row['change'] for row in report['changes']})

    def test_new_sha_with_same_tree_and_symlink_are_not_auto_admitted(self):
        new = commit(self.upstream)
        report = contract.drift(self.upstream, new, self.manifest)
        self.assertTrue(report['readmission_required']); self.assertEqual([], report['changes'])
        file = self.upstream / self.source; file.unlink(); file.symlink_to('/etc/passwd')
        new = commit(self.upstream)
        self.assertEqual('120000', contract.drift(self.upstream, new, self.manifest)['changes'][0]['after']['mode'])
        with self.assertRaises(ValueError): contract.git_inventory(self.upstream, new)

    def test_stage_reproducible_external_only_and_does_not_mutate_checkout(self):
        before = git(self.free, 'status', '--porcelain')
        result = contract.stage(self.upstream, self.sha, self.free, self.head, self.output, self.manifest)
        self.assertEqual(len(self.manifest['overlays']) + 1, result['files'])
        root = self.output / 'loyalty-for-woocommerce'
        self.assertEqual(len(self.manifest['overlays']) + 1, contract.verify_tree(root, self.manifest, 'projection'))
        other = self.work / 'other'; other.mkdir()
        contract.stage(self.upstream, self.sha, self.free, self.head, other, self.manifest)
        first = {str(p.relative_to(root)): p.read_bytes() for p in root.rglob('*') if p.is_file()}
        second_root = other / 'loyalty-for-woocommerce'
        self.assertEqual(first, {str(p.relative_to(second_root)): p.read_bytes() for p in second_root.rglob('*') if p.is_file()})
        self.assertEqual(before, git(self.free, 'status', '--porcelain'))
        self.assertEqual(self.head, git(self.free, 'rev-parse', 'HEAD'))

    def test_free_provenance_binding_and_admitted_baseline(self):
        git(self.free, 'remote', 'set-url', 'origin', 'https://github.com/other/free.git')
        with self.assertRaisesRegex(ValueError, 'Free repository binding'): contract.stage(self.upstream, self.sha, self.free, self.head, self.output, self.manifest)
        self.assertEqual([], list(self.output.iterdir()))
        git(self.free, 'remote', 'set-url', 'origin', 'https://github.com/yoohwz/loyalty-for-woocommerce.git')
        manifest = copy.deepcopy(self.manifest); manifest['free']['baseline_sha'] = '0' * 40
        with self.assertRaises(subprocess.CalledProcessError): contract.stage(self.upstream, self.sha, self.free, self.head, self.output, manifest)
        self.assertEqual([], list(self.output.iterdir()))

    def test_fail_closed_before_staging_writes(self):
        inside = self.free / 'output'; inside.mkdir()
        with self.assertRaises(ValueError): contract.stage(self.upstream, self.sha, self.free, self.head, inside, self.manifest)
        self.assertEqual([], list(inside.iterdir())); inside.rmdir()
        manifest = copy.deepcopy(self.manifest); manifest['imports'][0]['output_sha256'] = '0' * 64
        with self.assertRaises(ValueError): contract.stage(self.upstream, self.sha, self.free, self.head, self.output, manifest)
        self.assertEqual([], list(self.output.iterdir()))
        (self.free / 'inc/forged.php').write_text('<?php class YOWCL_License_Runtime {}')
        with self.assertRaises(ValueError): contract.stage(self.upstream, self.sha, self.free, self.head, self.output, self.manifest)
        self.assertEqual([], list(self.output.iterdir()))

    def test_case_alias_of_protected_checkout_output_is_refused(self):
        output = self.work / 'FREE/.git/output'
        output.mkdir(parents=True)
        with self.assertRaisesRegex(ValueError, 'outside source'):
            contract.stage(self.upstream, self.sha, self.free, self.head, output, self.manifest)
        self.assertEqual([], list(output.iterdir()))

    def test_renamed_or_obfuscated_premium_and_source_drift_denied(self):
        file = self.free / 'inc/cores/database.php'; original = file.read_bytes()
        file.write_bytes(original + b'\nclass YOWCL_License_Runtime {}\n')
        with self.assertRaisesRegex(ValueError, 'Forbidden Premium'): contract.verify_tree(self.free, self.manifest, source=True)
        file.write_bytes(original + b'\nclass Renamed_Private_License {}\n')
        with self.assertRaisesRegex(ValueError, 'content drift'): contract.verify_tree(self.free, self.manifest, source=True)
        file.write_bytes(original); file.chmod(0o755)
        with self.assertRaisesRegex(ValueError, 'mode drift'): contract.verify_tree(self.free, self.manifest, source=True)
        file.chmod(0o644); file.unlink(); file.symlink_to(ROOT / 'inc/cores/database.php')
        with self.assertRaisesRegex(ValueError, 'Unsafe filesystem'): contract.verify_tree(self.free, self.manifest, source=True)

    def test_package_rejects_metadata_junk_and_source_allows_untracked_os_junk(self):
        file = self.free / 'inc/.DS_Store'; file.write_bytes(b'OS metadata')
        self.assertEqual(len(self.manifest['overlays']), contract.verify_tree(self.free, self.manifest, source=True))
        with self.assertRaises(ValueError): contract.verify_tree(self.free, self.manifest)


if __name__ == '__main__': unittest.main()
