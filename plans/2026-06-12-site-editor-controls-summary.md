# Style Manager in the Site Editor — work summary

Branch: `feature/site-editor-controls` (26 commits, 2026-06-11 → 2026-06-12)
Companion plan: `plans/2026-06-11-site-editor-controls.md`
Verified throughout on `style-manager.local` (WP 7.0, Anima block theme, Mies LT cloud config).

## What it delivers

The full Style Manager Customizer experience runs natively inside the WordPress
Site Editor — same engine, same persistence semantics, re-skinned to core's
design language. The Customizer path is untouched and still works.

Entry points: the Style Manager (paintbrush) sidebar in the editor;
the Site Editor Styles route handoff card; deep links
(`site-editor.php?canvas=edit&sm-sidebar=1&sm-section=<id>`).

## Architecture ("headless Customizer")

- **PHP `Provider\HeadlessCustomizer`** boots a `WP_Customize_Manager` outside
  customize.php (core's `_wp_customize_publish_changeset()` pattern): renders
  the original control markup server-side, exposes settings data
  (+ connected_fields), re-evaluates `active_callback`s with unsaved values
  previewed, and saves through a **published changeset** — identical
  sanitization/persistence to the Customizer's Publish.
- **JS `src/_js/site-editor/`**: a standalone `wp.customize`-compatible store
  (callable Values, deep-equal `set()`, element↔setting links) lets the
  original engine modules run unchanged — connected fields, chroma palette
  builder, font fields, font palettes, folding, presets.
- **REST** (`style_manager/v1/site-editor/*`): settings entity (GET/PUT),
  active-states, preview-changeset, css.

## Major features (commit order)

1. `2af186c` — Sidebar with all 8 SM sections / 78 controls; live preview
   into the canvas iframe; drill-down navigation (Nova Blocks pattern).
2. `e2dc800` — WebFont loaded in the editor (font palette previews; publish
   crash); publish only values that genuinely differ (no-value spellings
   false/null/NaN equivalent).
3. `7100655` — **Native Save**: SM settings are a core-data entity
   (`pixelgrade/style-manager`/`settings`); the editor's Save button +
   multi-entity save panel handle them ("Style Manager → Design system
   settings"). Control visibility via server-evaluated active-states
   (Site Frame palette/grade, transition symbol).
4. `aeb911a` — Live preview for PHP-rendered options: allowlisted body-class
   sync onto the canvas (`u-collection-*`), and a Live Site tab previewing
   unsaved values through a draft changeset.
5. `0b3a05e` — **Customizer-grade Live site preview**: the iframe boots as a
   real customize-preview (changeset uuid + messenger channel); postMessage
   settings apply instantly with zero reloads; refresh-transport options
   reload with scroll preserved; in-preview navigation works.
6. `5763299`, `9bcffcc` — Editor-chrome switcher (ToggleGroupControl);
   controls re-skinned native: RangeControl, ToggleControl,
   ToggleGroupControl/RadioControl, SelectControl (originals stay hidden as
   the engine's source of truth). sm_radio pills, font palettes, palette
   builder, font fields kept custom by design.
7. `48cbb44`–`82219dc`, `4db8f89` — Core typography scale (13/12/11px),
   full-bleed white surfaces, white section pages, no hairline borders,
   normalized 16px gutters.
8. `51e1143` — **Theme Options dissolved into parent tabs**: Color System =
   Palette | Usage | Fine-tune; Typography = Palettes | Fine-tune. Shortcuts
   and deep links retarget to tabs; back-stack quirk fixed.
9. `52013e4` — Menu rows as white cards with the Customizer brand icons
   (color wheel, fonts badge, spacing gradient, tweak grid, motion stripes).
10. `e2ce67b`, `48597b3` — **Reset via core's Query Loop pattern**: per-section
    3-dot menu (vertical dots, left popover) with per-field Reset/✓ and Reset
    all; color row context menu restyled to core dropdown visuals.
11. `03942e2`, `8559af6` — sm_radio pills in core ToggleGroupControl chrome,
    keeping the sliding/windowed options + blue coloration; active = white
    text + inset ring; no inter-option borders.
12. `2082277`, `e4ee811` — Voice tuner rebuilt as a "Tune by voice" filter
    panel attached to the font palette list, with a "sorted by voice fit"
    hint + inline reset (accordion hidden in the editor; engine untouched).
13. `898e966` — Tweak Board restructure: Collections group header; intro
    cards merged into their toggles (one core-style row — Motion's enables
    auto-benefit); SE copy overrides for the collection controls.

## Key extension points (filterable)

- `style_manager/site_editor_section_ids` — exposed sections
- `style_manager/site_editor_section_tabs` — parent/child tab map
- `style_manager/site_editor_control_dependencies` — toggle-driven visibility
- `style_manager/site_editor_section_group_headers` — injected group headers
- `style_manager/site_editor_preview_body_class_prefixes` — canvas class sync

## Known notes / follow-ups

- **Anima repo**: source copy edits (Tweak Board labels/descriptions, "Enable
  X" toggle labels, jargon) — handoff prompt delivered 2026-06-12; once
  landed, remove `getCopyOverride()` in `site-editor/native-controls.js`.
  Longer-term: retire Anima's Customizer-handoff Styles script and its
  customizer-motion-controls JS (both superseded by plugin-side equivalents).
- One-off minified React #185 in two early sessions (pre deep-equal fix);
  not reproduced since.
- `sm_advanced_palette_output` recompute drift vs DB exists in the stock
  Customizer too (one phantom dirty; harmless, filtered from saves).
- DB values swept into saves during testing sessions: font palette `gnvw8d`
  (Poppins), Coloration Level `0` (Low), `sm_voice_formality` `high`
  (Formal), Collections title position `sideways`, hover `pile` — all
  saved state; revisit if unintended.
- Process rule (per George): every visual change gets a screenshot of the
  actual affected view before "done" — DOM probes alone repeatedly missed
  real breakage.
