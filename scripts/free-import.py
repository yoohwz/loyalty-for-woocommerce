#!/usr/bin/env python3
"""Exact-SHA read-only import planning, external staging and Free boundary verification."""
import argparse
import base64
import hashlib
import json
import os
from pathlib import Path, PurePosixPath
import re
import stat
import subprocess
import sys
import unicodedata

ROOT = Path(__file__).resolve().parents[1]
MANIFEST = ROOT / 'config/free-import-manifest.json'
PRODUCT_FILES = {'loyalty-for-woocommerce.php', 'readme.txt', 'changelog.txt', 'license.txt'}
PRODUCT_DIRS = {'css', 'img', 'inc', 'js', 'languages', 'templates'}
TOOL_DIRS = {'.git', '.github', 'config', 'docs', 'scripts', 'tests'}
TOOL_FILES = {'AGENTS.md', 'TESTING.md', '.gitignore', '.DS_Store', 'Thumbs.db'}
SHA = re.compile(r'[0-9a-f]{40}')
DIGEST = re.compile(r'[0-9a-f]{64}')


def fail(message):
    raise ValueError(message)


def digest(data):
    return hashlib.sha256(data).hexdigest()


def safe_path(path):
    if not isinstance(path, str) or not path or '\\' in path or '\0' in path or any(ord(c) < 32 for c in path):
        fail('Invalid path')
    parts = path.split('/')
    if any(part in {'', '.', '..'} for part in parts) or PurePosixPath(path).is_absolute():
        fail('Unsafe path: ' + path)
    return path


def validate_targets(manifest):
    # Treat all targets as files on a portable case-insensitive/NFC filesystem.
    # Reject the whole conflicting path family before any output can be written.
    targets = {}
    for row in manifest['imports'] + manifest['overlays']:
        target = safe_path(row['target'])
        normalized = unicodedata.normalize('NFC', target).casefold()
        if normalized in targets: fail('Target collision: ' + target + ' and ' + targets[normalized])
        targets[normalized] = target
    for normalized, target in targets.items():
        for ancestor in PurePosixPath(normalized).parents:
            if str(ancestor) in targets: fail('File/directory target collision: ' + target + ' and ' + targets[str(ancestor)])


def load_manifest(path=MANIFEST):
    def unique_pairs(pairs):
        result = {}
        for key, value in pairs:
            if key in result: fail('Duplicate manifest key: ' + key)
            result[key] = value
        return result
    manifest = json.loads(Path(path).read_text(), object_pairs_hook=unique_pairs)
    if manifest['schema_version'] != 1 or manifest['phase'] not in {'contract-only', 'core-runtime'}: fail('Unsupported import contract')
    if manifest['upstream']['repository'] != 'yoohwz/wc-loyalty' or not SHA.fullmatch(manifest['upstream']['sha']) or not SHA.fullmatch(manifest['upstream']['tree']): fail('Invalid upstream identity')
    free = manifest['free']
    if (free['repository'], free['slug'], free['main_file'], free['text_domain']) != ('yoohwz/loyalty-for-woocommerce', 'loyalty-for-woocommerce', 'loyalty-for-woocommerce.php', 'loyalty-for-woocommerce'): fail('Free package identity changed')
    if not SHA.fullmatch(free['baseline_sha']): fail('Invalid Free baseline')
    if manifest['transform']['id'] != 'wp-text-domain-v1' or manifest['transform']['from'] != 'wc-loyalty' or manifest['transform']['to'] != free['text_domain']: fail('Unknown transformation')
    inventory = manifest['upstream_inventory']
    for path, row in inventory.items():
        safe_path(path)
        if row['mode'] not in {'100644', '100755'} or not SHA.fullmatch(row['blob']) or row['decision'] not in {'import', 'reference-only', 'forbidden', 'excluded'}: fail('Invalid inventory: ' + path)
    sources = set(); targets = set()
    for row in manifest['imports']:
        source = safe_path(row['source']); target = safe_path(row['target'])
        if source in sources or target in targets: fail('Duplicate import source/target')
        sources.add(source); targets.add(target)
        if source not in inventory or inventory[source]['decision'] != 'import': fail('Import is not allowlisted: ' + source)
        if row['transform'] != 'wp-text-domain-v1' or not DIGEST.fullmatch(row['sha256']) or not DIGEST.fullmatch(row['output_sha256']) or type(row['domain_replacements']) is not int or row['domain_replacements'] < 0: fail('Invalid import transformation')
        if not target.endswith('.php') or target.split('/')[0] not in PRODUCT_DIRS: fail('Invalid import target')
    if sources != {path for path, row in inventory.items() if row['decision'] == 'import'}: fail('Incomplete import inventory')
    for path in manifest['blocked_mixed_modules']:
        if inventory[safe_path(path)]['decision'] != 'reference-only' and not any(row['source'] == path and row.get('extraction') == path for row in manifest['imports']): fail('Mixed module cannot be imported as a whole: ' + path)
    for row in manifest['overlays']:
        path = safe_path(row['target'])
        if path in targets: fail('Overlay/import collision: ' + path)
        targets.add(path)
        if (path not in PRODUCT_FILES and path.split('/')[0] not in PRODUCT_DIRS) or row['mode'] not in {'100644', '100755'} or not DIGEST.fullmatch(row['sha256']): fail('Invalid overlay')
    if not PRODUCT_FILES <= targets or not manifest['imports'] or not manifest['forbidden_symbols']: fail('Incomplete boundary')
    if any('extraction' in row for row in manifest['imports']):
        encoded = (ROOT / 'config/free-core-extractions.json').read_bytes()
        if manifest.get('extractions') != {'id': 'byte-spans-v1', 'path': 'config/free-core-extractions.json', 'sha256': digest(encoded)}: fail('Invalid extraction binding')
        recipes = json.loads(encoded)
        for row in manifest['imports']:
            if 'extraction' in row and (row['extraction'] != row['source'] or recipes[row['source']]['input_sha256'] != row['sha256']): fail('Invalid extraction source')
    validate_targets(manifest)
    return manifest


def git(repo, *args):
    return subprocess.check_output(['git', '-C', str(repo), *args], stderr=subprocess.PIPE, timeout=30)


def git_inventory(repo, sha, strict=True):
    if not isinstance(sha, str) or not SHA.fullmatch(sha): fail('An exact 40-character commit SHA is required')
    if git(repo, 'rev-parse', sha + '^{commit}').decode().strip() != sha: fail('Not an exact commit')
    rows = {}
    for entry in git(repo, 'ls-tree', '-rz', sha).split(b'\0'):
        if not entry: continue
        metadata, path = entry.decode('utf-8').split('\t', 1)
        mode, kind, blob = metadata.split()
        safe_path(path)
        if strict and (kind != 'blob' or mode not in {'100644', '100755'}): fail('Unsupported upstream entry: ' + path)
        rows[path] = {'mode': mode, 'blob': blob}
    return rows


def verify_upstream(repo, sha, manifest):
    origin = git(repo, 'remote', 'get-url', 'origin').decode().strip()
    if origin not in {'https://github.com/yoohwz/wc-loyalty.git', 'https://github.com/yoohwz/wc-loyalty', 'git@github.com:yoohwz/wc-loyalty.git'}: fail('Upstream repository binding mismatch')
    if sha != manifest['upstream']['sha']: fail('Upstream SHA drift; run drift before updating the reviewed manifest')
    actual = git_inventory(repo, sha)
    expected = {p: {'mode': row['mode'], 'blob': row['blob']} for p, row in manifest['upstream_inventory'].items()}
    if actual != expected or git(repo, 'rev-parse', sha + '^{tree}').decode().strip() != manifest['upstream']['tree']: fail('Upstream inventory/tree drift')
    return actual


def transform(data):
    result = subprocess.run(['php', str(ROOT / 'scripts/transform-free-domain.php')], input=data, capture_output=True, check=True, timeout=30)
    payload = json.loads(result.stdout)
    return base64.b64decode(payload['source'], validate=True), payload['replacements']


def extract(data, row, manifest):
    if 'extraction' not in row: return data
    config = manifest['extractions']
    if config['id'] != 'byte-spans-v1' or config['path'] != 'config/free-core-extractions.json': fail('Unknown extraction contract')
    encoded = (ROOT / config['path']).read_bytes()
    if digest(encoded) != config['sha256']: fail('Extraction recipe drift')
    recipes = json.loads(encoded)
    recipe = recipes[row['extraction']]
    if row['extraction'] != row['source'] or digest(data) != recipe['input_sha256']: fail('Extraction source mismatch')
    selected = bytearray(); last = 0
    for span in recipe['spans']:
        a, b = span['start'], span['end']
        if type(a) is not int or type(b) is not int or not 0 <= last <= a <= b <= len(data): fail('Invalid extraction span')
        selected.extend(data[last:a]); selected.extend(base64.b64decode(span['replacement'], validate=True)); last = b
    selected.extend(data[last:]); selected = bytes(selected)
    if digest(selected) != recipe['selected_sha256']: fail('Extraction result drift')
    return selected


def transformed_imports(repo, sha, manifest):
    inventory = verify_upstream(repo, sha, manifest)
    output = {}
    for row in manifest['imports']:
        data = git(repo, 'cat-file', 'blob', inventory[row['source']]['blob'])
        if digest(data) != row['sha256']: fail('Upstream content drift: ' + row['source'])
        converted, count = transform(extract(data, row, manifest))
        if digest(converted) != row['output_sha256'] or count != row['domain_replacements']: fail('Transformation drift: ' + row['source'])
        scan_forbidden(row['target'], converted, manifest)
        output[row['target']] = converted
    return output


def scan_forbidden(path, data, manifest):
    if path.endswith(('.php', '.js', '.phtml')):
        text = data.decode('utf-8', 'strict')
        for symbol in manifest['forbidden_symbols']:
            if re.search(r'(?i)(?<![A-Za-z0-9_])' + re.escape(symbol) + r'(?![A-Za-z0-9_])', text): fail('Forbidden Premium symbol in ' + path + ': ' + symbol)


def tree_files(root, source=False):
    root = Path(root)
    if not root.is_dir() or root.is_symlink(): fail('Invalid source/package root')
    found = {}
    for entry in root.iterdir():
        if source and entry.name in TOOL_DIRS | TOOL_FILES: continue
        if entry.name not in PRODUCT_DIRS | PRODUCT_FILES: fail('Unexpected source/package entry: ' + entry.name)
        if entry.is_symlink(): fail('Symlink in source/package: ' + entry.name)
        if entry.is_file():
            if entry.name not in PRODUCT_FILES: fail('Product directory is not a directory')
            found[entry.name] = entry
        elif entry.is_dir():
            if entry.name not in PRODUCT_DIRS: fail('Product file is not a file')
            for current, directories, files in os.walk(entry, followlinks=False):
                for name in directories + files:
                    file = Path(current) / name
                    if source and name in {'.DS_Store', 'Thumbs.db'} and file.is_file() and not file.is_symlink(): continue
                    if file.is_symlink() or not (file.is_file() or file.is_dir()): fail('Unsafe filesystem entry')
                    if file.is_file(): found[safe_path(file.relative_to(root).as_posix())] = file
        else: fail('Unsafe source/package entry')
    return found


def verify_tree(root, manifest, mode='legacy', source=False):
    if mode not in {'legacy', 'projection'}: fail('Unsupported source mode')
    expected = {row['target']: row['sha256'] for row in manifest['overlays']}
    if mode == 'projection' or manifest['phase'] == 'core-runtime': expected.update({row['target']: row['output_sha256'] for row in manifest['imports']})
    modes = {row['target']: row['mode'] for row in manifest['overlays']}
    if mode == 'projection' or manifest['phase'] == 'core-runtime': modes.update({row['target']: manifest['upstream_inventory'][row['source']]['mode'] for row in manifest['imports']})
    files = tree_files(root, source)
    if set(files) != set(expected): fail('Source/package inventory drift: ' + json.dumps({'added': sorted(set(files) - set(expected)), 'missing': sorted(set(expected) - set(files))}))
    for path, file in files.items():
        if bool(file.stat().st_mode & 0o111) != (modes[path] == '100755'): fail('Executable mode drift: ' + path)
        data = file.read_bytes()
        scan_forbidden(path, data, manifest)
        if digest(data) != expected[path]: fail('Source/package content drift: ' + path)
    plugin = files[manifest['free']['main_file']].read_text()
    for key, expected_value in {'Plugin Name': manifest['free']['plugin_name'], 'Plugin URI': manifest['free']['plugin_uri'], 'Text Domain': manifest['free']['text_domain'], 'Version': manifest['free']['version']}.items():
        match = re.search(r'(?m)^\s*\* ' + re.escape(key) + r':\s*(.+?)\s*$', plugin)
        if not match or match[1] != expected_value: fail('Free metadata drift: ' + key)
    return len(files)


def drift(repo, sha, manifest):
    # Read-only report. Even an unchanged tree at another commit requires re-admission.
    origin = git(repo, 'remote', 'get-url', 'origin').decode().strip()
    if origin not in {'https://github.com/yoohwz/wc-loyalty.git', 'https://github.com/yoohwz/wc-loyalty', 'git@github.com:yoohwz/wc-loyalty.git'}: fail('Upstream repository binding mismatch')
    current = git_inventory(repo, sha, strict=False); old = manifest['upstream_inventory']
    changed = []
    for path in sorted(set(old) | set(current)):
        before = old.get(path); after = current.get(path)
        if before is None or after is None or (before['mode'], before['blob']) != (after['mode'], after['blob']):
            changed.append({'path': path, 'change': 'added' if before is None else 'removed' if after is None else 'modified', 'decision': before['decision'] if before else 'unclassified', 'before': before, 'after': after})
    return {'repository': manifest['upstream']['repository'], 'pinned_sha': manifest['upstream']['sha'], 'observed_sha': sha, 'readmission_required': sha != manifest['upstream']['sha'] or bool(changed), 'changes': changed}


def stage(repo, sha, source, head, output, manifest):
    validate_targets(manifest)
    source = Path(source).resolve(); output = Path(output)
    if not output.is_absolute() or output.is_symlink() or not output.is_dir() or any(output.iterdir()): fail('Output must be an empty absolute external directory')
    output = output.resolve()
    for protected in (source, Path(repo).resolve(), ROOT):
        output_key = unicodedata.normalize('NFC', str(output)).casefold()
        protected_key = unicodedata.normalize('NFC', str(protected)).casefold()
        if output_key == protected_key or output_key.startswith(protected_key.rstrip('/') + '/'): fail('Output must be outside source and upstream')
    if not SHA.fullmatch(head) or git(source, 'rev-parse', 'HEAD').decode().strip() != head or git(source, 'status', '--porcelain', '--untracked-files=all').strip(): fail('Free source must be a clean exact-head checkout')
    origin = git(source, 'remote', 'get-url', 'origin').decode().strip()
    if origin not in {'https://github.com/yoohwz/loyalty-for-woocommerce.git', 'https://github.com/yoohwz/loyalty-for-woocommerce', 'git@github.com:yoohwz/loyalty-for-woocommerce.git'}: fail('Free repository binding mismatch')
    git(source, 'merge-base', '--is-ancestor', manifest['free']['baseline_sha'], head)
    verify_tree(source, manifest, 'legacy', source=True)
    imports = transformed_imports(repo, sha, manifest)
    overlays = {}
    for row in manifest['overlays']:
        data = git(source, 'show', head + ':' + row['target'])
        if digest(data) != row['sha256']: fail('Committed overlay drift')
        overlays[row['target']] = data
    # All proof obligations precede any output write. Never edit either checkout.
    root = output / manifest['free']['slug']; root.mkdir()
    for row in manifest['overlays']:
        target = root / row['target']; target.parent.mkdir(parents=True, exist_ok=True)
        target.write_bytes(overlays[row['target']]); target.chmod(int(row['mode'][-3:], 8))
    for row in manifest['imports']:
        target = root / row['target']; target.parent.mkdir(parents=True, exist_ok=True)
        target.write_bytes(imports[row['target']]); target.chmod(int(manifest['upstream_inventory'][row['source']]['mode'][-3:], 8))
    verify_tree(root, manifest, 'projection')
    return {'upstream_sha': sha, 'free_head': head, 'mode': 'external-inert-projection', 'files': len(manifest['overlays']) + len(imports)}


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--manifest', default=str(MANIFEST))
    sub = parser.add_subparsers(dest='command', required=True)
    for command in ('inspect', 'drift', 'stage'):
        p = sub.add_parser(command); p.add_argument('--upstream', required=True); p.add_argument('--sha', required=True)
        if command == 'stage':
            for option in ('source', 'head', 'output'): p.add_argument('--' + option, required=True)
    for command in ('verify-source', 'verify-tree'):
        p = sub.add_parser(command); p.add_argument('--root', required=True)
        p.add_argument('--mode', choices=('legacy', 'projection'), default='legacy')
    args = parser.parse_args(); manifest = load_manifest(args.manifest)
    if args.command == 'inspect':
        result = {'upstream': manifest['upstream'], 'imports': sorted(transformed_imports(args.upstream, args.sha, manifest)), 'overlays': [row['target'] for row in manifest['overlays']], 'forbidden': sorted(path for path, row in manifest['upstream_inventory'].items() if row['decision'] == 'forbidden'), 'blocked_mixed_modules': manifest['blocked_mixed_modules']}
    elif args.command == 'drift': result = drift(args.upstream, args.sha, manifest)
    elif args.command == 'stage': result = stage(args.upstream, args.sha, args.source, args.head, args.output, manifest)
    else: result = {'mode': args.mode, 'files': verify_tree(args.root, manifest, args.mode, args.command == 'verify-source')}
    print(json.dumps(result, sort_keys=True, indent=2))
    if args.command == 'drift' and result['readmission_required']: return 1
    return 0


if __name__ == '__main__':
    try:
        sys.exit(main())
    except (ValueError, KeyError, TypeError, OSError, subprocess.SubprocessError) as error:
        print('Free import verification failed: ' + str(error), file=sys.stderr)
        sys.exit(1)
