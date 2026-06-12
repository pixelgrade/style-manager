# Customizer phase-out plan

Status: proposal (2026-06-12) · Owner: George
Context: the Site Editor integration is merged on `main` (see
`plans/2026-06-12-site-editor-controls-summary.md`) and hardened
(#127 sweep protection, #129 payload/runtime trims). Both surfaces edit the
same settings through the same persistence semantics.

## The one constraint that shapes everything

"Remove Style Manager from the Customizer" means removing the **pane UI**,
not the customize framework. The Site Editor integration *runs on* a headless
`WP_Customize_Manager`: `customize_register` is how every SM/theme setting
comes into existence, and published changesets are the save engine
(`HeadlessCustomizer::save()`), now with the #127 sibling-completion guarantee.
That machinery stays forever — it is WordPress's only first-class API for
sanitize-and-persist of theme_mod/option graphs. What can go away is:

- the SM panels/sections/controls *appearing in customize.php*,
- the pane-only assets (customizer pane JS shell, search, deep-link handoffs),
- theme-side handoff scripts (Anima's Styles-route card → already retargeted,
  its `customizer-motion-controls` JS → superseded by
  `style_manager/site_editor_control_dependencies`).

Second constraint: `is_sm_supported()` gates on
`current_theme_supports( 'customizer_style_manager' )` — classic (non-block)
themes declare this too, and they have **no Site Editor**. Every phase below
is therefore gated on `wp_is_block_theme()`; classic themes keep the
Customizer UI until Phase 3 decides their fate.

## Phase 0 — ship 2.3.0 (now): dual surface, SE primary

- Site Editor is the promoted surface on block themes (paintbrush sidebar,
  Styles-route card, deep links). Customizer remains fully functional as the
  safety hatch.
- Done this cycle: #127 (publish hardening), #128 (test suite runs again),
  #129 (payload trims). Keep the parity discipline: any new control lands in
  the structure/payload path, not as pane-only UI.
- Collect support signals: which surface customers actually use, what breaks.

Exit criteria: 2.3.0 in the field ≥1 release cycle with no SE-save or parity
regressions reported.

## Phase 1 — 2.4.0: Customizer becomes a redirect surface (block themes)

- New plugin setting + filter `style_manager/customizer_ui`
  (`auto` | `keep` | `hide`, default `auto` = hide on block themes). The
  kill-switch makes rollback a setting flip, not a release.
- On block themes (in `auto`/`hide`): SM sections/panels register but are
  marked `active_callback => __return_false` in the pane; a single
  "Style Manager has moved" section deep-links to
  `site-editor.php?canvas=edit&sm-sidebar=1` (+ per-section `sm-section`
  links, the existing mechanism). Settings stay registered — the headless
  engine, parity, and third-party `customize_register` consumers are
  untouched.
- Retire Anima's Customizer handoff scripts (tracked Anima-side; the copy
  handoff from the SE work is the same cycle).
- Pixelgrade Care / onboarding: audit links pointing at
  `customize.php?autofocus[...]=sm_*` and re-point block-theme flows to the
  SE deep links.

Exit criteria: support volume on "where did Style Manager go" ≈ 0; no
rollbacks to `keep`.

## Phase 2 — 2.5.0: stop paying for the pane (block themes)

- Split the customizer bundle: `engine-core` (settings model, connected
  fields, palette logic — shared by SE and pane) vs `pane-shell` (controls
  chrome, search, sticky UI). Block themes load only `engine-core` via the
  SE; the pane-shell enqueues are skipped entirely (today the pane assets are
  still registered/enqueued on customize.php even when SM is hidden).
- Remove the "moved" placeholder section; customize.php on block themes shows
  no SM trace. Core itself hides the Customizer menu for block themes unless
  a plugin re-adds it — stop being the reason it appears.
- Classic themes: unchanged, now explicitly the only consumers of pane-shell.

Exit criteria: customize.php on a block theme loads zero SM pane assets;
SE-only bundle size measurably down (baseline: customizer.js engine is the
bulk of the 351 KB SE script payload's dependency chain).

## Phase 3 — 3.0.0: decide classic themes, delete pane code

Two options, decided by the classic-theme install base at the time:

- (a) **Sunset**: classic themes pin to the 2.x branch (WUpdates/wp.org both
  support capped release channels); 3.0 requires a block theme. Pane code
  deleted.
- (b) **Standalone screen**: the headless architecture renders the same
  control structure on any admin page (proven by the SE integration — the
  structure payload is surface-agnostic). Ship an "Appearance → Style
  Manager" screen for classic themes reusing the SE sidebar tree, then delete
  pane code anyway.

(b) is the likely winner: it costs one mount-point page and removes the
Customizer dependency for *all* themes, while the save path stays the
headless changeset engine.

## Invariants — tested at every phase

1. Frontend CSS output byte-identical across surface changes (the baseline
   diff harness from the 2026-06-12 session: capture
   `style-manager_output_style`, `style-manager_fonts_output`,
   `sm-colors-custom-properties`, body classes; diff after).
2. A publish/save touching N settings persists exactly N settings (+ the
   #127 sibling completion). Watch with a `pre_update_option_theme_mods_*`
   tracer when in doubt.
3. Customizer ↔ Site Editor round-trip parity while both exist (146-setting
   store-vs-DB diff probe, zero deviations expected).
4. Unit suite actually runs (`Tests: N > 0` — see #128).

## Risks

- **Read filters in the wild** (translation plugins, option snippets) — the
  #127 completion makes publishes safe, but SE *display* still reads through
  filters; document that SE shows effective values, DB keeps stored ones.
- **Third-party code hooking SM's Customizer sections** (child themes adding
  controls to `sm_*` sections): the headless boot still fires the full
  `customize_register` chain, so their settings keep working; their *pane UI*
  placement stops existing in Phase 2 → needs a release-notes callout and the
  `style_manager/site_editor_section_ids` filter as the migration path.
- **WP.org re-review**: surface changes are review-neutral; keep the
  CLI-exempt ABSPATH guards (#128) so Plugin Check stays green.
- **Deep links in docs/SaaS** (`customize.php?autofocus...`): Phase 1 keeps
  them working via the "moved" section; Phase 2 needs doc updates first.
