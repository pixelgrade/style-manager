=== Style Manager - Auto-magical system to style your entire WordPress site ===
Contributors: pixelgrade, vlad.olaru, babbardel, razvanonofrei, gorby31
Tags: design, customizer, fonts, colors, gutenberg, font palettes, color palettes, global styles
Requires at least: 5.5.0
Tested up to: 6.0
Stable tag: 2.2.7
Requires PHP: 7.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Auto-magical system to style your WordPress site.

== Description ==

Style Manager provides you with the tools to get your site's style to match your personality while retaining overall cohesion and balance.

Design is at the forefront of everything that Style Manager provides. We want you to feel at ease when customizing your blog, feeling confident that the end results will match the quality of your content.

This is why we are actively integrating Style Manager with free themes on WordPress.org and collaborating with theme authors to take advantage and enhance their user's customizing experience.

**Made with love by [Pixelgrade](https://pixelgrade.com)**

== Installation ==

Installing "Style Manager" can be done either by searching for "Style Manager" via the "Plugins > Add New" screen in your WordPress dashboard, or by using the following steps:

1. Download the plugin via WordPress.org.
2. Upload the ZIP file through the 'Plugins > Add New > Upload' screen in your WordPress dashboard.
3. Activate the plugin through the 'Plugins' menu in WordPress.
4. Head over to Appearance -> Customize and access the Style Manager section.

== Issues ==

If you identify any errors or have an idea for improving the plugin, please open an [issue](https://github.com/pixelgrade/style-manager/issues?stage=open). We're more than excited to see what the community thinks of this project, and we welcome your input!

If GitHub is not your thing, but you are passionate about Style Manager and want to help us make it better, don't hesitate to [reach us](https://pixelgrade.com/contact/).

== Frequently Asked Questions ==

= Is there a way to reset the Customizer options to their default value? =
Reset buttons are available for all the options or for individual sections or panels.
This is mostly a development tool, thus it is disabled by default.
To enable them simply go to Dashboard -> Appearance -> Style Manager and check "Enable Reset Buttons"

== Credits ==

* [Select2](https://select2.github.io) JavaScript library - License: MIT
* [Ace Editor](https://ace.c9.io/) JavaScript editor - License: BSD
* [jQuery React](https://github.com/natedavisolds/jquery-react) JavaScript jQuery plugin - License: MIT
* [Web Font Loader](https://github.com/typekit/webfontloader) JavaScript library - License: Apache 2.0
* [Fuse.js](http://fusejs.io) Lightweight fuzzy-search JavaScript library - License: Apache 2.0
* [CarbonFields](https://carbonfields.net/) WordPress Custom Fields Library - License: GPLv2
* Default [image](https://unsplash.com/photos/OgM4RKdr2kY) for Style Manager Color Palette control - License: [Unsplash](https://unsplash.com/license)

== Changelog ==

= 2.2.7 =
* 2022-06-16
* Improvements to design assets handling.
* Improvements to fonts handling on the frontend of your site.
* Fix for fonts controls styling.
* Fix site color variation controls.

= 2.2.6 =
* 2022-06-07
* Fix for migrating parent theme theme_mods to the child theme in order to keep your customizations.

= 2.2.5 =
* 2022-06-03
* Fix edge-case bug when the Customizer preview would not update with new fonts.
* Styling fixes for Customizer controls.
* Fix cache invalidation after Pixelgrade Care Starter Content import.
* Test with the latest WordPress version (6.0).

= 2.2.4 =
* 2022-05-06
* Fix inconsistencies in the block editor.
* Test with the latest WordPress version.

= 2.2.3 =
* 2022-04-20
* Fix CSS output for legacy color palettes

= 2.2.2 =
* 2022-04-19
* Ensure compatibility with PHP 8.0

= 2.2.1 =
* 2022-04-19
* Improve backwards compatibility
* Bug fixes and style improvements

= 2.2.0 =
* 2022-04-15
* Improve Color Palettes module
* Improve Fonts Palettes module
* Improve integration with the block editor and the full-site editor
* Lots of fixes and performance improvements
* Ensure compatibility with Nova Blocks 2.0+
* Ensure WordPress 5.9+ compatibility
* Update Carbon Fields library to version 3.3+

= 2.1.1 =
* 2021-12-14
* Fixes a CSS selector specificity issue introduced with 2.1.0

= 2.1.0 =
* 2021-12-06
* Introduces data migration when switching data store location from plugin settings
* Fixes bug in Color Palettes
* Invalidate caches after demo data import
* Increase minimum PHP version to 7.1 and WordPress version to 5.5.0
* Tested with the latest WordPress version (5.8.2)

= 2.0.7 =
* 2021-08-16
* Expose palettes configuration to frontend and block editor through the styleManager global object

= 2.0.6 =
* 2021-08-05
* Fixed issues with Customizer menus section styling.

= 2.0.5 =
* 2021-08-02
* Fixed a fatal PHP error on activation on certain PHP versions.
* Fixed issues with Customizer preview links
* Fixed feedback form

= 2.0.4 =
* 2021-07-21
* Tested with the latest WordPress version (5.8).
* Fix for custom fonts with custom source URLs.

= 2.0.3 =
* 2021-07-20
* Fixes scripts enqueue errors.

= 2.0.2 =
* 2021-07-16
* Minor fix for range fields to properly display their actual value

= 2.0.1 =
* 2021-07-14
* Minor fix for font controls.

= 2.0 =
* 2021-07-12
* Complete rewrite and overhaul of the styling logic. Better in every way.

= 1.0 =
* 2018-07-18
* Initial release
