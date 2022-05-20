# Style Manager - Auto-magical system to style your WordPress site

With [Style Manager](https://github.com/pixelgrade/style-manager), developers can easily create **advanced theme-specific options** inside the WordPress Customizer. Using those options, a user can make presentational changes without having to know or edit the theme code.

This plugin is **primarily intended** to be used together with [Pixelgrade themes](https://wordpress.org/themes/author/pixelgrade/). So the best way to get acquainted with it's capabilities is to study the way [one of Pixelgrade's themes](https://github.com/pixelgrade/rosa2-lite/tree/master/inc/integrations/customify) integrates with it.

**Made with care by Pixelgrade**

## How to use it?

First you need to install and activate the stable version. This will always be on [wordpress.org](https://wordpress.org/plugins/style-manager/)

Now go to ‘Appearance -> Customize’ menu and have fun with the new fields provided by your active theme.

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

You need to be careful since we **require** certain **node versions (v14) and PHP versions (v7.4).**

For ease of development, it is best to use `nvm` (https://github.com/nvm-sh/nvm) for node version management and automatic node version switching on shell navigation. For the `zsh` shell the easiest way is to use [oh-my-zsh](https://github.com/ohmyzsh/ohmyzsh) with the `nvm` [plugin](https://github.com/ohmyzsh/ohmyzsh/tree/master/plugins/nvm) activated.

We use the following oh-my-zsh plugins: `plugins=(composer git nvm npm)` configured in `~/.zshrc`. For automatic node version switching, place this line in `~/.zshrc` just below the plugins line: `NVM_AUTOLOAD=1`. Now whenever you enter a directory through the shell, if it finds a `.nvmrc` file, it will switch to the specified node version.

### Easy experimentation with design assets

To avoid the hassle and bustle of editing design assets on the cloud and then refreshing your local WordPress installation (ad infinitum), you can [**use this must-use plugin**](https://github.com/pixelgrade/style-manager/files/8737684/style-manager-local-dev-mu-plugin.zip) that contains the logic to **automatically load and inject locally-defined, JSON-based design assets.**

Simply download the zip and extract it in your local WordPress installation's `wp-content/mu-plugins` directory (directly in that directory, not in a subdirectory, since WordPress will not recognize it as mu-plugin). 
Next go to the `style-manager-local-dev` directory and remove/add/edit anything you want. The starting directories and files are just there to help you get started. You don't need to keep all of them.
Please note that there are **further instructions** in the mu-plugin's code.

## Building The Release .zip 

Since Style Manager is intended for distribution on WordPress.org you will need to build the plugin files, transpile them to the appropriate PHP version (7.1), and generate a cleaned-up zip.

After you have updated the version, added the changelog, blessed everything, **you NEED to clone the repo in a TEMPORARY directory** since **the build process is DESTRUCTIVE!!!**

**From the newly cloned, temporary directory,** run these commands from the command line:

```shell
nmp install

npm run zip
```

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
