# Style Manager Development Notes

## Local Sites Setup

- **Development source:** `/Users/georgeolaru/Local Sites/style-manager/` (Local site ID: m3xlT6fdf)
- **Test site:** `/Users/georgeolaru/Local Sites/sm1738sdah/` (Local site ID: SL1-NEb3N, URL: http://sm1738sdah.local, credentials: admin/admin)
- **Plugin path:** `wp-content/plugins/style-manager/`
- **Test site PHP:** 8.2.27
- **Dev site PHP CLI:** 7.4.33

## Build Process (`npm run zip`)

### Prerequisites
- **Node version:** `nvm use 14.17.3` (required for gulp tasks)
- **PHP version:** PHP 7.4 (`brew link --force --overwrite php@7.4`) - required for Rector downgrade step
- **WP CLI:** must be available in PATH for `wp i18n make-pot` (translation generation)
- **dos2unix:** optional, for fixing line endings

### Build is DESTRUCTIVE
The build process modifies source files in-place (Rector rewrites PHP files). **Always clone to a temporary directory before building.**

### Build Sequence
1. `npm run zip` calls `composer run zip` which runs:
   - `@pre-build`:
     1. `npm install`
     2. `gulp composer:delete_lock_and_vendor` (deletes vendor/ & composer.lock)
     3. `composer install --prefer-dist --no-scripts` (reinstalls ALL deps including dev)
     4. `@prefix-dependencies` (php-scoper prefixes vendor deps to `vendor_prefixed/`)
     5. `@downgrade-to-php-71` (Rector transpiles PHP to 7.1 compat) **NEEDS PHP 7.4**
     6. `composer dump-autoload --no-dev --optimize` **CRITICAL: regenerates autoloader without dev deps**
   - `gulp zip`:
     1. `build:folder` - rsync to `../build/style-manager/`, remove files per `.zipignore`
     2. `build:fix` - fix permissions (755 dirs, 644 files) and line endings
     3. `build:translate` - replace `__plugin_txtd` with `style-manager`, generate .pot
     4. `build:zip` - create `style-manager-X-X-X.zip` in parent dir, delete build folder

### Known Build Issues

#### 1. Dev autoloader in production zip (fatal error on activation)
If the Rector step (#5) or autoloader regeneration (#6) fails, the build continues with a **dev autoloader** that references missing files. The `.zipignore` strips dev dependency directories but NOT autoloader entries. This causes a fatal error on activation:
```
Failed opening required '.../vendor/phpstan/phpstan/bootstrap.php'
```
**Fix:** Ensure `composer dump-autoload --no-dev --optimize` runs successfully. Verify `vendor/composer/autoload_files.php` does NOT contain references to dev packages (phpstan, rector, brain/monkey, etc.).

#### 2. Prefixed packages duplicated in autoloader
Even with `--no-dev`, `composer dump-autoload` includes autoload entries from packages in `vendor/` that are ALSO prefixed in `vendor_prefixed/` (symfony, psr, pimple, cedaro). Since `.zipignore` strips `vendor/symfony`, `vendor/psr`, etc., the autoloader references non-existent files.
**Fix:** After `composer install`, delete the overlapping vendor directories AND remove them from `vendor/composer/installed.json` before running `composer dump-autoload`. The correct `installed.json` should only contain `htmlburger/carbon-fields`.

#### 3. platform_check.php PHP version mismatch
`psr/container` requires PHP >= 7.4, causing `platform_check.php` to require PHP 7.4 even though the plugin targets PHP 7.1. After removing psr/container from `installed.json`, the check correctly reflects PHP >= 7.1.

#### 4. symfony/polyfill-php72 metapackage issue
On PHP >= 7.2, `symfony/polyfill-php72` v1.31+ becomes a metapackage (no files). php-scoper fails because `vendor/symfony/polyfill-php72/` directory doesn't exist. Pin to `^1.25.0` or use `composer install --prefer-dist` to ensure real files are installed.

### Alternative Build Approach (when php-scoper/Rector fail)
Since `vendor_prefixed/` is committed to git (already scoped), a simpler build is possible:
1. `git archive` the repo to a temp directory
2. `composer install --prefer-dist --no-dev --no-scripts --optimize-autoloader`
3. Delete overlapping vendor dirs: `rm -rf vendor/symfony vendor/psr vendor/pimple vendor/cedaro vendor/instituteweb`
4. Clean `vendor/composer/installed.json` to only keep `htmlburger/carbon-fields`
5. `composer dump-autoload --no-dev --optimize`
6. `npx gulp zip` (builds the final archive)

### .zipignore Key Entries
- Dev deps: `vendor/phpstan`, `vendor/rector`, `vendor/brain`, `vendor/mockery`, etc.
- Prefixed deps (in vendor_prefixed/): `vendor/symfony`, `vendor/psr`, `vendor/pimple`, `vendor/cedaro`
- Source files: `src/_js`, `src/_scss`, `src/_css`
- Build tools: `php-scoper`, `rector.php`, `node_modules`, `tasks`

### Composer Autoload (production - correct state)
- PSR-4: `Pixelgrade\StyleManager\` => `src/`
- PSR-4: `Carbon_Fields\` => `vendor/htmlburger/carbon-fields/core`
- Classmap: `vendor_prefixed/` (cedaro, pimple, psr, symfony - all prefixed with `Pixelgrade\StyleManager\Vendor`)
- Files: `src/functions.php`, `src/sm-functions.php`, `src/cloud-filter-functions.php`, `src/deprecated.php`, `vendor_prefixed/symfony/polyfill-mbstring/bootstrap.php`, `vendor_prefixed/symfony/polyfill-php72/bootstrap.php`
- **Must NOT include**: `vendor/symfony/polyfill-*/bootstrap.php` (these are stripped by .zipignore)

## GitHub & Distribution
- GitHub repo: https://github.com/pixelgrade/style-manager
- **NOT on WordPress.org repository** — no SVN deployment needed
- WUpdates ID: mg8pX
- Release asset: versioned zip (e.g., `style-manager-2-2-9.zip`)
- WUpdates upload: manual zip upload at https://wupdates.com/ (product ID: mg8pX)
- Distribution is via GitHub releases + WUpdates only

## Similar Plugin Build Notes (Nova Blocks)
- Same build pattern as Style Manager
- `gulp zip` needs WP CLI environment
- Node: `nvm use 14.17.3`
- PHP: `brew install php@7.4 && brew link --force --overwrite php@7.4`
