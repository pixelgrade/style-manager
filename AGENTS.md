# Style Manager Development Notes

## Local Sites Setup

- **Development source:** `/Users/georgeolaru/Local Sites/style-manager/` (Local site ID: m3xlT6fdf)
- **Test site:** `/Users/georgeolaru/Local Sites/sm1738sdah/` (Local site ID: SL1-NEb3N, URL: http://sm1738sdah.local, credentials: admin/admin)
- **Plugin path:** `wp-content/plugins/style-manager/`
- **Test site PHP:** 8.2.27
- **Dev site PHP CLI:** 7.4.33

## Build Process (`npm run zip`)

### Prerequisites
- **Node version:** 22+ (`.nvmrc` / `.node-version` are set to `22`)
- **npm version:** 10+
- **PHP version:** 8.1+
- **Composer version:** 2.2+
- **WP CLI:** must be available in PATH for `wp i18n make-pot` (translation generation)
- **rsync + zip:** required by packaging tasks
- **dos2unix:** optional, for fixing line endings

### Build is NON-DESTRUCTIVE
The release pipeline no longer runs Rector/php-scoper during packaging and no longer deletes lock files.

### Build Sequence
1. `npm run zip` calls `composer run zip` which runs:
   - `@pre-build`:
     1. `npm install`
     2. `composer install --prefer-dist --no-dev --no-scripts --optimize-autoloader`
     3. `npm run gulp composer:delete_prefixed_vendor_libraries`
        - removes duplicated runtime vendor dirs (`symfony`, `psr`, `pimple`, `cedaro`, `instituteweb`)
        - sanitizes `vendor/composer/installed.json` to production entries
        - prunes `vendor/` to production namespaces (`composer`, `htmlburger`)
     4. `composer dump-autoload --no-dev --optimize`
     5. `node ./node-tasks/verify_release_autoload.js`
   - `gulp zip`:
     1. `build:preflight` - fail fast on missing tooling / unsupported Node, PHP, Composer versions
     2. `build:folder` - rsync to `../build/style-manager/`, remove files per `.zipignore`
     2. `build:fix` - fix permissions (755 dirs, 644 files) and line endings
     3. `build:translate` - replace `__plugin_txtd`, generate .pot, normalize volatile POT headers
     4. `build:zip` - create `style-manager-X-X-X.zip` in parent dir, delete build folder

### Known Build Issues

#### 1. Dev/stripped autoload references in production zip (fatal on activation)
If vendor cleanup or autoload regeneration fails, the build can include references to stripped dependencies. This causes fatal errors like:
```
Failed opening required '.../vendor/phpstan/phpstan/bootstrap.php'
```
**Fix:** keep `verify_release_autoload.js` passing in pre-build and CI.

#### 2. Old local toolchain in PATH
If local PATH points to older runtimes (e.g. PHP 7.4 / Composer 2.0), builds fail late.
**Fix:** `build:preflight` now hard-fails unless Node 22+, PHP 8.1+, Composer 2.2+ are available.

#### 3. Non-deterministic POT timestamp
`wp i18n make-pot` writes a dynamic `POT-Creation-Date`.
**Fix:** `build:translate:normalizepot` rewrites that header to a fixed value for reproducible zips.

### .zipignore Key Entries
- Dev deps and tooling dirs (including `vendor/phpstan`, `vendor/phpunit`, `vendor/phpcsstandards`, etc.)
- Prefixed deps (in vendor_prefixed/): `vendor/symfony`, `vendor/psr`, `vendor/pimple`, `vendor/cedaro`
- Source files: `src/_js`, `src/_scss`, `src/_css`
- Build tools/docs/plans: `node_modules`, `tasks`, `node-tasks`, `plans`, lint config dotfiles

### Composer Autoload (production - correct state)
- PSR-4: `Pixelgrade\StyleManager\` => `src/`
- PSR-4: `Carbon_Fields\` => `vendor/htmlburger/carbon-fields/core`
- Classmap: `vendor_prefixed/` (cedaro, pimple, psr, symfony - all prefixed with `Pixelgrade\StyleManager\Vendor`)
- Files: `src/functions.php`, `src/sm-functions.php`, `src/cloud-filter-functions.php`, `src/deprecated.php`, `vendor_prefixed/symfony/polyfill-mbstring/bootstrap.php`
- **Must NOT include**: `vendor/symfony/polyfill-*/bootstrap.php` (these are stripped by .zipignore)

## GitHub & Distribution
- GitHub repo: https://github.com/pixelgrade/style-manager
- **NOT on WordPress.org repository** — no SVN deployment needed
- WUpdates ID: mg8pX
- Release asset: versioned zip (e.g., `style-manager-2-2-9.zip`)
- WUpdates upload: manual zip upload at https://wupdates.com/ (product ID: mg8pX)
- Distribution is via GitHub releases + WUpdates only

## Issue & Commit Workflow

Every fix or improvement **must** follow this workflow:

1. **Create a GitHub issue** describing the problem and root cause
2. **Assign it to the latest open milestone** (or create a new one if none exists)
3. **Commit with `Fixes #N`** in the message to auto-close the issue on push
4. **Push to main** — the issue closes automatically

This applies to both this repo (`pixelgrade/style-manager`) and the theme repo (`pixelgrade/anima`).

## WP 7.0 Compatibility Learnings

- The block editor is always iframed in WP 7.0, so dark mode must be synced in both documents:
  - admin shell (`document.documentElement`)
  - editor canvas iframe (`iframe[name="editor-canvas"]` documentElement)
- One-shot `domReady` class sync is not enough. Keep iframe sync resilient with:
  - iframe `load` listener
  - `MutationObserver` for body class and iframe insertion changes
- For `sm_dark_mode_advanced`, runtime reads via `\Pixelgrade\StyleManager\get_option()` can become stale outside Customizer because cached minimal option details may still hold an older `value`.
  - For editor/admin runtime toggles that must reflect latest value, prefer direct `\get_option( 'sm_dark_mode_advanced', 'off' )`.
- Customizer preview callbacks should avoid hard dependency on `window.parent.sm.customizer`.
  - Add guarded fallbacks (`window.sm.customizer`) and return safe defaults when unavailable.
- Editor dynamic CSS injection should use `enqueue_block_editor_assets` only.
  - Avoid duplicate paths that can inject style-manager inline CSS twice.
- QA with versioned plugin folders (`style-manager-2-2-9-*`) is valid, but only one Style Manager copy can be active at a time.
  - Deactivate canonical plugin before activating a QA copy.
  - Reactivate canonical plugin when finishing tests.
- In this Local setup, WP-CLI may fail DB connection because `DB_HOST=localhost` resolves to socket while CLI PHP default socket is not Local's socket.
  - If WP-CLI fails with `Error establishing a database connection`, verify via browser/admin or use explicit Local socket tooling.

## Similar Plugin Build Notes (Nova Blocks)
- Same build pattern as Style Manager
- `gulp zip` needs WP CLI environment
- Node: 22+
- PHP: 8.1+
