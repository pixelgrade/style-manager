# Tweak Board section previews — design

Status: validated (2026-06-13) · Extends `2026-06-12-section-preview-principles.md`
to the Tweak Board section.

## Goal

Give the Tweak Board the same kind of in-editor preview the Colors,
Typography and Spacing sections already have — but the Tweak Board is a
grab-bag of distinct expressive treatments, so it needs **per-group**
previews rather than one section-level one.

Ship now: **Site Frame** and **Fancy Titles** boards. Defer Collections
(see Non-goals).

## What the Tweak Board contains today

One section (`sm_tweak_board_section`) holding, in order:

- **Collections** — `sm_collection_title_position` (above/sideways),
  `sm_collection_hover_effect` (none/hive/felt/pile) — plugin-defined.
- **Fancy Titles** — `sm_decorative_titles_style` — plugin defines it as a
  radio (underline/blocky); **Anima overrides it to an Enabled toggle** and
  the underline/blocky styles are not implemented in Anima.
- **Site Frame** — `sm_site_frame_style` (none/editorial), `sm_site_frame_palette`,
  `sm_site_frame_variation` — appended by the Anima theme.
- **Post-type Colors** — `sm_contextual_entry_colors` toggle — theme-appended.

Only one injected group header exists today ("Collections", in
`SiteEditor.php`). No Tweak Board preview exists.

## Reorganized IA

Single section, four labelled groups; the two preview-bearing groups sit
directly under Collections:

```
‹ Tweak Board
─────────────────────────────────
COLLECTIONS
  Title position   (Above / Sideways)
  Hover effect     (None / Hive / Felt / Pile)

SITE FRAME              Preview ▸
  Style            (None / Editorial)
  Palette / Color grade

FANCY TITLES            Preview ▸
  Enabled          (toggle)

POST-TYPE COLORS
  Enabled          (toggle)
```

## The two boards

Both are body-mounted React overlays following the `SpacingOverlay`
pattern (`src/_js/customizer/components/spacing-overlay/`), bound to the
engine via `useCustomizeSettingCallback`, rendered in-document, zero
network in the tuning loop.

### Site Frame board (`mode: 'site-frame'`)

A schematic miniature page: top bar + left bar + right nav rail drawn as
colored divs around a content area. Colors resolve through the **real
cascade** — the board wrapper carries `.sm-palette-{id} .sm-variation-{n}`
so `--site-frame-surface: var(--sm-current-bg-color)` (and the dark-mode /
`accent` flips) paint the schematic exactly as the frontend does.

- Live-binds `sm_site_frame_palette` / `sm_site_frame_variation` → instant retint.
- Live-binds `sm_site_frame_style`: `editorial` shows the frame; `none`
  shows the page bare with a "Set Style to Editorial to frame your site" hint.
- Handles the `accent` grade keyword.
- Footer shows resolved palette name + grade (principle 3).
- Optional light/dark toggle (the frame flips colors in dark mode).

Frame proportions mirror the theme: 12px top, 48px left, 60px rail.

### Fancy Titles board (`mode: 'fancy-titles'`)

Replicates the documented auto-emphasis rules as a fixed specimen — four
centered lines in the live heading/display font, each demonstrating a rule
cluster with real `<b>`/`<i>` emphasis, plus a one-line caption per rule.
Previews the **full rule set**, not one sample (principle 1).

| Trigger | Result |
|---|---|
| UPPERCASE word / word ending `!` | bold |
| "quotes" / (parentheses) | italic |
| text before `:` | bold |
| left of `?` bold, right italic | bold + italic |

- Live-binds the `sm_decorative_titles_style` toggle: off → specimen dims
  with an "Enable to apply this to your post titles" note; on → full strength.
- Fixed illustrative lines (no regex porting; the frontend does the real
  regex in `anima/inc/title-styles.php`).

## Shared mechanism — group-level previews

The existing preview machinery is section-keyed (`SECTION_PREVIEW_MODES`,
a single header button per section). Extend it to groups:

1. **Payload** — entries in `payload.sectionGroupHeaders[sectionId]` gain an
   optional `preview: { mode, context? }`. Add Site Frame / Fancy Titles /
   Post-type Colors group headers; Site Frame + Fancy Titles carry a preview.
   Make the payload filterable (`style_manager/site_editor_section_group_headers`)
   so Anima can own its feature groups; reorder so Site Frame sits after
   Collections.
2. **`buildSectionPanel`** (`index.js` ~110-119) — when inserting the
   `sm-se-group-title` `<li>`, if it has `preview`, append a "Preview ▸"
   button wired to `setPreviewMode`/`previewModeListeners` for the open/close
   label. Factor the section-header button logic (~179-203) into a shared helper.
3. **`SiteEditorPreviewOverlays`** (`index.js` ~850-892) — two new branches:
   `'site-frame' === mode && <SiteFrameOverlay show />`,
   `'fancy-titles' === mode && <FancyTitlesOverlay show />`.
4. **Components** — `site-frame-overlay/` and `fancy-titles-overlay/` under
   `src/_js/customizer/components/`, exported from the barrel.

## Cleanups

- Fix `sm_collection_hover_effect` default from the invalid `'dropcap'` to a
  real choice (`'none'`) in `TweakBoardSection.php`.

## Non-goals (deferred)

- **Collections / Collection Hover board** — `hive`/`felt` are unimplemented
  placeholders in Anima (only `pile` exists, and it needs real Nova Blocks
  card markup). Revisit when the effect set is real. The group-preview
  mechanism is general, so Collections can opt in later with no rework.
- Implementing hive/felt effects.
- Underline/blocky decoration styles (not implemented in Anima).

## Per-section decisions (extends the principles doc table)

| Section | Token space | Preview approach |
|---|---|---|
| **Site Frame** | palette × variation → frame surface/ink (+ dark/accent flips) | **System board**: schematic frame on neutral ground, retinted live through the real `.sm-palette/.sm-variation` cascade; resolved palette/grade shown |
| **Fancy Titles** | the auto-emphasis rule set | **Specimen board**: fixed illustrative headings in the live display font, one per rule cluster, with captions; reflects the on/off toggle |

Both honor principle 6's converse: neither effect is *behavioral* (no time /
trigger), so a board — not a live-site flow — is the right surface.

## Files touched

- JS: `src/_js/site-editor/index.js`,
  `src/_js/customizer/components/site-frame-overlay/{index.js,style.scss}`,
  `src/_js/customizer/components/fancy-titles-overlay/{index.js,style.scss}`,
  `src/_js/customizer/components/index.js`.
- PHP (plugin): `src/Screen/SiteEditor.php` (group-header payload + filter),
  `src/Customize/TweakBoardSection.php` (dropcap default).
- PHP (theme, Anima): register Site Frame / Fancy Titles group headers +
  reorder, if group headers are made theme-owned.

## Verification

Build the JS, open Site Editor → Style Manager → Tweak Board; confirm the
four group labels and the two "Preview ▸" buttons; open each board; change
palette/variation/style and the Fancy Titles toggle and confirm live
updates. **Screenshot the actual board view for each** (per the standing
visual-verification requirement — DOM probes alone are insufficient).
