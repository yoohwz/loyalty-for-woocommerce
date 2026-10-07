#!/usr/bin/env python3
"""Read-only, bounded proof that main merged an unchanged certified candidate."""
import json
import os
import re
import subprocess
import sys

REPOSITORY = 'yoohwz/loyalty-for-woocommerce'
SHA = re.compile(r'[0-9a-f]{40}')


def git(repo, *args):
    return subprocess.check_output(['git', '-C', str(repo), *args], stderr=subprocess.DEVNULL).decode().strip()


def api(path):
    # gh receives only generated paths and read-only GETs; never log authentication.
    result = subprocess.run(['gh', 'api', '--hostname', 'github.com', '--method', 'GET', path],
                            capture_output=True, check=True, timeout=15)
    return json.loads(result.stdout)


def certified(candidate, repository, read=api):
    checks = read(f'repos/{repository}/commits/{candidate}/check-runs?filter=latest&per_page=100')
    rows = checks['check_runs']
    if not isinstance(rows, list) or type(checks['total_count']) is not int or checks['total_count'] != len(rows):
        return False
    canonical = [row for row in rows if row['name'] == 'LOYF Required CI']
    if not canonical or any(type(row['id']) is not int for row in canonical):
        return False
    # Latest canonical attempt is authoritative; do not resurrect an earlier success.
    latest = max(canonical, key=lambda row: row['id'])
    if sum(row['id'] == latest['id'] for row in canonical) != 1:
        return False
    if (latest['head_sha'] != candidate or latest['status'] != 'completed' or
            latest['conclusion'] != 'success' or latest['app']['slug'] != 'github-actions' or
            latest['app']['id'] != 15368):
        return False
    match = re.fullmatch(r'https://github\.com/' + re.escape(repository) + r'/actions/runs/([1-9][0-9]*)/job/([1-9][0-9]*)', latest['details_url'])
    if not match or int(match[2]) != latest['id']:
        return False
    run = read(f'repos/{repository}/actions/runs/{match[1]}')
    return (run['id'] == int(match[1]) and run['head_sha'] == candidate and
            run['check_suite_id'] == latest['check_suite']['id'] and
            run['repository']['full_name'] == repository and run['head_repository']['full_name'] == repository and
            run['path'] == '.github/workflows/ci.yml' and run['event'] == 'pull_request' and
            run['status'] == 'completed' and run['conclusion'] == 'success')


def classify(repo, before, head, repository, read=api):
    """All proof obligations must hold; any unavailable/ambiguous evidence is FULL."""
    try:
        if repository != REPOSITORY or not all(SHA.fullmatch(oid) for oid in (before, head)):
            return 'FULL'
        if git(repo, 'rev-parse', 'HEAD') != head or git(repo, 'rev-parse', '--is-shallow-repository') != 'false':
            return 'FULL'
        parents = git(repo, 'show', '-s', '--format=%P', head).split()
        if len(parents) != 2 or parents[0] != before or parents[0] == parents[1]:
            return 'FULL'
        if git(repo, 'rev-parse', head + '^{tree}') != git(repo, 'rev-parse', parents[1] + '^{tree}'):
            return 'FULL'
        if certified(parents[1], repository, read):
            print('Proven unchanged certified merge: ' + head + ' candidate=' + parents[1], file=sys.stderr)
            return 'POST_MERGE'
    except (OSError, ValueError, TypeError, KeyError, subprocess.SubprocessError):
        pass
    return 'FULL'


def main():
    mode = 'FULL'
    if (os.getenv('GITHUB_EVENT_NAME') == 'push' and os.getenv('GITHUB_REF') == 'refs/heads/main' and
            os.getenv('GITHUB_SERVER_URL') == 'https://github.com'):
        mode = classify('.', os.getenv('LOY_BASE', ''), os.getenv('LOY_HEAD', ''), os.getenv('GITHUB_REPOSITORY', ''))
    print('mode=' + mode)


if __name__ == '__main__':
    main()
