# Style Manager Development Notes

## Public Project Context

- Style Manager is a WordPress plugin distributed from the WordPress.org plugin directory.
- Public repository: `https://github.com/pixelgrade/style-manager`
- Plugin slug: `style-manager`
- Plugin path inside a WordPress install: `wp-content/plugins/style-manager/`

## Private Local Overlays

- Keep machine-specific paths, credentials, private runbooks, and local QA shortcuts out of tracked public files.
- `AGENTS.local.example.md` documents the local-only note categories.
- `bin/bootstrap-private` can hydrate private local overlays from a user-provided private source.
- Do not commit hydrated private overlays or local environment files.

## Cross-Stack Strategy Decisions

When Style Manager work changes or settles product, business, positioning, monetization, Pixelgrade.com, Pixelgrade LT vs Pixelgrade Plus, starter strategy, or cross-repo LT stack architecture, save the durable decision in the central strategy folder:

`/Users/georgeolaru/Developer/pixelsite/master-strategy/`

Before making or changing those decisions, read:
- `/Users/georgeolaru/Developer/pixelsite/master-strategy/README.md`
- `/Users/georgeolaru/Developer/pixelsite/master-strategy/decisions/README.md`
- `/Users/georgeolaru/Developer/pixelsite/master-strategy/pixelgrade-lt-stack-strategy.md`
- `/Users/georgeolaru/Developer/pixelsite/master-strategy/source-index.md`

For any meaningful cross-stack strategy decision:
- Create a dated note in `/Users/georgeolaru/Developer/pixelsite/master-strategy/decisions/YYYY-MM-DD-short-title.md` using the template in `decisions/README.md`.
- Update `source-index.md` when the decision depends on a new source document, repo note, issue, or public reference.
- Update `pixelgrade-lt-stack-strategy.md` only when the decision changes the central strategy.

Keep implementation details, tests, and repo-specific plans in the repo where the work happens. Keep cross-stack product direction, positioning, monetization, and Pixelgrade.com strategy in `pixelsite/master-strategy`.

## Tooling Prerequisites

- Node: 22+
- npm: 10+
- PHP: 8.1+
- Composer: 2.2+
- WP-CLI: required for translation generation during release packaging.
- `rsync` and `zip`: required by packaging tasks.
- `dos2unix`: optional, for fixing line endings.

## Development Setup

Install dependencies and start the development build from the plugin root:

```bash
composer run dev-install
npm run dev
```

Run the unit suite with:

```bash
composer run tests-unit
```

Integration tests require a configured WordPress test environment.

## Build Process

The release pipeline is non-destructive: it installs production dependencies,
cleans duplicate runtime vendor packages, regenerates optimized autoload files,
generates translations, and creates the package without deleting lock files.

`npm run zip:wporg` is the canonical package command for WordPress.org. It
builds `../style-manager-wporg-X-X-X.zip`.

`npm run zip` is an alias for the same WordPress.org-safe package command.

Shared build sequence:

1. `npm install`
2. `composer install --prefer-dist --no-dev --no-scripts --optimize-autoloader`
3. `npm run gulp composer:delete_prefixed_vendor_libraries`
4. `composer dump-autoload --no-dev --optimize`
5. `node ./node-tasks/verify_release_autoload.js`
6. `gulp zip:wporg`

`gulp zip:wporg` runs:

1. `build:preflight`
2. `build:folder:wporg`
3. `build:fix`
4. `build:translate`
5. `build:zip:wporg`

## Release Package Guardrails

- Build against modern local tooling; the preflight task hard-fails unsupported Node, PHP, or Composer versions.
- Keep `node-tasks/verify_release_autoload.js` passing so production autoload files never reference stripped dev vendors.
- Generate and inspect the built artifact before publishing.
- Run Plugin Check against the built artifact, not only the source tree.

## Composer Autoload

Production autoload should include:

- PSR-4: `Pixelgrade\StyleManager\` => `src/`
- PSR-4: `Carbon_Fields\` => `vendor/htmlburger/carbon-fields/core`
- Classmap: `vendor_prefixed/`
- Files:
  - `src/functions.php`
  - `src/sm-functions.php`
  - `src/cloud-filter-functions.php`
  - `src/deprecated.php`
  - `vendor_prefixed/symfony/polyfill-mbstring/bootstrap.php`

Production autoload must not reference stripped dev vendors.

## WordPress.org Release

1. Create or update the GitHub issue for the release work.
2. Update `style-manager.php`, `readme.txt`, and the changelog/stable tag.
3. Build with `npm run zip:wporg`.
4. Extract or install the artifact and run Plugin Check against it.
5. Commit the cleaned build contents to WordPress.org SVN `trunk`.
6. Copy `trunk` to `tags/<version>` with `svn cp`.
7. Verify the directory API and download link:

```bash
curl -s 'https://api.wordpress.org/plugins/info/1.2/?action=plugin_information&request%5Bslug%5D=style-manager'
curl -I 'https://downloads.wordpress.org/plugin/style-manager.<VERSION>.zip'
```

## Issue & Commit Workflow

Every fix or improvement should follow the project workflow:

1. Create a GitHub issue describing the problem and root cause.
2. Assign it to the latest open milestone, or create the next appropriate release milestone if none exists.
3. Commit with `Fixes #N` in the message.
4. Push to `main` after verification passes.
