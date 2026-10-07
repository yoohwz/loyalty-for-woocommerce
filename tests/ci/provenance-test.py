#!/usr/bin/env python3
"""Exact Git merge fixtures and bounded canonical Checks/Actions evidence."""
import copy
import importlib.util
from pathlib import Path
import subprocess
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[2]
spec = importlib.util.spec_from_file_location('provenance', ROOT / 'scripts/ci-provenance.py')
provenance = importlib.util.module_from_spec(spec); spec.loader.exec_module(provenance)


def git(repo, *args):
    return subprocess.check_output(['git', '-C', str(repo), *args], stderr=subprocess.DEVNULL).decode().strip()


def evidence(candidate):
    check = dict(id=101, name='LOYF Required CI', head_sha=candidate, status='completed', conclusion='success',
                 app=dict(slug='github-actions', id=15368), check_suite=dict(id=99),
                 details_url='https://github.com/yoohwz/loyalty-for-woocommerce/actions/runs/10/job/101')
    run = dict(id=10, head_sha=candidate, check_suite_id=99, repository=dict(full_name=provenance.REPOSITORY),
               head_repository=dict(full_name=provenance.REPOSITORY), path='.github/workflows/ci.yml',
               event='pull_request', status='completed', conclusion='success')
    return dict(total_count=1, check_runs=[check]), run


class ProvenanceTest(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory(prefix='loy-provenance-'); self.repo = Path(self.tmp.name)
        git(self.repo, 'init', '-q'); git(self.repo, 'config', 'user.email', 'test@example.invalid')
        git(self.repo, 'config', 'user.name', 'LOY test')
        (self.repo / 'file').write_text('base\n'); self.before = self.commit()
        git(self.repo, 'checkout', '-qb', 'candidate')
        (self.repo / 'file').write_text('candidate\n'); self.candidate = self.commit()
        git(self.repo, 'checkout', '--detach', self.before)
        git(self.repo, 'merge', '--no-ff', '-qm', 'Fixture message has no authority', self.candidate)
        self.head = git(self.repo, 'rev-parse', 'HEAD')
        self.checks, self.run = evidence(self.candidate)
        self.calls = []

    def tearDown(self): self.tmp.cleanup()

    def commit(self):
        git(self.repo, 'add', '-A'); git(self.repo, 'commit', '-qm', 'Fixture')
        return git(self.repo, 'rev-parse', 'HEAD')

    def read(self, path):
        self.calls.append(path)
        return copy.deepcopy(self.checks if '/check-runs?' in path else self.run)

    def classify(self, before=None, head=None, read=None):
        return provenance.classify(self.repo, before or self.before, head or self.head, provenance.REPOSITORY, read or self.read)

    def test_proven_merge_and_older_draft_failure(self):
        self.assertEqual('POST_MERGE', self.classify())
        self.assertEqual(2, len(self.calls))
        older = copy.deepcopy(self.checks['check_runs'][0]); older.update(id=100, conclusion='failure')
        self.checks['check_runs'].append(older); self.checks['total_count'] = 2
        self.assertEqual('POST_MERGE', self.classify())
        self.checks['check_runs'][-1]['id'] = 102
        self.assertEqual('FULL', self.classify())

    def test_git_proof_negative_controls(self):
        self.assertEqual('FULL', self.classify(before=self.candidate))
        for oid in ['', 'main', '0' * 40, self.head[:12]]:
            self.assertEqual('FULL', provenance.classify(self.repo, oid, self.head, provenance.REPOSITORY, self.read))
        self.assertEqual('FULL', provenance.classify(self.repo, self.before, self.head, 'unknown/repo', self.read))
        git(self.repo, 'checkout', '--detach', self.candidate)
        self.assertEqual('FULL', self.classify()) # checkout identity mismatch
        self.assertEqual('FULL', self.classify(head=self.candidate)) # direct single-parent
        git(self.repo, 'checkout', '--detach', self.head)
        (self.repo / 'file').write_text('merge changed content\n'); git(self.repo, 'add', '.')
        git(self.repo, 'commit', '--amend', '-qm', 'Changed merge')
        self.assertEqual('FULL', self.classify(head=git(self.repo, 'rev-parse', 'HEAD')))
        self.assertEqual([], self.calls)

    def test_check_proof_negative_controls(self):
        original = copy.deepcopy(self.checks)
        variants = []
        for key, value in [('head_sha', '0'*40), ('name', 'Other'), ('status', 'in_progress'),
                           ('conclusion', 'failure'), ('conclusion', 'skipped'), ('app', dict(slug='other', id=1)),
                           ('details_url', 'https://evil.invalid/actions/runs/10/job/101'),
                           ('details_url', 'https://github.com/yoohwz/loyalty-for-woocommerce/actions/runs/10/job/102')]:
            changed = copy.deepcopy(original); changed['check_runs'][0][key] = value; variants.append(changed)
        variants += [{}, None, dict(total_count=0, check_runs=[]), dict(total_count=101, check_runs=original['check_runs'])]
        duplicate = copy.deepcopy(original); duplicate['check_runs'] *= 2; duplicate['total_count'] = 2; variants.append(duplicate)
        for changed in variants:
            self.checks = changed
            with self.subTest(checks=changed): self.assertEqual('FULL', self.classify())

    def test_run_and_api_negative_controls(self):
        original = copy.deepcopy(self.run)
        for key, value in [('id', 11), ('head_sha', '0'*40), ('check_suite_id', 98), ('path', 'other.yml'),
                           ('event', 'push'), ('conclusion', 'failure'), ('status', 'in_progress'),
                           ('repository', dict(full_name='other/repo')), ('head_repository', None)]:
            self.run = copy.deepcopy(original); self.run[key] = value
            with self.subTest(key=key): self.assertEqual('FULL', self.classify())
        for error in [OSError('unavailable'), ValueError('bad JSON'), subprocess.TimeoutExpired('gh', 15)]:
            def failed(path): raise error
            self.assertEqual('FULL', self.classify(read=failed))


if __name__ == '__main__': unittest.main()
