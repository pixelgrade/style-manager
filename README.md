# Style Manager - Design-system engine for compatible WordPress themes and blocks

Style Manager generates and coordinates colors, typography, spacing, and related design decisions across compatible WordPress themes and blocks.

It turns theme and block integrations into a shared design system: themes apply the system to the site, blocks adapt to it in context, and users can preview those decisions in the editor and on the front end without writing CSS.

The current reference stack is [Pixelgrade's Anima universal theme](https://github.com/pixelgrade/anima/blob/dev/inc/integrations/style-manager/style-manager.php), [Nova Blocks](https://wordpress.org/plugins/nova-blocks/), and the Pixelgrade LT themes. Together, they show how Style Manager can coordinate palettes, typography, spacing, theme tweaks, motion, and contextual block styles from one styling layer.

Style Manager is not a universal page builder. It is most useful when the active theme and blocks are built to read its design decisions.

**Made with care by Pixelgrade**

## Known compatible themes and blocks

- [Anima](https://github.com/pixelgrade/anima) _by Pixelgrade_
- [Nova Blocks](https://wordpress.org/plugins/nova-blocks/) _by Pixelgrade_
- [Mies LT](https://pixelgrade.com/themes/portfolio/mies-lt/) _by Pixelgrade_
- [Julia LT](https://pixelgrade.com/themes/blogging/julia-lt/) _by Pixelgrade_
- [Rosa LT](https://pixelgrade.com/themes/restaurants/rosa-lt/) _by Pixelgrade_
- [Felt LT](https://pixelgrade.com/themes/blogging/felt-lt/) _by Pixelgrade_

## How to use it?

First you need to install and activate the stable version from [WordPress.org](https://wordpress.org/plugins/style-manager/), then use a theme or block library that integrates with Style Manager.

For the Site Editor experience, open `Appearance -> Editor` and use the Style Manager controls exposed by your active theme.

For themes that still expose the Customizer workflow, go to `Appearance -> Customize` and use the Style Manager section provided by the active theme.

## WordPress Developer Love

We know developers are a special kind of breed and that they need special kinds of treats. That is why we have introduced options dedicated to them.

### Reset Buttons

In the plugin's settings page (*WordPress Dashboard > Settings > Style Manager*) you will find a checkbox called **Enable Reset Buttons** that once activated will show a new Customizer section called **Style Manager Toolbox** and also introduce buttons in each section or panel configured via the plugin.

All these buttons will reset the options to their default values.

### Continuous Default Values

If you want to go even further, there is a nuclear option. Simply define the `STYLE_MANAGER_DEV_FORCE_DEFAULTS` constant to `true` and everywhere the default value will be used. You can play with the values in the Customizer and the live preview will work, but no value gets saved in the database.

Add this in your `wp-config.php` file:
```php
define( 'STYLE_MANAGER_DEV_FORCE_DEFAULTS', true);
```

## Developing Style Manager

Before you can get developing, you need to have `node` and `composer` (v2) installed globally. Google is your best friend to get you to the resource to set things up.

Once you clone the Git repo, to get started open a shell/terminal in the cloned directory and run these from the command line (in this order):

```shell
composer run dev-install

npm run dev
```

This will set up all node_modules, composer packages, and compile the scripts and styles with watchers waiting for your next move.

## Local Environment Setup Pointers

You need to be careful since we **require** certain **node versions (v22) and PHP versions (v8.1).**

For ease of development, it is best to use `nvm` (https://github.com/nvm-sh/nvm) for node version management and automatic node version switching on shell navigation. For the `zsh` shell the easiest way is to use [oh-my-zsh](https://github.com/ohmyzsh/ohmyzsh) with the `nvm` [plugin](https://github.com/ohmyzsh/ohmyzsh/tree/master/plugins/nvm) activated.

We use the following oh-my-zsh plugins: `plugins=(composer git nvm npm)` configured in `~/.zshrc`. For automatic node version switching, place this line in `~/.zshrc` just below the plugins line: `NVM_AUTOLOAD=1`. Now whenever you enter a directory through the shell, if it finds a `.nvmrc` file, it will switch to the specified node version.

### Easy experimentation with design assets

To avoid the hassle and bustle of editing design assets on the cloud and then refreshing your local WordPress installation (ad infinitum), you can [**use this must-use plugin**](https://github.com/pixelgrade/style-manager/files/8737998/style-manager-local-dev-mu-plugin.zip) that contains the logic to **automatically load and inject locally-defined, JSON-based design assets.**

Simply download the zip and extract it in your local WordPress installation's `wp-content/mu-plugins` directory (the `style-manager-local-dev.php` and `style-manager-local-dev` need to be directly in the `mu-plugins` directory, not in a subdirectory, since WordPress will not recognize it as mu-plugin otherwise). 

Next go to the `style-manager-local-dev` directory and remove/add/edit anything you want. The starting directories and files are just there to help you get started. You don't need to keep all of them.

Please note that there are **further instructions** in the mu-plugin's code.

## Building Release Zips

Style Manager has two package targets while older installs are still migrating away from WUpdates:

- `npm run zip:wporg` builds the WordPress.org package and strips the commercial updater files plus the `Update URI: false` header.
- `npm run zip` builds the legacy WUpdates package and should only be used for the migration handoff while that channel still exists.

The build is non-destructive. After you update the version and changelog, run:

```shell
npm install
npm run zip:wporg
```

Before shipping to WordPress.org SVN, run Plugin Check against the built artifact and verify the generated zip installs cleanly.

## Running Unit Tests

To run the PHPUnit tests, in the root directory of the plugin, run something like:

```shell
./vendor/bin/phpunit --testsuite=Unit --colors=always
```
or
```shell
composer run tests
```

Bear in mind that there are **simple unit tests** (hence the `--testsuite=Unit` parameter) that are very fast to run, and there are **integration tests** (`--testsuite=Integration`) that need to load the entire WordPress codebase, recreate the db, etc. Choose which ones you want to run depending on what you are after.

**Important:** Before you can run the tests, you need to create a `.env` file in `tests/phpunit/` with the necessary data. You can copy the already existing `.env.example` file. Further instructions are in the `.env.example` file.

## License

GPLv2 and later, of course!

## Thanks!

This plugin also includes the following third-party libraries:

* Select 2 - https://select2.github.io/
* Ace Editor - https://ace.c9.io/
* CarbonFields - https://carbonfields.net/
* React jQuery Plugin - https://github.com/natedavisolds/jquery-react

2020-2022 © Pixelgrade.
