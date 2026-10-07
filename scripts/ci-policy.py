#!/usr/bin/env python3
"""Fail-closed exact-diff certification classifier; no repository or GitHub writes."""
import os
import re
import subprocess

# These are prose contracts, excluded from the shipped distribution. No docs/** glob.
DOCS = {
    'AGENTS.md', 'TESTING.md', 'docs/workflow.md',
    'docs/POINTS-LIFECYCLE-CONTRACT.md', 'docs/POINTS-TRANSACTION.md',
    'docs/INTEGRATION-COMPATIBILITY.md', 'docs/ORDER-REDEMPTION.md',
    'docs/COUPON-REDEMPTION.md', 'docs/CORE-REWARDS.md', 'docs/releasing.md',
}


def git(repo, *args):
    return subprocess.check_output(['git', '-C', str(repo), *args], stderr=subprocess.DEVNULL)


def classify(repo, base, head):
    """Unknown, empty, malformed, non-text or non-regular input always needs FULL."""
    try:
        if not all(re.fullmatch(r'[0-9a-f]{40}', oid) for oid in (base, head)):
            return 'FULL'
        for oid in (base, head):
            git(repo, 'cat-file', '-e', oid + '^{commit}')
        if git(repo, 'rev-parse', '--is-shallow-repository').strip() != b'false':
            return 'FULL'
        git(repo, 'merge-base', base, head)
        entries = git(repo, 'diff', '--raw', '--no-abbrev', '--no-renames', '-z', base, head).split(b'\0')
        if entries.pop() != b'' or not entries or len(entries) % 2:
            return 'FULL'
        for header, path in zip(entries[::2], entries[1::2]):
            match = re.fullmatch(rb':(000000|100644) (000000|100644) ([0-9a-f]{40}) ([0-9a-f]{40}) ([AMD])', header)
            if not match or path.decode('utf-8', 'strict') not in DOCS:
                return 'FULL'
            old_mode, new_mode, old_oid, new_oid, _ = match.groups()
            for mode, oid in ((old_mode, old_oid), (new_mode, new_oid)):
                if mode != b'000000':
                    blob = git(repo, 'cat-file', 'blob', oid.decode())
                    if b'\0' in blob:
                        return 'FULL'
                    blob.decode('utf-8', 'strict')
        return 'LIGHTWEIGHT'
    except (OSError, UnicodeError, subprocess.CalledProcessError):
        return 'FULL'


def route(event, draft, certification):
    if event == 'pull_request' and draft == 'true':
        return 'DEVELOPMENT'
    if event == 'pull_request' and draft == 'false' and certification in {'FULL', 'LIGHTWEIGHT'}:
        return certification
    # push main and invalid routing inputs never authorize an expensive-job skip.
    return 'FULL'



def main():
    mode = route(os.getenv('GITHUB_EVENT_NAME', ''), os.getenv('LOY_DRAFT', ''),
                 classify('.', os.getenv('LOY_BASE', ''), os.getenv('LOY_HEAD', '')))
    print('mode=' + mode)


if __name__ == '__main__':
    main()
