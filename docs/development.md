# Development

This page collects contributor-oriented notes that do not belong in the first-user README.

## Local Setup

Style Manager development requires:

- Node 22+
- npm 10+
- PHP 8.1+
- Composer 2.2+

After cloning the repository, install dependencies and start the development build:

```shell
composer run dev-install
npm run dev
```

This installs the PHP and JavaScript dependencies and starts the script/style watchers.

## Development Options

### Reset Buttons

In `Settings -> Style Manager`, enable `Enable Reset Buttons` to show reset buttons in configured Customizer sections and panels.

These buttons reset options to their default values and are intended mainly for development and QA workflows.

### Continuous Default Values

Define `STYLE_MANAGER_DEV_FORCE_DEFAULTS` as `true` to force defaults everywhere. The Customizer preview can still react to value changes, but values are not saved to the database.

```php
define( 'STYLE_MANAGER_DEV_FORCE_DEFAULTS', true );
```

## Local Design Assets

For local experimentation with JSON-based design assets, use the Style Manager local development must-use plugin:

https://github.com/pixelgrade/style-manager/files/8737998/style-manager-local-dev-mu-plugin.zip

Extract it into `wp-content/mu-plugins` so both `style-manager-local-dev.php` and the `style-manager-local-dev` directory sit directly inside `mu-plugins`.

Then edit the JSON assets inside the `style-manager-local-dev` directory. The must-use plugin contains additional instructions.

## Building Release Zips

Style Manager has two package targets while older installs are still migrating away from WUpdates:

- `npm run zip:wporg` builds the WordPress.org package and strips the commercial updater files plus the `Update URI: false` header.
- `npm run zip` builds the legacy WUpdates package and should only be used for the migration handoff while that channel still exists.

The build is non-destructive. After updating the version and changelog, run:

```shell
npm install
npm run zip:wporg
```

Before shipping to WordPress.org SVN, run Plugin Check against the built artifact and verify the generated zip installs cleanly.

## Running Unit Tests

Run the PHPUnit unit suite from the plugin root:

```shell
./vendor/bin/phpunit --testsuite=Unit --colors=always
```

or:

```shell
composer run tests
```

Unit tests are fast. Integration tests load the WordPress test environment and need database configuration.

Before running tests, create `tests/phpunit/.env` from `tests/phpunit/.env.example` and fill in the required values.
