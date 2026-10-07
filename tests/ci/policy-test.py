#!/usr/bin/env python3
"""Exercise exact Git diffs, PR routing and the actual required-job shell gate."""
import json
import importlib.util
import itertools
import os
import re
from pathlib import Path
import subprocess
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[2]
spec = importlib.util.spec_from_file_location('policy', ROOT / 'scripts/ci-policy.py')
policy = importlib.util.module_from_spec(spec)
spec.loader.exec_module(policy)


def git(repo, *args):
    return subprocess.check_output(['git', '-C', str(repo), *args], stderr=subprocess.DEVNULL).decode().strip()


class PolicyTest(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory(prefix='loy-policy-')
        self.repo = Path(self.tmp.name)
        git(self.repo, 'init', '-q')
        git(self.repo, 'config', 'user.email', 'test@example.invalid')
        git(self.repo, 'config', 'user.name', 'LOY test')
        for path in policy.DOCS | {'inc/source.php', 'readme.txt', 'tests/runtime/run.sh'}:
            file = self.repo / path
            file.parent.mkdir(parents=True, exist_ok=True)
            file.write_text('Original text\n')
        self.base = self.commit()

    def tearDown(self):
        self.tmp.cleanup()

    def commit(self):
        git(self.repo, 'add', '-A')
        git(self.repo, 'commit', '-qm', 'Fixture', '--allow-empty')
        return git(self.repo, 'rev-parse', 'HEAD')

    def test_allowlist_and_modes(self):
        for path in sorted(policy.DOCS):
            (self.repo / path).write_text('Updated contract\n')
        self.assertEqual('LIGHTWEIGHT', policy.classify(self.repo, self.base, self.commit()))
        (self.repo / 'TESTING.md').chmod(0o755)
        self.assertEqual('FULL', policy.classify(self.repo, self.base, self.commit()))

    def test_deletion_and_regular_addition(self):
        (self.repo / 'TESTING.md').unlink()
        deleted = self.commit()
        self.assertEqual('LIGHTWEIGHT', policy.classify(self.repo, self.base, deleted))
        (self.repo / 'TESTING.md').write_text('Restored prose\n')
        self.assertEqual('LIGHTWEIGHT', policy.classify(self.repo, deleted, self.commit()))

    def test_unknown_source_ci_test_and_package_paths(self):
        for path in ['inc/source.php', 'readme.txt', 'tests/runtime/run.sh', '.github/workflows/ci.yml',
                     'scripts/ci-policy.py', 'scripts/stage-distribution.sh', 'docs/new.md', 'docs/tool.py',
                     'docs/../unknown', 'docs/weird\nname.md']:
            with self.subTest(path=path):
                git(self.repo, 'reset', '--hard', self.base)
                file = self.repo / path
                file.parent.mkdir(parents=True, exist_ok=True)
                file.write_text('Changed\n')
                self.assertEqual('FULL', policy.classify(self.repo, self.base, self.commit()))

    def test_symlink_binary_invalid_empty_and_rename(self):
        self.assertEqual('FULL', policy.classify(self.repo, self.base, self.base))
        for base in ['', 'main', '0' * 40, self.base[:12]]:
            self.assertEqual('FULL', policy.classify(self.repo, base, self.base))
        file = self.repo / 'TESTING.md'
        file.write_bytes(b'binary\0content')
        self.assertEqual('FULL', policy.classify(self.repo, self.base, self.commit()))
        file.unlink()
        file.symlink_to('../outside')
        self.assertEqual('FULL', policy.classify(self.repo, self.base, self.commit()))
        file.unlink()
        file.write_bytes(b'\xff')
        self.assertEqual('FULL', policy.classify(self.repo, self.base, self.commit()))
        file.rename(self.repo / 'unknown.md')
        self.assertEqual('FULL', policy.classify(self.repo, self.base, self.commit()))

    def test_routing(self):
        # Action type does not waive a source candidate; draft state is the routing signal.
        for activity in ['opened', 'synchronize', 'reopened', 'ready_for_review', 'converted_to_draft']:
            for certification in ['FULL', 'LIGHTWEIGHT']:
                with self.subTest(activity=activity, certification=certification):
                    self.assertEqual('DEVELOPMENT', policy.route('pull_request', 'true', certification))
                    self.assertEqual(certification, policy.route('pull_request', 'false', certification))
        for event, draft, mode in [('push', 'true', 'LIGHTWEIGHT'), ('unknown', 'false', 'LIGHTWEIGHT'),
                                   ('pull_request', '', 'LIGHTWEIGHT'), ('pull_request', 'false', '')]:
            self.assertEqual('FULL', policy.route(event, draft, mode))

    def test_workflow_gate_exhaustive(self):
        workflow = (ROOT / '.github/workflows/ci.yml').read_text()
        block = workflow.split('      - name: Fail closed on required jobs\n')[1].split('        run: |\n')[1]
        shell = '\n'.join(line[10:] for line in block.splitlines())
        results = ['success', 'failure', 'cancelled', 'skipped']
        for mode, syntax, runtime, event in itertools.product(
                ['FULL', 'LIGHTWEIGHT', 'POST_MERGE', 'DEVELOPMENT', '', 'invalid'], results, results,
                ['pull_request', 'push', 'unknown']):
            expected = syntax == 'success' and (
                (mode == 'FULL' and runtime == 'success') or
                (mode == 'LIGHTWEIGHT' and event == 'pull_request' and runtime == 'skipped') or
                (mode == 'POST_MERGE' and event == 'push' and runtime == 'skipped'))
            env = dict(os.environ, LOY_MODE=mode, LOY_SYNTAX_RESULT=syntax,
                       LOY_RUNTIME_RESULT=runtime, LOY_EVENT=event, LOY_REF='refs/heads/main')
            actual = subprocess.run(['bash', '-c', shell], env=env, capture_output=True)
            self.assertEqual(expected, actual.returncode == 0, (mode, syntax, runtime, event))
        env.update(LOY_MODE='POST_MERGE', LOY_SYNTAX_RESULT='success', LOY_RUNTIME_RESULT='skipped',
                   LOY_EVENT='push', LOY_REF='refs/heads/other')
        self.assertNotEqual(0, subprocess.run(['bash', '-c', shell], env=env, capture_output=True).returncode)

    def test_accepted_base_policy_and_workflow_wiring(self):
        workflow = (ROOT / '.github/workflows/ci.yml').read_text()
        self.assertIn('types: [opened, synchronize, reopened, ready_for_review]', workflow)
        self.assertEqual(1, workflow.count("if: needs.syntax.outputs.mode == 'FULL'"))
        self.assertIn('needs: [syntax, runtime]', workflow)
        self.assertIn("if: ${{ always() && (github.event_name != 'pull_request' || github.event.pull_request.draft == false) }}", workflow)
        self.assertIn('run: bash tests/runtime/run.sh', workflow)
        block = workflow.split('      - name: Classify with accepted base policy\n')[1].split('        run: |\n')[1].split('\n      - name:')[0]
        shell = '\n'.join(line[10:] for line in block.splitlines())
        with tempfile.TemporaryDirectory(prefix='loy-output-') as out:
            output = Path(out) / 'output'
            env = dict(os.environ, LOY_BASE=self.base, LOY_HEAD=self.base, LOY_DRAFT='true',
                       RUNNER_TEMP=out, GITHUB_OUTPUT=str(output), GITHUB_EVENT_NAME='pull_request', GITHUB_REF='refs/pull/1/merge')
            # First adoption cannot use Draft/LIGHTWEIGHT to waive the admitted full gate.
            subprocess.run(['bash', '-c', shell], cwd=self.repo, env=env, check=True, capture_output=True)
            self.assertEqual('mode=FULL\n', output.read_text())
            file = self.repo / 'scripts/ci-policy.py'
            file.parent.mkdir(exist_ok=True)
            file.write_text((ROOT / 'scripts/ci-policy.py').read_text())
            base = self.commit()
            (self.repo / 'TESTING.md').write_text('New prose\n')
            head = self.commit()
            for event, draft, expected in [('pull_request', 'true', 'DEVELOPMENT'),
                                            ('pull_request', 'false', 'LIGHTWEIGHT'),
                                            ('push', 'false', 'FULL')]:
                output.write_text('')
                env.update(LOY_BASE=base, LOY_HEAD=head, LOY_DRAFT=draft, GITHUB_EVENT_NAME=event)
                subprocess.run(['bash', '-c', shell], cwd=self.repo, env=env, check=True, capture_output=True)
                self.assertEqual('mode=' + expected + '\n', output.read_text())

    def test_job_conditions_and_concurrency(self):
        workflow = (ROOT / '.github/workflows/ci.yml').read_text()
        jobs = dict(re.findall(r'^  (syntax|runtime|required):\n(.*?)(?=^  [a-z]+:|\Z)', workflow, re.M | re.S))
        self.assertEqual({'syntax', 'runtime', 'required'}, set(jobs))
        self.assertNotIn('  package:', workflow)
        self.assertNotIn('converted_to_draft', workflow)
        conditions = {job: re.search(r'^    if: (.+)$', block, re.M)[1] for job, block in jobs.items()}

        def evaluate(expression, event, draft, mode=''):
            # Limited boolean fragments actually used in this workflow, in a test only.
            expression = expression.replace('${{', '').replace('}}', '').strip()
            expression = expression.replace('github.event_name', repr(event))
            expression = expression.replace('github.event.pull_request.draft', repr(draft))
            expression = expression.replace('needs.syntax.outputs.mode', repr(mode))
            expression = expression.replace('always()', 'True').replace('false', 'False')
            return eval(expression.replace('&&', ' and ').replace('||', ' or '), {'__builtins__': {}})

        for action in ['opened', 'synchronize', 'reopened']:
            for mode in ['', 'FULL', 'LIGHTWEIGHT']:
                self.assertFalse(evaluate(conditions['syntax'], 'pull_request', True, mode), action)
                self.assertFalse(evaluate(conditions['required'], 'pull_request', True, mode), action)
                # runtime has a normal successful syntax dependency, so Draft cannot allocate it.
                self.assertIn('needs: syntax', jobs['runtime'])
                self.assertNotIn('always()', conditions['runtime'])
                self.assertFalse(evaluate(conditions['runtime'], 'pull_request', True, ''))
        for event in ['pull_request', 'push']:
            self.assertTrue(evaluate(conditions['syntax'], event, False))
            self.assertTrue(evaluate(conditions['required'], event, False))
            for mode in ['FULL', 'LIGHTWEIGHT', 'POST_MERGE', '', 'invalid']:
                self.assertEqual(mode == 'FULL', evaluate(conditions['runtime'], event, False, mode))
        lane = re.search(r"\$\{\{ github.event_name == 'pull_request'.*?\}\}", workflow)[0]
        self.assertEqual('draft', evaluate(lane, 'pull_request', True))
        self.assertEqual('ready', evaluate(lane, 'pull_request', False))
        self.assertEqual('main', evaluate(lane, 'push', False))
        self.assertIn('github.event.pull_request.number || github.ref', workflow)
        self.assertIn('cancel-in-progress: true', workflow)

    def test_actual_workflow_package_step_succeeds(self):
        workflow = (ROOT / '.github/workflows/ci.yml').read_text()
        block = workflow.split('      - name: Stage, archive, extract and verify\n')[1].split('      - name: Repository integrity')[0]
        shell = '\n'.join(line[10:] for line in block.split('        run: |\n')[1].splitlines())
        # Execute the actual workflow step against a clean committed source, not a
        # rewritten local package command. Wrong staging/archive slugs must fail.
        with tempfile.TemporaryDirectory(prefix='loyf-package-contract-') as directory:
            checkout = Path(directory) / 'source'
            subprocess.run(['git', 'clone', '--no-hardlinks', '--quiet', str(ROOT), str(checkout)], check=True)
            head = git(checkout, 'rev-parse', 'HEAD')
            result = subprocess.run(['bash', '-c', shell], cwd=checkout,
                                    env=dict(os.environ, LOY_HEAD=head), capture_output=True, text=True)
            self.assertEqual(0, result.returncode, result.stdout + result.stderr)
            self.assertIn('source_sha=' + head, result.stdout)
            self.assertIn('files=' + str(len(json.loads((ROOT / 'config/free-import-manifest.json').read_text())['overlays']) + len(json.loads((ROOT / 'config/free-import-manifest.json').read_text())['imports'])), result.stdout)

    def test_package_step_preserved_and_failure_is_fatal(self):
        workflow = (ROOT / '.github/workflows/ci.yml').read_text()
        block = workflow.split('      - name: Stage, archive, extract and verify\n')[1].split('      - name: Repository integrity')[0]
        self.assertIn("if: steps.policy.outputs.mode == 'FULL'", block)
        shell = '\n'.join(line[10:] for line in block.split('        run: |\n')[1].splitlines())
        for command in ['scripts/stage-distribution.sh', 'scripts/verify-distribution.py', 'zip -qr', 'unzip -q']:
            self.assertIn(command, shell)
        # A failed package stage exits the actual step and therefore fails assurance.
        shell = shell.replace('bash scripts/stage-distribution.sh "$LOY_HEAD" "$work/stage"', 'false')
        shell = shell.replace('test "$(git rev-parse HEAD)" = "$LOY_HEAD"', 'true')
        shell = shell.replace("version=$(sed -n 's/^[[:space:]]*\\* Version:[[:space:]]*//p' loyalty-for-woocommerce.php | head -n1)", 'version=1.2.2')
        result = subprocess.run(['bash', '-c', shell], env=dict(os.environ, LOY_HEAD=self.base), capture_output=True)
        self.assertNotEqual(0, result.returncode)


if __name__ == '__main__':
    unittest.main()
