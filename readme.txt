=== Style Manager ===
Contributors: pixelgrade, vlad.olaru, babbardel, razvanonofrei, gorby31
Tags: design, customizer, fonts, colors, color palettes
Requires at least: 6.5
Tested up to: 7.0
Stable tag: 2.2.13
Requires PHP: 8.1
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

== Privacy & External Services ==

Style Manager relies on one external service to deliver its design assets.

**Pixelgrade Cloud (cloud.pixelgrade.com)**

To provide the color palettes, font palettes, and curated design configurations
that power the Customizer, the plugin fetches design assets from Pixelgrade Cloud.
This request is made from the WordPress admin (not from your site's frontend) and
the response is cached locally.

When fetching design assets, the plugin sends: your site URL, whether the site
uses SSL, your WordPress version, the Style Manager version, and your active
theme's slug, name, URI, version, and text domain. No personal data about your
site's visitors is sent or collected, and the plugin does not load any tracking
or analytics scripts.

* Service: Pixelgrade Cloud
* Provider: Pixelgrade — https://pixelgrade.com
* Privacy Policy: https://pixelgrade.com/privacy/
* Web fonts referenced by font palettes are served by Google Fonts
  (https://fonts.googleapis.com / https://fonts.gstatic.com) — see Google's
  Privacy Policy at https://policies.google.com/privacy.

== Changelog ==

= 2.3.0-beta1 =
* New: the full Style Manager experience runs natively inside the Site Editor — colors, typography, spacing, tweaks, and motion, with live preview into the editor canvas and saving through the editor's own Save flow.
* New: section previews — the color system board, the type specimen, a new spacing & rhythm board, and a guided live-site flow for motion with per-behavior replays.
* New: a Live Site preview that shows unsaved changes on the real frontend without leaving the editor (also available from the editor's View menu).
* Fix: publishing settings can no longer overwrite sibling options of the theme's options root with filtered values (protects sites running read filters such as translation plugins).
* Fix: element colorize options (Coloration Level) now live-preview inside the editor canvas and the Live Site preview.
* Fix: page transitions play inside the Live Site preview (with the latest Anima).
* Performance: ~100 KB trimmed from the editor payload; preview boards render on demand.
* Maintenance: PHPUnit 10, WordPress 6.9 stubs, refreshed build toolchain; the PHP test suite runs again.

= 2.2.13 =
* Security: constrain and escape the dark-mode appearance attribute printed on the site's `<html>` tag.
* Isolate the WUpdates self-update mechanism so it can be excluded from WordPress.org-targeted builds.

= 2.2.12 =
* Apply connected fields presets when switching font palettes that define a palette-specific preset.
* Preserve nested font palette metadata when cloud palettes are combined with local partial overrides.

= 2.2.11 =
* Add Voice Tuner controls to sort font palettes by personality fit.
* Restore original palette ordering when the tuner returns to balanced.
* Preserve Voice Tuner scoring on current installs where local palette overrides omit personality metadata.
* Refresh font palette cards with richer live preview typography.

= 2.2.10 =
* Consolidated release from beta streams.
* WordPress 7.0 compatibility updates for the iframed block editor and safer Customizer cross-frame behavior.
* Safari fix for multi-variant font selection in Customizer (fallback when `requestIdleCallback` is unavailable).
* Improved dark mode propagation into the block editor iframe.
* Modernized build and CI pipeline (Node 22, PHP 8.1+, deterministic zip validation).

= 2.2.9 =
* Ensure full compatibility with PHP 8.1, 8.2, 8.3, 8.4, and 8.5 (zero deprecation notices)
* Update symfony polyfill dependency version constraints for PHP 8.2+ compatibility
* Add PHP 8.1 downgrade set to Rector build pipeline

= 2.2.8 =
* 2026-02-09
* Upgrade Carbon Fields library to version 3.6.9 for WordPress 6.2+ compatibility (React 18).
* Fix Settings page not rendering fields on WordPress 6.2+.
* Fix PHP 8.2 ReturnTypeWillChange deprecation notices from Carbon Fields.
* Security: sanitize $_SERVER['REQUEST_URI'] in exception message.
* Security: replace $_SERVER['PHP_SELF'] with global $pagenow.
* Security: add capability check to AJAX migration handler.
* Security: cast sm_site_color_variation to integer in JS output.
* Security: escape RadioImage control colors, labels, and data attributes.
* Tested with WordPress 6.9.1.

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
