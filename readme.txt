=== Style Manager ===
Contributors: pixelgrade, vlad.olaru, babbardel, razvanonofrei, gorby31
Tags: design, site editor, typography, colors, color palettes
Requires at least: 6.5
Tested up to: 7.0
Stable tag: 2.3.0
Requires PHP: 8.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Generate and coordinate colors, typography, and spacing across compatible WordPress themes and blocks.

== Description ==

Style Manager generates and coordinates colors, typography, and spacing for compatible WordPress themes and blocks.

Instead of tuning isolated colors, font sizes, and spacing values by hand, you work with connected design decisions that can flow through the editor, the active theme, compatible blocks, and the front end.

Themes that support Style Manager apply the system across site templates and theme details. Compatible blocks can adapt to the same design decisions in context, so sections and blocks feel like part of one site rather than separate styling work.

In Style Manager 2.3, the main styling flow is available in the Site Editor, with visual preview boards, editor-canvas previews, and a Live Site preview for reviewing unsaved changes before publishing.

= Free compatible stack =

You can try Style Manager with this free Pixelgrade stack:

* [Anima](https://github.com/pixelgrade/anima) provides the theme-level integration.
* [Nova Blocks](https://wordpress.org/plugins/nova-blocks/) provides compatible blocks that can respond to the same design system.

Style Manager also integrates with Pixelgrade LT themes, but premium themes are not required to try the free Anima and Nova Blocks path.

= What you can shape =

* Color palettes with generated roles for compatible themes and blocks.
* Typography choices for headings, body copy, and details.
* Spacing and rhythm controls that keep layouts consistent.
* Theme-supported tweaks and motion settings.
* Live editor previews before saving.
* Context-aware styling for blocks and sections that support Style Manager.

= Compatibility =

Style Manager is not a universal page builder and does not make every theme or block support every control.

The full experience depends on your active theme and blocks being built to read Style Manager's design decisions. If a theme or block does not support a specific Style Manager feature, the related controls may be hidden or may not affect that theme or block's output.

== Installation ==

Install Style Manager by searching for "Style Manager" in Plugins > Add New, or by using these steps:

1. Download the plugin via WordPress.org.
2. Upload the ZIP file through Plugins > Add New > Upload Plugin.
3. Activate the plugin from the Plugins screen.
4. Use a theme or block library that supports Style Manager. For a free setup, try Anima with Nova Blocks.
5. Open Appearance > Editor for the Site Editor experience.
6. On themes that still expose the Customizer workflow, open Appearance > Customize and look for Style Manager controls.

== Issues ==

If you identify any errors or have an idea for improving the plugin, please open an [issue](https://github.com/pixelgrade/style-manager/issues?stage=open). We're more than excited to see what the community thinks of this project, and we welcome your input!

If GitHub is not your thing, but you are passionate about Style Manager and want to help us make it better, don't hesitate to [reach us](https://pixelgrade.com/contact/).

== Frequently Asked Questions ==

= Which themes and blocks are supported? =

Style Manager is built for compatible themes and blocks. Some controls appear only when the active theme or blocks support them.

= What free setup can I use to try it? =

Use Anima for theme-level integration and Nova Blocks for compatible blocks. Both are free.

= Where do I find the Style Manager controls? =

In Style Manager 2.3, compatible themes can expose the main styling flow in Appearance > Editor. Themes that still use the Customizer workflow can expose Style Manager controls in Appearance > Customize.

= Do I need to write CSS? =

No. Style Manager is designed around visual controls and live previews. Theme and block builders can still consume the generated styles in custom integrations.

= Does Style Manager require a Pixelgrade account? =

No Pixelgrade account is required for the core styling workflow. Some design assets are loaded from Pixelgrade Cloud as described in the Privacy & External Services section.

= What is new in Style Manager 2.3? =

Style Manager 2.3 brings the styling experience into the Site Editor, including preview boards, editor-canvas previews, and a Live Site preview flow for unsaved changes.

== Screenshots ==

1. Control your site's style from the Site Editor and preview changes before saving.
2. Choose a palette and let compatible themes apply coherent color roles across the site.
3. Shape the site's typography from a focused set of design decisions.
4. Tune layout feel, rhythm, and supported theme refinements from one styling flow.
5. Review unsaved design changes on the front end before publishing them.
6. Context-aware palettes help compatible blocks adapt without manual per-block styling.

== Credits ==

* [Select2](https://select2.github.io) JavaScript library - License: MIT
* [Ace Editor](https://ace.c9.io/) JavaScript editor - License: BSD
* [jQuery React](https://github.com/natedavisolds/jquery-react) JavaScript jQuery plugin - License: MIT
* [Web Font Loader](https://github.com/typekit/webfontloader) JavaScript library - License: Apache 2.0
* [Fuse.js](http://fusejs.io) Lightweight fuzzy-search JavaScript library - License: Apache 2.0
* [CarbonFields](https://carbonfields.net/) WordPress Custom Fields Library - License: GPLv2
* Default [image](https://unsplash.com/photos/OgM4RKdr2kY) for Style Manager Color Palette control - License: [Unsplash](https://unsplash.com/license)

== Privacy & External Services ==

Style Manager uses external services to deliver some design assets.

**Pixelgrade Cloud (cloud.pixelgrade.com)**

To provide color palettes, font palettes, and curated design configurations, the plugin can fetch design assets from Pixelgrade Cloud. This request is made from the WordPress admin, not from your site's frontend, and the response is cached locally.

When fetching design assets, the plugin sends: your site URL, whether the site uses SSL, your WordPress version, the Style Manager version, and your active theme's slug, name, URI, version, and text domain. No personal data about your site's visitors is sent or collected, and the plugin does not load tracking or analytics scripts.

* Service: Pixelgrade Cloud
* Provider: Pixelgrade - https://pixelgrade.com
* Privacy Policy: https://pixelgrade.com/privacy/
* Web fonts referenced by font palettes are served by Google Fonts (https://fonts.googleapis.com / https://fonts.gstatic.com). See Google's Privacy Policy at https://policies.google.com/privacy.

== Changelog ==

= 2.3.0 =
* New: the full Style Manager experience runs natively inside the Site Editor: colors, typography, spacing, tweaks, and motion, with live preview into the editor canvas and saving through the editor's own Save flow.
* New: section previews: the color system board, the type specimen, a new spacing and rhythm board, and a guided live-site flow for motion with per-behavior replays.
* New: a Live Site preview that shows unsaved changes on the real frontend without leaving the editor.
* Fix: publishing settings can no longer overwrite sibling options of the theme's options root with filtered values.
* Fix: element colorize options (Coloration Level) now live-preview inside the editor canvas and the Live Site preview.
* Fix: page transitions play inside the Live Site preview when the active theme supports them.
* Performance: the editor payload is smaller and preview boards render on demand.
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
