#!/usr/bin/env python3
"""Runner safety/orchestration doubles; not native WordPress certification."""
import os
from pathlib import Path
import subprocess
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[2]
RUNNER = ROOT / 'tests/runtime/run.sh'


class RuntimeTest(unittest.TestCase):
    def run_case(self, failure='', checkout='/tmp/loyf-disposable-source', dirty=False):
        with tempfile.TemporaryDirectory(prefix='loyf-harness-') as directory:
            tmp = Path(directory)
            if checkout == '/tmp/loyf-disposable-source':
                checkout = str(tmp / 'source')
                mu = Path(checkout) / 'tests/runtime/mu-isolation.php'
                mu.parent.mkdir(parents=True)
                mu.write_text('<?php // fixture\n')
                golden = Path(checkout) / 'tests/fixtures/free-1.2.2-expected.json'
                golden.parent.mkdir(parents=True)
                golden.write_text('{"snapshot":"same"}\n')
            bin_dir = tmp / 'bin'
            bin_dir.mkdir()
            log = tmp / 'calls'
            scripts = {
                'git': '''#!/bin/bash
case "$*" in
 *--show-toplevel*) echo "$FAKE_REPO" ;;
 *'rev-parse HEAD'*) echo 1111111111111111111111111111111111111111 ;;
 *'status --porcelain'*) [[ "$FAKE_DIRTY" != 1 ]] || echo modified ;;
 *archive*) tar -cf - --files-from /dev/null ;;
esac
exit 0
''',
                'python3': '#!/bin/bash\necho 3c1aac240df03101561b856cddb603b1501fa41b\n',
                'mysql': '''#!/bin/bash
echo "$*" >> "$CALLS"
if [[ "$*" == *CREATE* && "$FAKE_FAIL" == create ]]; then exit 1; fi
if [[ "$*" == *DROP* && "$FAKE_FAIL" == cleanup ]]; then exit 1; fi
''',
                'curl': '#!/bin/bash\nexit 0\n',
                'php': '''#!/bin/bash
echo "$*" >> "$CALLS"
if [[ "$*" == *seed.php* && "$FAKE_FAIL" == seed ]]; then exit 1; fi
if [[ "$*" == *characterization.php* ]]; then
 [[ "$FAKE_FAIL" != scenario ]] || exit 1
 echo '{"snapshot":"same"}' > "$LOYF_SNAPSHOT"
 echo '{"raw":"same"}' > "$LOYF_RAW_SNAPSHOT"
 if [[ "$FAKE_FAIL" == mismatch && "$LOYF_SNAPSHOT" == *candidate.json ]]; then echo different > "$LOYF_SNAPSHOT"; fi
fi
''',
            }
            for name, text in scripts.items():
                file = bin_dir / name
                file.write_text(text)
                file.chmod(0o755)
            env = dict(os.environ, PATH=str(bin_dir) + ':' + os.environ['PATH'], TMPDIR=str(tmp),
                       LOY_RUNTIME_DISPOSABLE='1', LOY_DB_HOST='127.0.0.1', LOY_DB_USER='root',
                       LOY_DB_PASSWORD='disposable-only', CALLS=str(log), FAKE_FAIL=failure,
                       FAKE_REPO=checkout, FAKE_DIRTY='1' if dirty else '0')
            result = subprocess.run(['bash', str(RUNNER)], cwd=ROOT, env=env, capture_output=True)
            calls = log.read_text() if log.exists() else ''
            self.assertFalse(list(tmp.glob('loyf-runtime.*')), 'All temporary site trees cleaned')
            return result, calls

    def test_full_runs_both_exact_sources_and_cleans(self):
        result, calls = self.run_case()
        self.assertEqual(0, result.returncode, result.stderr)
        self.assertEqual(2, calls.count('CREATE DATABASE'))
        self.assertEqual(2, calls.count('DROP DATABASE'))
        self.assertEqual(2, calls.count('characterization.php'))
        self.assertIn('baseline/candidate characterization PASS', result.stdout.decode())

    def test_failure_cleanup_and_mismatch(self):
        for failure in ['create', 'seed', 'scenario', 'mismatch', 'cleanup']:
            with self.subTest(failure=failure):
                result, calls = self.run_case(failure)
                self.assertNotEqual(0, result.returncode)
                self.assertIn('DROP DATABASE', calls)

    def test_installed_or_dirty_source_refused_before_database(self):
        for path, dirty in [('/Users/example/Local Sites/site/plugin', False), ('/tmp/source', True)]:
            result, calls = self.run_case(checkout=path, dirty=dirty)
            self.assertNotEqual(0, result.returncode)
            self.assertNotIn('CREATE DATABASE', calls)

    def test_explicit_disposable_credentials_required(self):
        env = {key: value for key, value in os.environ.items() if not key.startswith('LOY_')}
        result = subprocess.run(['bash', str(RUNNER)], env=env, capture_output=True)
        self.assertNotEqual(0, result.returncode)
        self.assertIn(b'LOY_RUNTIME_DISPOSABLE', result.stderr)
        env['LOY_RUNTIME_DISPOSABLE'] = '1'
        result = subprocess.run(['bash', str(RUNNER)], env=env, capture_output=True)
        self.assertNotEqual(0, result.returncode)
        self.assertIn(b'Disposable database connection', result.stderr)


if __name__ == '__main__':
    unittest.main()
