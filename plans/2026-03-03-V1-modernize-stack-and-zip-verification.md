# Build Artifact Verification + Plan Archival for Node Modernization

## Summary
I’ll add a pre/post build zip comparison step to verify that modernization did not unintentionally alter shipped files, then persist this plan under `plans/` for future implementation.

## Goal
1. Create a reproducible comparison process that validates a newly built ZIP against a pre-change baseline ZIP.
2. Persist the plan in a tracked `plans/` artifact for future implementation execution.

## Scope
- Keeps existing build pipeline shape (`npm run zip` via Composer/Gulp).
- Adds a non-invasive verification layer and documentation/process updates.
- No functional plugin code changes included in this pass.

## Required plan artifacts to create

1. New plan file:
   - `plans/2026-03-03-modernize-stack-and-zip-verification.md`

2. New helper artifact (implementation phase):
   - `plans/artifacts/` (directory for baseline and target zip captures, not committed if desired)
   - `scripts/verify-build-zip.js` (Node CLI for zip diff)
   - Optional docs update: `README.md` build section.

## Changes to implement

1. Add deterministic zip diff script.
- New file: `scripts/verify-build-zip.js`
- CLI interface:
  - `node scripts/verify-build-zips.js --baseline <path> --candidate <path> --mode manifest|binary`
- Behavior:
  - Validate both inputs exist and are valid archives.
  - Extract both to temporary directories.
  - Generate canonical manifests:
    - relative path
    - file mode
    - size
    - SHA-256 checksum
  - Sort entries before comparison.
  - Report deltas in four buckets:
    - `missing-in-candidate`
    - `added-in-candidate`
    - `size-mismatch`
    - `checksum-mismatch`
  - Exit code non-zero if any delta exists unless `--allow-new-or-missing` is passed.
  - Always print a short summary + absolute diff file path.

2. Add npm scripts for repeatable checks.
- Update `package.json`:
  - Add:
    - `"build:zip:pre": "npm run zip"`
    - `"build:zip:pre:capture": "npm run zip && cp style-manager-*.zip plans/artifacts/pre-implementation/style-manager-pre.zip"`
    - `"build:zip:post:capture": "npm run zip && cp style-manager-*.zip plans/artifacts/post-implementation/style-manager-post.zip"`
    - `"build:zip:verify": "node scripts/verify-build-zips.js --baseline plans/artifacts/pre-implementation/style-manager-pre.zip --candidate plans/artifacts/post-implementation/style-manager-post.zip"`
- If keeping the existing release command semantics untouched, do not gate `npm run zip` by default; run verify in an explicit release validation step.

3. Add documented build flow with pre/post artifact capture.
- Add section to `README.md`:
  - Capture baseline zip before modernization changes.
  - Run all required dependency/tooling updates.
  - Rebuild zip.
  - Run verification and review diff report.

4. Save this plan in `plans/` for implementation reuse.
- Place the concrete implementation plan text in:
  - `plans/2026-03-03-modernize-stack-and-zip-verification.md`

## Verification strategy for pre/post zip comparison

1. Pre-change:
- Ensure clean source state.
- Run `npm run zip:pre:capture`.
- Persist artifact as `plans/artifacts/pre-implementation/style-manager-pre.zip`.

2. Apply modernization changes (Node/tooling/runtime updates, dependency refresh, build hardening).

3. Post-change:
- Run `npm run zip:post:capture`.
- Run `npm run build:zip:verify`.

4. Acceptance criteria:
- No missing/added/mismatched files unless explicitly allowed.
- If only expected updates should occur, allow controlled exceptions in a config file:
  - e.g., `plans/build-verify-allowlist.txt`
  - for fields like package metadata that may intentionally change version strings or timestamps.

## Public interface / behavior changes
- New developer-facing commands:
  - `npm run build:zip:pre:capture`
  - `npm run build:zip:post:capture`
  - `npm run build:zip:verify`
- New script CLI:
  - `scripts/verify-build-zips.js --baseline <zip1> --candidate <zip2>`

## Test cases and scenarios

1. Positive baseline parity:
- Baseline and candidate zips from the same source produce zero diffs.

2. Expected change parity:
- A controlled change (e.g., version bump) shows only expected file diffs and exits with pass when allowlist is configured.

3. Failure mode:
- Corrupted candidate zip or missing required file (e.g., `.php` entry missing) fails and emits a clear delta summary.

4. Determinism check:
- Run `npm run zip` twice with no source change to confirm identical manifests (helps catch flaky build artifacts).

## Assumptions
- Zip filename pattern remains `style-manager-*.zip` in repo root.
- The implementation phase can create `plans/artifacts/` but it may be gitignored if binaries are not tracked.
- CI/runtime for local dev includes `node` and `bash` utilities available for temporary extraction (`zip/unzip` or Node-native alternatives).

## Rollout order
1. Persist updated plan file in `plans/` (this step).
2. Add verify script + npm scripts.
3. Add documentation.
4. Run a dry pre/post comparison against current baseline before code modernization begins.
5. Proceed with modernization and use verification after each meaningful change.
