# Contextual Colors preview — design

Status: validated (2026-06-13) · Extends `2026-06-12-section-preview-principles.md`
to the (premium) Contextual Colors feature.

## The feature

`sm_contextual_entry_colors` ("Custom Post Type Colors") gives **each piece of
content — post, page, project, product** — its own colour, hand-picked or
**auto-extracted from its cover image** (Tonesque dominant colour). That single
colour is expanded into a full **12-grade, contrast-checked palette**
(`sm-palette-contextual-post`) and tints that item's contextual accents:

- the **collection-card hover border** (`--anima-project-color`, falls back to
  `--sm-current-accent-color`),
- the **reading-progress bar** on the single view,
- the **page-transition sweep** to the next item.

Set per-item (post meta `_project_color`); the Site Editor only holds the global
on/off. So the board **demonstrates the concept** with illustrative entries — it
cannot read real per-post colours.

## Copy

- **Section name:** "Contextual Colors" (was "Custom Post Type Colors").
- **Intro:** "Give each post, page, project or product its own colour — drawn
  from its cover image and expanded into a full, accessible palette — for the
  accents that belong to it."

## The board (`mode: 'contextual-colors'`)

A body-mounted overlay following the existing board pattern. Top → bottom:

1. **Header** — title + a small **Premium** pill, intro line beneath.
2. **The engine** (angle #3): one entry's journey, left → right — a sample
   **cover** → the extracted **dominant colour** dot → a **12-grade strip**.
   Grades use the real generator's ramp (source × white at 92/84/72/60/40/20%,
   the source, source × black at 18/34/50/66/82%), so the strip is truthful.
   Caption: *"From cover to palette — one colour becomes 12 readable grades."*
3. **The gallery** (angle #1): a grid of ~6 sample entry cards labelled by type
   (**Post · Page · Project · Product …**), each tinted by a *different*
   cover-derived colour, each showing the real card hover-border accent plus its
   colour dot + hex. Caption: *"Every item, its own — automatically."*
4. **Where it lands** (compact strip): abstract demos of the accents the
   colour flows into — **titles**, **buttons**, **page transitions**. Kept
   abstract/illustrative (the contextual palette can tint any scoped element),
   not a literal per-place list. Caption: *"The item's colour flows into the
   accents that belong to it — its titles, its buttons, and the transition to
   the next item."*

## Conditional preview

Contextual Colors is a master-toggle section, so its Preview button shows **only
while the feature is on** (the established pattern) — sits on the title row,
left of the switch.

## Premium

A tasteful "Premium" pill in the board header; the board doubles as the upsell.
Actual **gating** (disabling the toggle + an upgrade link for free installs) is
deliberately out of scope here — a separate decision.

## Files

- JS: `src/_js/customizer/components/contextual-colors-overlay/{index.js,style.scss}`,
  barrel, `src/_js/site-editor/index.js` (mode branch).
- PHP (plugin): `src/Screen/SiteEditor.php` (preview marker on the Post-type group).
- PHP (theme, Anima): `inc/integrations/style-manager/tweak-board.php` (rename +
  breadth copy).

## Verification

Open Site Editor → Tweak Board → enable Contextual Colors → its Preview appears →
open the board; screenshot the engine, gallery and strip. Confirm the Preview is
hidden when the feature is off.
