# WordPress.org release process

Human makes two separate decisions: **prepare version `<version>` for
`WORDPRESS_ORG`**, then **publish that exact version + retained ZIP SHA-256 to
WordPress.org**. Codex handles technical preparation and execution. Merge,
Acceptance, a receipt, Git tag or CI success is not publication permission.
This runbook grants no version selection, release or SVN authority; LOYF-45
keeps source at 1.2.2 and exercises verification artifacts only.

Repository: `yoohwz/loyalty-for-woocommerce`; slug/root:
`loyalty-for-woocommerce`; SVN:
`https://plugins.svn.wordpress.org/loyalty-for-woocommerce/`.
See [distribution audit](../docs/DISTRIBUTION-CHECKLIST.md),
[testing evidence](../TESTING.md) and canonical governance via LOYF-1.

## 1. Prepare one release candidate

- Recover Human's chosen version/destination, fetch origin, inspect Issue,
  branch/PR/status and preserve unrelated work. Establish the **last actually
  published WordPress.org version** from directory/download and SVN evidence;
  GitHub main or an unreleased build is not the baseline. 1.2.2 was the baseline
  when this workflow was admitted; recheck at release time. Never reuse it or an
  existing public tag/version. Check bounded GitHub tags/releases/PR evidence
  and SVN `tags/<version>` for collisions. Read access does not prove write
  permission; verify the real operator/access mechanism without assuming a
  username, credentials or standing publication authority. Uncertain access
  stops the live step; do not invent an uploader.
- In the one authorized release branch/PR, update plugin `Version`, readme
  `Stable tag`, newest readme/changelog.txt entries and Upgrade Notice if needed.
  Preserve historical entries. Notes describe **net merchant-visible changes
  since the last published version**, once per finished feature. Include shipped
  fixes, security/compatibility changes and upgrade requirements; omit unreleased
  iterations, PR chronology and CI/tooling details without customer impact.
- Refresh compatibility claims only from release-time evidence; regenerate POT
  if product strings change and refresh affected Free overlay hashes. Reuse
  current import manifest (currently 29 pinned imports + 71 overlays = 100
  product files; derive inventory from the manifest, never a frozen count).
- Run relevant local checks, existing Plugin Check/readme validator and audit
  triage. Reconcile actionable findings; historical false positives do not become
  new blanket blockers. Preserve the existing Free economic/upgrade/runtime
  matrix and known limitations.

## 2. Freeze, retain and accept the exact artifact

Use a **clean external checkout at the final candidate SHA**, outside Human
Local. Choose an empty persistent artifact directory outside Git (not `/tmp`
for a real release). From that checkout:

```sh
python3 scripts/release-artifact.py build --sha <candidate-sha> --version <version> \
  --purpose WORDPRESS_ORG --output <absolute-empty-external-directory>
```

The wrapper reuses `stage-distribution.sh` and `verify-distribution.py`, checks
header/Stable tag/both newest changelog versions, inventory, source/stage/ZIP/
extracted bytes and unsafe entries. It retains the versioned ZIP and
`receipt.json` (candidate SHA/tree, version, purpose, ZIP SHA-256 and every file
hash). `VERIFICATION_ONLY` is for engineering dry runs and cannot be used for a
public release. Receipt identity is evidence, not an approval parser or registry.

Freeze/push Draft; obtain the canonical fresh isolated independent Technical
Review, then mark **that unchanged PASS SHA** Ready for **one FULL `LOYF Required
CI`**. Classifier remains unchanged. CI's ZIP is temporary and deleted; do not
claim it is the retained release artifact. Separate ChatGPT Acceptance must
record the final candidate SHA/tree and this retained ZIP checksum/path/receipt.
Human Merge remains separate. If candidate bytes change, return to Draft and
repeat applicable review/CI/Acceptance on the new SHA. No second release pipeline.

After Human merge, existing `ci-provenance.py` may admit fast `POST_MERGE` only
for its proven two-parent unchanged accepted candidate and exact successful CI.
Missing/ambiguous provenance fails closed to FULL; never force POST_MERGE.
Retain the original ZIP unchanged after Acceptance; do not rebuild, retarget the
receipt to the merge SHA, or infer byte identity from similar source/timestamps.

## 3. Quick verification before separately authorized publication

Fetch origin. In the clean detached checkout of the accepted candidate, run:

```sh
python3 scripts/release-artifact.py verify --sha <accepted-candidate-sha> \
  --version <version> --purpose WORDPRESS_ORG --receipt <absolute-receipt-path> \
  --zip-sha256 <checksum-recorded-by-Acceptance-and-Human> \
  --merged-sha <fetched-origin-main-sha>
```

This is offline integrity verification: it reads the retained ZIP, checks its
approved checksum, candidate SHA/tree, metadata and receipt inventory, proves
candidate ancestry in fetched main and compares all merged package paths/bytes.
Temporary staging/extraction does not rebuild the archive. It neither runs
`run.sh`, `modern.sh`, `onboarding.sh`, `consumers.sh` nor starts runtime/CI jobs.
Read GitHub evidence to confirm exact-head CI, independent review, Acceptance and
Human merge remain valid; tool output cannot supply those authorities.

**STOP** if artifact/receipt is missing, checksum/version/purpose differs,
package inventory or bytes drift, provenance is uncertain, or the version/tag is
already published. Do not silently build a replacement; report and obtain a new
Human decision and applicable candidate certification. Changes to version,
readme/changelog/POT, manifest or build-affecting scripts after Acceptance also
invalidate candidate evidence. Reverification alone cannot certify a new SHA.

## 4. Publish only on explicit Human authorization

Authorization must name version, artifact checksum and WordPress.org. Verify
available `svn`, the actual authorized operator/credential mechanism, destination
and that `tags/<version>` is still absent immediately before writing. Public
`svn ls` alone is not authenticated write-access proof. Access denied or uncertain
means STOP/report. Keep secrets out of the repository and command arguments.

Use a temporary SVN checkout of the root URL above. Extract the **verified retained
ZIP** to a separate temporary directory. Synchronize its `loyalty-for-woocommerce/`
**contents** into `trunk/` (main PHP directly inside trunk, no root wrapper),
including obsolete-file deletions; preserve SVN metadata and leave `assets/`
untouched. Schedule additions/deletions using SVN. Review `svn status` and diff,
and compare trunk's exact product inventory/hashes with the approved receipt
before committing. Unknown changes stop publication.

```sh
svn copy <svn-checkout>/trunk <svn-checkout>/tags/<version>
svn status <svn-checkout>
svn commit <svn-checkout> -m "Release version <version>"
```

Commit trunk and new tag together once using the verified operator. No existing
tag overwrite, automatic GitHub merge/tag publisher, workflow_dispatch, new
uploader service or credential storage. LOYF-45 performs no production SVN
checkout, authentication or write; offline layout rehearsals use temporary paths.

## 5. Read back

Record SVN revision and read back trunk/tag inventory, hashes, header and Stable
tag against the approved receipt. Confirm directory/download/update version after
cache refresh; WordPress.org's generated ZIP may have different compression, so
compare extracted product bytes rather than assuming the retained ZIP checksum.
Complete Release Confirmation only if enabled and Human-authorized; report any
pending confirmation/cache delay. On ambiguous commit failure, inspect remote
revision/tag/bytes before retrying; never blindly repeat writes or silently
rollback. Report candidate/merged SHA, retained checksum/path, SVN revision and
readback results with remaining limitations.

References (read-only): BM Core runbook at
[`6d448791ea36fd27cb1e3917327a7221bdeddc9c`](https://github.com/yoohwz/wc-blacklist-manager/blob/6d448791ea36fd27cb1e3917327a7221bdeddc9c/.github/RELEASING.md),
[BM-0243](https://github.com/yoohwz/wc-blacklist-manager/issues/221),
[BM-0229](https://github.com/yoohwz/wc-blacklist-manager/issues/198);
official [SVN](https://developer.wordpress.org/plugins/wordpress-org/how-to-use-subversion/),
[Stable tag](https://developer.wordpress.org/plugins/wordpress-org/how-your-readme-txt-works/)
and [Release Confirmation](https://developer.wordpress.org/plugins/wordpress-org/release-confirmation-emails/).
