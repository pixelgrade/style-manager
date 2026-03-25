# Style Manager Development Notes

## Local Sites Setup

- **Development source:** `/Users/georgeolaru/Local Sites/style-manager/` (Local site ID: m3xlT6fdf)
- **Test site:** `/Users/georgeolaru/Local Sites/sm1738sdah/` (Local site ID: SL1-NEb3N, URL: http://sm1738sdah.local, credentials: admin/admin)
- **Plugin path:** `wp-content/plugins/style-manager/`
- **Test site PHP:** 8.2.27
- **Dev site PHP CLI:** 7.4.33

## Private Local Files

- Keep `AGENTS.md` as the canonical shared instruction file for both Codex and Claude.
- Keep `CLAUDE.md` as a thin shim to `@AGENTS.md` so the shared instructions stay in one place.
- Keep shared private agent instructions in `AGENTS.local.md`.
- Keep vendor-neutral private research notes, plans, and issue writeups in `.ai/`.
- Keep tool-specific distilled working memory in `.claude/napkin.md`.
- Keep local env values in `.env.local`.
- Do not commit those private overlays; commit only the `*.example` files.
- Use `bin/bootstrap-private` to hydrate the private overlays after cloning the public repo.

Clone/bootstrap flow for a fresh machine:
```bash
# 1. Clone the public repo
git clone git@github.com:pixelgrade/style-manager.git
cd style-manager

# 2. Point the repo at the shared private companion repo
git config --local stylemanager.privateRepo git@github.com:pixelgrade/style-manager-private.git

# 3. Hydrate the private local overlays
bin/bootstrap-private
```

What gets pulled from the private repo when present:
- `AGENTS.local.md`
- `.ai/`
- `.claude/napkin.md`
- `.env.local`

If you prefer to keep an explicit local checkout of the private repo, use:
```bash
git clone git@github.com:pixelgrade/style-manager-private.git /path/to/style-manager-private
bin/bootstrap-private --source-dir /path/to/style-manager-private
```

How later private-overlay changes are handled:
- `bin/bootstrap-private` only manages these top-level targets: `AGENTS.local.md`, `.ai/`, `.claude/napkin.md`, and `.env.local`.
- In the default copy mode, rerun `bin/bootstrap-private --force` when one of those targets changed in the private repo and you want to replace your local copy.
- New files added inside `.ai/` are treated as part of the `.ai/` target, so copy mode still needs `--force` to refresh that directory.
- If you bootstrap with `--link`, later changes inside the private repo show up through the symlink without recopies.
- If you introduce a brand-new private path outside those managed targets, update `bin/bootstrap-private`, `.gitignore`, and the packaging exclusions before expecting it to sync.

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

## WUpdates Release

- Keep raw WUpdates host, port, and key material out of git. Store them only in private local files such as `AGENTS.local.md`, `.ai/`, or `.claude/napkin.md`. This repo assumes a local `wupdates` SSH alias is already configured on the release machine.
- Product type: `wup_plugin`
- Product slug: `style-manager`
- Release artifact: `../style-manager-X-X-X.zip`
- After publishing the GitHub release, publish the same zip in WUpdates:
  1. `wp-admin` -> `Plugin Versions` -> `Add New`
  2. Set parent plugin to `Style Manager`
  3. Upload the versioned zip
  4. Fill `Version Name` with the exact semantic version
  5. Paste release notes into the version post body if the WUpdates changelog should be updated
  6. Save/publish the version post, then edit the parent `Style Manager` product and switch `Current Version` to that new version post
- SSH verification uses the live WUpdates WordPress install at `/home/wupdates/public_html`:
  - Resolve the product ID by slug:
    ```bash
    ssh wupdates 'WP=/home/wupdates/public_html; CLI="php $WP/cli/wp-cli.phar --path=$WP"; PREFIX=$($CLI db prefix); $CLI db query "SELECT ID FROM ${PREFIX}posts WHERE post_type = '\''wup_plugin'\'' AND post_name = '\''style-manager'\'' LIMIT 1;"'
    ```
  - Verify the chosen version relationship and package metadata:
    ```bash
    ssh wupdates 'php /home/wupdates/public_html/cli/wp-cli.phar --path=/home/wupdates/public_html post meta get <PRODUCT_ID> current_version'
    ssh wupdates 'php /home/wupdates/public_html/cli/wp-cli.phar --path=/home/wupdates/public_html post meta get <CURRENT_VERSION_ID> parent_product'
    ssh wupdates 'php /home/wupdates/public_html/cli/wp-cli.phar --path=/home/wupdates/public_html post meta get <CURRENT_VERSION_ID> version'
    ssh wupdates 'php /home/wupdates/public_html/cli/wp-cli.phar --path=/home/wupdates/public_html post meta get <CURRENT_VERSION_ID> zip_attachment_id'
    ssh wupdates 'php /home/wupdates/public_html/cli/wp-cli.phar --path=/home/wupdates/public_html post meta get <ZIP_ATTACHMENT_ID> _wp_attached_file'
    ```
  - `parent_product` must match `<PRODUCT_ID>`, and the attached file must be the expected versioned zip under `wp-content/uploads/...`.
- HTTP verification:
  - In the WUpdates product edit screen, copy the `Current Version URL`.
  - `curl -I '<CURRENT_VERSION_URL>'` should return a `302` to the expected versioned zip on `media.wupdates.com`.
  - `curl -L -o /tmp/style-manager.zip '<CURRENT_VERSION_URL>'` can be used for a full download smoke test when needed.
  - The generated `api_wupl_version` URL is HTTPS-only. The same path over plain HTTP returns `404`.

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

## Customizer UI Learnings

- Perceived lag in Typography controls is often post-click Customizer work, not the shared `.sm-radio-group` shell or delayed click dispatch.
  - For bulk Typography setting changes, reduce duplicate downstream updates before tuning control cosmetics.
- Voice Tuner controls feel more responsive when the selected radio paints first and the palette resort runs on the next animation frame.
  - Prefer deferring heavy sidebar-only DOM work by one frame instead of doing it in the same click turn.
- Customizer sections can reshuffle injected controls after the first render.
  - When adding synthetic rows that must stay adjacent to native controls, anchor them to a stable sibling, rerun placement on the next frame, and keep a `MutationObserver` on the section container to restore the intended order.

## Similar Plugin Build Notes (Nova Blocks)
- Same build pattern as Style Manager
- `gulp zip` needs WP CLI environment
- Node: 22+
- PHP: 8.1+
