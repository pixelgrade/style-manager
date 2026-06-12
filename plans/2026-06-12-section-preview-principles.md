# Section preview principles

Status: adopted (2026-06-12) · Derived from the Colors and Typography boards
before extending previews to Spacing and Motion.

## How the existing boards work

**Colors** (`ColorsPreview` → `ColorSystemPreview`): renders the *entire
output space* of the color system — every palette × every grade, with
SURFACE / TEXT / ACCENT roles and hover usage demos. It binds
`sm_advanced_palette_output` (the engine's computed system) and
`sm_site_color_variation` through `useCustomizeSettingCallback`, so the
whole board re-renders the instant the engine recomputes — same document,
zero network.

**Typography** (`TypographyPreview`): enumerates every type *role* the
system manages (display, h1–h6, buttons, lead, body, accent, navigation,
input, meta) from a curated list, resolves each role's connected setting to
real CSS (family, size, spacing) and renders a true specimen plus the
computed size badge. Re-resolves on master/connected setting changes,
in-document, with the pane's already-loaded webfonts.

## Why the Live Site preview couldn't do this job

1. **Coverage** — a real page only exercises the tokens its content happens
   to use. No homepage shows all 12 color grades or an H5 and an input
   field. The boards enumerate the token space deterministically.
2. **Feedback latency** — the boards react at engine speed (milliseconds,
   in-document) while a user drags a slider. The live preview pays a
   changeset write plus an iframe load for refresh-transport options;
   that breaks the tuning loop.
3. **Isolation** — on a real page, tokens interact with images, overlays
   and contextual palettes; you can't *see* the token itself. Boards show
   tokens on neutral ground.
4. **Quantification** — boards surface the resolved numbers (hex values,
   px sizes) next to every rendered token; pages can't.
5. **Pedagogy** — the boards teach the system's roles and usage rules while
   previewing. That's half their value.

## The principles

1. **Preview the system, not a sample.** Enumerate the full token space the
   section controls, not whatever one page happens to use.
2. **Bind to the engine, render in-document.** `wp.customize` setting
   callbacks, React state, zero network in the tuning loop.
3. **Show resolved values.** Every rendered token carries its computed
   number (px, %, hex, ratio). Changes must be quantifiable, not just
   visible.
4. **Isolate and teach.** Neutral ground, labeled roles, usage hints. The
   board doubles as documentation of the design system.
5. **The Live Site preview is the integration check, not the system
   check.** It shows real content, PHP-rendered options, and behaviors that
   only exist on the frontend. Both previews coexist; they answer different
   questions.
6. **When a section's effect is intrinsically experiential, the right
   "board" may be a guided live-site flow.** Don't fake what can only be
   felt in context (see Motion below).

## Per-section decisions

| Section | Token space | Preview approach |
|---|---|---|
| Color System | palettes × grades × roles | System board (shipped) |
| Typography | type roles, resolved fonts/sizes | Specimen board (shipped) |
| **Spacing** | container width %, content inset, spacing ratio → derived rhythm steps | **System board**: page-anatomy blueprint (container/inset with measures) + vertical rhythm ladder (base step 32 × ratio and its multiples, px badges) + a density demo that breathes with the level |
| **Motion** | transition style/symbol, intro style/speed — *behaviors over time, triggered by navigation/load* | **Guided live-site flow** (proposal): motion cannot be honestly previewed on a static board; its Preview should open the Live Site preview, where in-preview navigation already replays page transitions, with a hint pointing at what to do. A replay stage inside a board would simulate the theme's frontend JS rather than run it — principle 6 says don't. |

The Spacing board renders resolved values from the same relationships the
theme CSS uses (`--theme-spacing-ratio: var(--sm-spacing-level)`,
`--spacing-y1: calc(32 * ratio)`), so the numbers shown are the numbers
shipped.
