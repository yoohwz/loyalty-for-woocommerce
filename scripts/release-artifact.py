#!/usr/bin/env python3
"""Retain or reverify an external ZIP; never certify, rebuild on verify, or publish."""
import argparse
import importlib.util
import json
from pathlib import Path
import re
import subprocess
import sys
import tempfile
import zipfile

sys.dont_write_bytecode = True
ROOT = Path(__file__).resolve().parents[1]
spec = importlib.util.spec_from_file_location('distribution', ROOT / 'scripts/verify-distribution.py')
package = importlib.util.module_from_spec(spec)
spec.loader.exec_module(package)


def git(*args):
    return package.git_output(ROOT, *args).decode().strip()


def external(path):
    if Path(path).is_symlink():
        package.fail('Artifact path must not be a symlink')
    path = Path(path).resolve()
    if path == ROOT or ROOT in path.parents:
        package.fail('Artifact must be outside the source checkout')
    return path


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('command', choices=('build', 'verify'))
    parser.add_argument('--sha', required=True, help='Exact reviewed candidate, never the merge SHA')
    parser.add_argument('--version', required=True)
    parser.add_argument('--purpose', required=True, choices=('WORDPRESS_ORG', 'VERIFICATION_ONLY'))
    parser.add_argument('--output', help='Existing empty external directory (build only)')
    parser.add_argument('--receipt', help='External retained receipt (verify only)')
    parser.add_argument('--zip-sha256', help='Checksum recorded by Acceptance/Human (verify only)')
    parser.add_argument('--merged-sha', help='Fetched exact origin/main SHA; required before publication')
    args = parser.parse_args()
    if not re.fullmatch(r'[0-9a-f]{40}', args.sha) or not re.fullmatch(r'[0-9]+(?:\.[0-9]+){1,3}', args.version):
        package.fail('Exact SHA and numeric release version required')
    if git('rev-parse', 'HEAD') != args.sha or git('status', '--porcelain', '--untracked-files=all'):
        package.fail('Source must be clean at the exact candidate SHA')
    if args.command == 'build':
        if not args.output or args.receipt or args.zip_sha256 or args.merged_sha:
            package.fail('Build requires only --output and candidate identity')
        output = external(args.output)
        if not output.is_dir() or any(output.iterdir()):
            package.fail('Output directory must exist and be empty')
        archive = output / f'loyalty-for-woocommerce-{args.version}.zip'
        receipt_path = output / 'receipt.json'
    else:
        if args.output or not args.receipt or not args.zip_sha256 or not re.fullmatch(r'[0-9a-f]{64}', args.zip_sha256):
            package.fail('Verify requires --receipt and independently recorded --zip-sha256')
        receipt_path = external(args.receipt)
        receipt = json.loads(receipt_path.read_text())
        archive = receipt_path.parent / f'loyalty-for-woocommerce-{args.version}.zip'
        if archive.is_symlink() or receipt_path.is_symlink() or not archive.is_file():
            package.fail('Retained regular ZIP and receipt required')
        if package.digest(archive.read_bytes()) != args.zip_sha256:
            package.fail('Retained ZIP checksum differs from approved checksum; STOP, do not rebuild')
    with tempfile.TemporaryDirectory(prefix='loyf-artifact-') as work:
        stage = Path(work) / 'stage'; stage.mkdir()
        extracted = Path(work) / 'extracted'; extracted.mkdir()
        subprocess.run(['bash', str(ROOT / 'scripts/stage-distribution.sh'), args.sha, str(stage)], check=True)
        inventory = package.single_root(stage)
        package.verify_version(stage, args.version)
        identity = {
            'schema': 1, 'purpose': args.purpose, 'version': args.version,
            'source_sha': args.sha, 'source_tree': git('rev-parse', args.sha + '^{tree}'),
            'archive': archive.name, 'inventory': inventory,
        }
        if args.command == 'build':
            # Fixed timestamps/compression for repeatable preparation. Verify never writes this ZIP.
            with zipfile.ZipFile(archive, 'x', compression=zipfile.ZIP_DEFLATED) as bundle:
                for path in sorted(inventory):
                    info = zipfile.ZipInfo('loyalty-for-woocommerce/' + path, (1980, 1, 1, 0, 0, 0))
                    info.create_system = 3
                    info.external_attr = (stage / 'loyalty-for-woocommerce' / path).stat().st_mode << 16
                    info.compress_type = zipfile.ZIP_DEFLATED
                    bundle.writestr(info, (stage / 'loyalty-for-woocommerce' / path).read_bytes())
        identity['archive_sha256'] = package.digest(archive.read_bytes())
        if args.command == 'verify' and receipt != identity:
            package.fail('Receipt differs from exact candidate/version/purpose/ZIP/inventory')
        # Reject unsafe archive paths before extraction, then reuse the existing full byte verifier.
        package.verify_zip(archive, inventory)
        with zipfile.ZipFile(archive) as bundle:
            bundle.extractall(extracted)
        subprocess.run([sys.executable, str(ROOT / 'scripts/verify-distribution.py'),
                        '--source', str(ROOT), '--sha', args.sha, '--version', args.version,
                        '--stage', str(stage), '--zip', str(archive), '--extracted', str(extracted)], check=True)
        if args.merged_sha:
            if args.command != 'verify' or not re.fullmatch(r'[0-9a-f]{40}', args.merged_sha):
                package.fail('Exact merged SHA required for verify')
            if git('rev-parse', 'origin/main') != args.merged_sha:
                package.fail('Merged SHA differs from fetched origin/main')
            subprocess.run(['git', '-C', str(ROOT), 'merge-base', '--is-ancestor', args.sha, args.merged_sha], check=True)
            merged = package.boundary.git_inventory(ROOT, args.merged_sha)
            merged_inventory = {path: package.digest(package.git_output(ROOT, 'show', args.merged_sha + ':' + path))
                                for path in merged if package.allowed(path)}
            if merged_inventory != inventory:
                package.fail('Merged main has packaged-file drift; STOP')
        if args.command == 'build':
            with receipt_path.open('x') as output_file:
                json.dump(identity, output_file, sort_keys=True, indent=2)
                output_file.write('\n')
        print(f'receipt={receipt_path}')
        print(f'source_tree={identity["source_tree"]}')
        print(f'purpose={args.purpose}')
        print('Artifact integrity verified; this receipt grants no Acceptance or publication authority.')


if __name__ == '__main__':
    try:
        main()
    except (ValueError, OSError, subprocess.CalledProcessError, zipfile.BadZipFile) as error:
        print(f'Artifact verification failed: {error}', file=sys.stderr)
        sys.exit(1)
