# Modernize Development Stack (Claude)

## Summary

Analysis of the current development stack and a phased plan to bring it to modern standards (Node 22, updated JS/PHP deps, simplified build pipeline).

---

## Current Stack Audit

### Node / JS Tooling

| Tool | Current | Status |
|---|---|---|
| Node | 14 (`.nvmrc`) | **EOL Apr 2023** |
| npm | `>=6.14.9` | EOL |
| Webpack | `^5.65.0` | OK (still v5, latest is ~5.98) |
| webpack-cli | `^4.9.1` | Outdated — v5 is current |
| Babel core/presets | `^7.16.x` | OK but stale |
| babel-loader | `^8.2.3` | Outdated — v9 is current |
| React / ReactDOM | `^17.0.2` | Outdated — v18/19 current |
| sass / gulp-sass | `^1.47 / ^5.1` | OK |
| sass-loader | `^12.4.0` | Outdated — v13 is current |
| Gulp | `^4.0.2` | OK |
| gulp-hub | `^4.2.0` | Unmaintained, fragile on newer Node |
| `request` | `^2.88.2` | **Deprecated since 2020** |
| `svg-sprite-loader` | `^6.0.11` | **Unmaintained** |
| `svg-inline-loader` | `^0.8.2` | **Abandoned** |
| `worker-loader` | `^3.0.8` | **Deprecated** — Webpack 5 has built-in Worker support |
| `gulp-clean` | `^0.4.0` | Old, use `del` directly |
| `gulp-sass-unicode` | `^1.0.5` | Unmaintained |
| `del` | `^6.0.0` | OK (v7+ is ESM-only; CJS gulpfile is fine with v6) |
| `@wordpress/browserslist-config` | `^4.1.2` | Stale — current is ^7 |

### PHP / Composer

| Tool | Current | Status |
|---|---|---|
| PHP target | `>=7.1` | Very old — WP 6.2+ requires PHP 7.4 minimum |
| PHPUnit | `^7.5` | **EOL** — current is v11 |
| WPCS | `^2.3.0` | Outdated — v3.x is current |
| `wpcs` dealerdirect installer | `^0.7.1` | Outdated — v1.0.0 released |
| `rector/rector` | `dev-main` | Unstable pin |
| `psr/container` | `^1.1` | v2 is current (PHP 8.0+ only, constrained by PHP floor) |
| `symfony/polyfill-php72` | `^1.27` | Can drop if PHP floor raised to 7.2+ |
| WordPress target | `^5.9` | WP 6.7 is current |

---

## Phased Modernization Plan

### Phase 1 — Node 22 + npm update (minimal risk)

**Files to change:**
- `.nvmrc`: `14` → `22`
- `package.json` engines: `"node": ">=22.0.0"`, `"npm": ">=10.0.0"`

**Steps:**
1. Update `.nvmrc` and `package.json` engines fields.
2. Run `nvm install 22 && nvm use 22`.
3. Delete `node_modules/` and `package-lock.json`, run `npm install`.
4. Validate that `npm run compile:production` succeeds without errors.

**Expected risk:** `gulp-hub` (loads `tasks/*.js` via glob + `require()`) has known issues on newer Node due to module resolution changes. If it breaks, move directly to Phase 3.

---

### Phase 2 — JS dependency updates

#### Safe bumps (non-breaking)

```
webpack-cli           ^4  → ^5
babel-loader          ^8  → ^9
@babel/core           → latest ^7
@babel/preset-env     → latest ^7
@babel/preset-react   → latest ^7
sass-loader           ^12 → ^13
mini-css-extract-plugin → latest ^2
terser-webpack-plugin → latest ^5
@wordpress/browserslist-config → ^7
```

Run: `npx npm-check-updates -u --filter "webpack-cli,babel-loader,@babel/*,sass-loader,mini-css-extract-plugin,terser-webpack-plugin,@wordpress/browserslist-config" && npm install`

#### Requires code changes

**React 17 → 18:**
- JSX transform: add `"runtime": "automatic"` to `@babel/preset-react` options (removes need for `import React` in every file).
- Replace `ReactDOM.render()` with `createRoot()` in all entry points under `src/_js/`.
- Audit for deprecated lifecycle methods or `act()` test patterns.

**`request` → native fetch (Node 22 has built-in `fetch`):**
- Identify all usages: likely only `tasks/google-fonts.js`.
- Replace with `fetch()` — no new dependency needed on Node 22.

**`worker-loader` → Webpack 5 native Web Workers:**
- Replace `import Worker from './my.worker.js'` with `new Worker(new URL('./my.worker.js', import.meta.url))`.
- Remove `worker-loader` from `package.json`.

**`svg-sprite-loader` / `svg-inline-loader` → `@svgr/webpack`:**
- Install `@svgr/webpack`.
- Update webpack rule: `{ test: /\.svg$/, use: ['@svgr/webpack'] }`.
- Update import sites from `import icon from './icon.svg'` (they become React components with `@svgr/webpack`).

#### Drop entirely

- `es6-promise` — native Promises exist in Node 22.
- `gulp-clean` — redundant with `del`.

---

### Phase 3 — Build tool cleanup (moderate risk, high reliability gain)

#### Replace `gulp-hub` with direct requires

`gulpfile.js` currently uses `gulp-hub` to auto-discover `tasks/*.js`. Replace with explicit requires:

```js
// gulpfile.js
const gulp = require('gulp');

require('./tasks/styles');
require('./tasks/build-folder');
require('./tasks/build-fix');
require('./tasks/build-translate');
require('./tasks/build-zip');
require('./tasks/composer');

gulp.task('zip', gulp.series('build:folder', 'build:fix', 'build:translate', 'build:zip'));
gulp.task('dev', gulp.parallel('watch:styles'));
```

Remove `gulp-hub` from `package.json`.

#### Drop `gulp-sass-unicode`

The charset stripping is already handled by the `replace()` call in `tasks/styles.js`:
```js
.pipe( replace( /^@charset "UTF-8";\n/gm, '' ) )
```
Remove `gulp-sass-unicode` import and `.pipe(sassUnicode())` call.

---

### Phase 4 — PHP floor raise (biggest impact, most reward)

**Recommended target: PHP 8.1**

WordPress 6.7 requires PHP 7.2+. Most production environments running modern WP are on PHP 8.0+. Raising to 8.1 unlocks:

#### What gets removed from the build

- `rector/rector` entirely — no more downgrade step, no more PHP 7.4 dev machine requirement
- `symfony/polyfill-php72` — functions polyfilled are built into PHP 7.2+
- `symfony/polyfill-mbstring` — mbstring is standard in PHP 8.1 environments
- The `@downgrade-to-php-71` composer script step
- The `vendor_prefixed/symfony/polyfill-php72/` and `vendor_prefixed/symfony/polyfill-mbstring/` directories

#### What gets updated

| Package | Current | New |
|---|---|---|
| `phpunit/phpunit` | `^7.5` | `^10.5` or `^11` |
| `wp-coding-standards/wpcs` | `^2.3.0` | `^3.1` |
| `dealerdirect/phpcodesniffer-composer-installer` | `^0.7.1` | `^1.0` |
| `psr/container` | `^1.1` | `^2.0` |
| `rector/rector` | `dev-main` | **removed** |
| PHP `engines` in composer.json | `>=7.1` | `>=8.1` |

#### Updated `composer.json` `require` block

```json
"require": {
    "cedaro/wp-plugin": "^0.4.0",
    "htmlburger/carbon-fields": "^3.3",
    "php": ">=8.1",
    "pimple/pimple": "^3.2",
    "psr/container": "^2.0",
    "psr/log": "^1.0 || ^2.0 || ^3.0"
}
```

#### Simplify `composer.json` scripts

Remove `@downgrade-to-php-71` from `pre-build`. The `zip` build script becomes:

```json
"pre-build": [
    "npm install",
    "npm run gulp composer:delete_lock_and_vendor",
    "@composer install --prefer-dist --no-scripts",
    "@prefix-dependencies",
    "@composer dump-autoload --no-dev --optimize"
],
"zip": [
    "@pre-build",
    "npm run gulp zip"
]
```

#### PHP code modernization (optional follow-up)

Once on 8.1, optional improvements to source code:
- Use `readonly` properties
- Use named arguments where helpful
- Use `enum` for fixed value sets
- Use union types and intersection types
- Use `match` expressions instead of `switch`
- Use `str_contains()`, `str_starts_with()`, `str_ends_with()` instead of `strpos()`

**If PHP 8.1 is too aggressive as a first step:** PHP 7.4 is a reasonable intermediate — it matches the current dev machine PHP, is the minimum for WP 6.2, and already enables property type declarations and arrow functions while still allowing Rector removal.

---

### Phase 5 — Build simplification (optional, longer-term)

Consider replacing the Gulp + Webpack dual setup with a single tool.

**Option A: Webpack-only**
- Move SCSS compilation fully into Webpack (already partially done via `sass-loader` in webpack config).
- Replace Gulp zip/rsync tasks with plain Node scripts (no framework needed).
- Simplifies `package.json` scripts significantly.

**Option B: Vite**
- Modern, ESM-first, extremely fast HMR.
- Handles React, SCSS, library mode out of the box.
- Smaller config than current Webpack setup.
- The `externals` (jQuery, lodash, React, chroma) map directly to Vite's `build.rollupOptions.external`.

Either option reduces the build surface area and removes the `gulp-*` dependency chain entirely.

---

## Recommended Execution Order

| Step | Scope | Risk | Value |
|---|---|---|---|
| 1 | Phase 1: Node 22 + engines update | Low | Unblocks all other work |
| 2 | Phase 3: Replace `gulp-hub`, drop `gulp-sass-unicode` | Low | Reliability |
| 3 | Phase 2: Safe dep bumps (webpack-cli, babel, sass-loader) | Low-Med | Security, compatibility |
| 4 | Phase 2: React 18, svg loaders, worker-loader | Medium | Code quality |
| 5 | Phase 4: PHP 8.1 floor, drop Rector | Medium | Biggest build simplification |
| 6 | Phase 5: Vite / build unification | High effort | Long-term DX |

**Highest leverage single change:** Phase 4 — removing Rector and raising the PHP floor eliminates the most brittle and time-consuming part of the current release process.

**Safest starting point:** Phase 1 + Phase 3 together in one PR — no functional changes, pure tooling cleanup.

---

## Verification

Before starting any phase, capture a baseline zip:
```sh
npm run zip
cp style-manager-*.zip /tmp/style-manager-baseline.zip
```

After completing each phase, rebuild and compare manifests to confirm shipped files are unchanged. See companion plan `2026-03-03-V1-modernize-stack-and-zip-verification.md` for the full zip diff verification strategy.
