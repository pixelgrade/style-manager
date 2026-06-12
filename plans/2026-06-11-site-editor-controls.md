# Style Manager controls in the Site Editor

Branch: `feature/site-editor-controls`
Date: 2026-06-11

## Goal

Surface the Style Manager Customizer controls inside the WordPress Site Editor
(Anima is a block theme on WP 7.0), with 1:1 functional parity, without
removing the Customizer path.

## Architecture: "headless Customizer"

Maximum parity per unit of code: reuse the original PHP control rendering and
the original JS engine, replacing only the Customizer chrome.

### PHP

1. `Provider\HeadlessCustomizer` — boots a `WP_Customize_Manager` outside
   `customize.php` (same pattern as core `_wp_customize_publish_changeset()`):
   instantiate, `register_controls()`, `do_action( 'customize_register' )`.
   Provides:
   - `get_settings_data()` — per-setting `{ value, transport, type }` +
     SM `connected_fields` (mirrors `customize_pane_settings_additional_data`).
   - `get_sections_markup()` — SM panels/sections meta + each control's
     `maybe_render()` HTML (same markup as the Customizer sidebar).
   - `save( $values )` — `save_changeset_post([ 'status' => 'publish', 'data' => ... ])`
     → identical sanitization/persistence semantics as a Customizer publish.
2. `Screen\SiteEditor` — on the `site-editor` screen: enqueues the new
   `site-editor` bundle + existing customizer CSS + fonts/webfont assets,
   localizes `styleManager` (same shape as Customizer screen) +
   `styleManagerSiteEditor` (markup, settings data, REST info).
3. REST: `style_manager/v1/site-editor/save` (POST values map) and
   `style_manager/v1/site-editor/css` (GET fresh editor CSS after save).

### JS (`src/_js/site-editor/`)

1. `customize-api.js` — standalone `wp.customize`-compatible shim: Value store
   with deferred `api( id, cb )`, `bind/trigger('ready'|'saved')`,
   `api.settings`, element↔setting linking via `data-customize-setting-link`
   (port of `wp.customize.Element` essentials), section/panel/control
   registries with no-op focus/expand, previewer/previewedDevice stubs.
2. `index.js` — `registerPlugin` + `PluginSidebar` (Site Editor). Renders the
   server-rendered SM sections in an accordion, then boots the original
   engine: `bindConnectedFields`, `handleFoldingFields`, `handleRangeFields`,
   `handleColorSelectFields`, `handleTabs`, `handlePresets`, select2,
   palette builder (React `Builder`), `initializeFonts`,
   `initializeFontPalettes`. Save button → REST.
3. `preview.js` — live preview into the editor canvas iframe: per-setting
   `<style>` tags regenerated with the same `getSettingCSS` util; JS
   css-callbacks (`sm_advanced_palette_output_cb`, `sm_site_color_variation_cb`,
   `sm_color_select_*`) reimplemented on `window`; webfont loading into the
   iframe; dark mode class sync.

### Scope

Sections: everything in `style_manager_panel` + SM-owned relocated sections
(`sm_*` section IDs: color usage, fine-tune color/font palettes, spacing,
tweak board, dark mode). Theme options sections stay in the Customizer.
Filter: `style_manager/site_editor_section_ids`.

Out of scope (Customizer chrome, not controls): reset buttons toolbox,
controls search, feedback modal, device preview tabs, color palettes
preview overlay (hover compare).

## UI decisions (landed during implementation)

- **Drill-down navigation** following the Nova Blocks inspector pattern
  (grouped headings + rounded rows opening full-panel section pages with a
  back header) instead of accordions — matches both the Customizer's
  slide-in-section model and Global Styles navigation.
- **Sidebar width**: standard Page/Block panel width (no override).
- **Preview tabs** (Live site / Typography / Colors): tab bar relocated into
  the editor top bar (right of the document bar); overlays cover the canvas
  region. Mounted on document.body — the editor header/canvas containers
  clip fixed descendants.
- **Styles route** (`?p=/styles`): the Anima handoff card is re-localized by
  the plugin to point at the in-editor Style Manager (deep links via
  `sm-sidebar=1&sm-section=<id>` query params).
- Do NOT put the `accordion-section-content` class on control containers
  outside the Customizer — wp-admin common.css accordion styles hide it.

## Verification (done on style-manager.local, Mies LT cloud config)

- All 8 SM sections render with the full 78-control inventory.
- Live preview into the canvas iframe: palette select (chroma engine),
  variation, dark mode, font palette fan-out (masters → connected
  `mies-lt_options[*]` fields), range edits — all verified, with restore.
- Save: REST → published changeset → frontend reflects values (verified by
  publishing variation 2 and restoring 1 through the UI).
- Folding (`show_if`) visibility matches the Customizer per-section.
- Customizer itself regression-checked: loads, all SM sections work,
  values intact; the lone `sm_advanced_palette_output` dirty-on-load quirk
  exists identically in stock.

## Known notes

- A one-off minified React #185 (max update depth) appeared in two early
  sessions before the deep-equal Value.set fix; not reproduced since.
- The "Fine-tune the type system" shortcut renders after Font Sizing (the
  code's anchor); the Customizer's own post-render reshuffle sometimes
  shows it above — quirk not replicated.
- `style_manager_site_color_variation_cb()` lost its strict string type on
  `$value` — saved options can legitimately hold ints when set via JS.
