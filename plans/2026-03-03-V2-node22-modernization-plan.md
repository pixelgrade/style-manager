# Style Manager Modernization Plan (Node 22 + PHP 8.1+) (Codex)

Date: 2026-03-03
Scope: Modernize the development/build stack while keeping release output stable.

## 1. Baseline decisions

- Runtime minimum PHP is now 8.1 (plugin metadata + composer + compatibility checks updated).
- Development/runtime target should stay on current stable PHP releases (8.2/8.3 locally), while preserving distributed plugin compatibility with PHP 8.1+.
- Node baseline target is Node 22 (npm 10).

## 2. Current constraints and risks

- Node currently pinned to 14 through `.nvmrc`, `.node-version`, and strict engine checks.
- Build uses a mixed webpack + gulp pipeline, with legacy plugins (`gulp-exec`, `gulp-clean`, `gulp-rsync`) and deprecated `request`.
- Zip build path is fragile and has a known buffer failure (`gulp-exec` maxBuffer).
- Build process is destructive and partially non-reproducible (deleting lock/vendor during packaging).
- PHP downgrade flow exists via Rector + php-scoper and currently targets older language levels.

## 3. Target end state

- Node 22 is the default and enforced version for dev/build.
- PHP compatibility floor is 8.1+; no code paths or checks remain tied to 7.x.
- `npm run zip` is deterministic, fail-fast, and succeeds on clean environments.
- CI runs both JavaScript and PHP checks on modern runners with artifacted release zips.

## 4. Execution plan

### Phase A: Stabilize build for Node 22

1. Update version enforcement:
- Set `package.json` engines to Node 22 / npm 10.
- Update `.nvmrc` and `.node-version` to Node 22.
- Keep strict engine enforcement.

2. Remove immediate blockers:
- Replace `gulp-exec` in zip/build tasks with `child_process.spawn` (or `execa`) and inherited stdio.
- Replace deprecated `request` usage with native `fetch` (Node 22).

3. Verify:
- `npm install`
- `npm run compile:production`
- `npm run zip`
- Validate generated zip structure and activation on local WP site.

### Phase B: Refresh JS toolchain

1. Upgrade core packages:
- `webpack`, `webpack-cli`, `babel-loader`, `@babel/core`, presets, `sass`, `sass-loader`, `mini-css-extract-plugin`, `terser` stack.

2. Remove legacy pieces:
- Migrate away from `worker-loader` to webpack 5 native Worker syntax.
- Remove unused deps and dead gulp plugins.

3. Verify:
- No behavior regressions in Customizer UI.
- Build output diffs reviewed for expected-only changes.

### Phase C: Make release build reproducible

1. Stop destructive dependency resolution:
- Remove lockfile deletion from packaging flow.
- Use locked installs for repeatable zip artifacts.

2. Add safety checks:
- Fail build if required tools (`wp`, `zip`) are missing.
- Add autoload integrity checks after composer/autoload generation.

3. Verify:
- Two clean builds on same commit generate equivalent file trees.

### Phase D: Modernize CI/CD

1. Replace Travis with GitHub Actions.
2. Add matrix:
- Node 22
- PHP 8.1 and 8.2+
3. Add pipeline jobs:
- JS build
- PHP lint/compat checks
- Zip build + artifact upload

## 5. Milestones and deliverables

- M1: Node 22-compatible build green locally.
- M2: JS dependency refresh merged with regression checks.
- M3: Reproducible zip pipeline and integrity checks in place.
- M4: GitHub Actions-based CI producing release-ready artifacts.

## 6. Definition of done

- `npm run zip` succeeds on Node 22 without manual fixes.
- Plugin activates and runs on PHP 8.1 and PHP 8.2 test environments.
- No deprecated runtime tooling remains in critical build path.
- CI artifacts are reproducible and release-ready.
