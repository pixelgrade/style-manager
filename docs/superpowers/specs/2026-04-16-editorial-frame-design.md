# Editorial Frame Design

Date: 2026-04-16

## Summary

`Editorial Frame` is a reusable LT chrome system that will ship first in the Hive LT variation while remaining variation-agnostic at the system level.

It reinterprets the Hive theme chrome as:

- a site-wide frame language
- a site-wide utility rail driven by a normal WordPress menu location
- a preset-driven Style Manager surface, not a freeform layout builder

The LT stack remains:

- `Anima` owns rendering and runtime behavior
- `Style Manager` owns the site-wide chrome settings
- `Nova Blocks` continues to own the main header and page structure

## Goals

- Recreate the editorial/framed chrome language seen in Hive in a way that fits LT architecture.
- Keep the feature reusable beyond Hive LT.
- Use the existing LT menu/search/social primitives instead of building parallel systems.
- Keep the authoring surface small and opinionated.

## Non-Goals

- Do not port legacy Hive PHP templates directly.
- Do not build a composable edge-by-edge chrome builder in v1.
- Do not create a second search system outside the normal menu extras flow.
- Do not move main header ownership away from Nova Blocks.

## Source Context

- Live reference: `https://demos.pixelgrade.com/hive/`
- Legacy theme source: `https://github.com/pixelgrade/hive`
- LT runtime references:
  - Anima FSE wrapper hooks in `inc/fse.php`
  - Anima Style Manager integration in `inc/integrations/style-manager/style-manager.php`
  - Anima menu extras in `inc/admin/class-admin-nav-menus.php`
  - Anima social auto-icon styling in `dist/css/social-links.css`

## High-Level Approach

The Hive chrome is treated as a system preset called `Editorial Frame`, not as a Hive-specific implementation.

`Editorial Frame` has three layers:

1. Frame accents
   - top strip
   - left strip
   - spacing reservation for the right rail

2. Utility rail
   - fixed on desktop
   - collapsed into a compact top-row treatment on mobile

3. Chrome menu content
   - normal WordPress menu assignment
   - supports normal links, social links, and existing extras like Search

## Ownership Model

### Style Manager

Style Manager owns the site-wide chrome settings and exposes a small preset-driven control surface.

It stores normal `sm_*` options inside the Anima option set, following the same contract already used for LT-wide settings.

### Anima

Anima owns runtime output and layout integration.

Responsibilities:

- register the `chrome` menu location
- read the site-wide `sm_*` chrome options
- add body classes for chrome state
- render the frame and right rail shell
- render the `chrome` navigation
- apply responsive collapse behavior

### Nova Blocks

Nova Blocks remains responsible for:

- main header structure
- sticky header logic
- reading/header interactions already provided by the LT stack

The chrome shell is outside the header block area and should not become a second header system.

## Menu Model

The `chrome` rail is driven by a real WordPress menu location named `chrome`.

This location supports:

- normal links
- social links as ordinary menu links
- existing menu extras, especially `Search`

Search remains menu-driven. Editors add it from the existing Extras menu item box, and the current overlay/search-suggestions behavior remains the active implementation.

## Chrome Item Styling

Chrome menu items are styled automatically by type.

### Existing extras

Known extras such as `Search` continue using their existing class-driven behavior and icon treatment.

### Social links

Social links continue using the existing URL-based auto-detection and icon styling already used elsewhere in Anima.

### Regular links

Regular links receive a generated monogram marker derived from the first visible character of the menu label.

Rules:

- uppercase first visible character
- decorative only; hidden from assistive tech
- label remains the accessible name
- generated at render time, never written back into saved menu labels

This keeps the rail visually coherent without adding per-link authoring overhead.

## Settings Surface

The v1 Style Manager surface is intentionally small.

### Exposed settings

- `sm_chrome_preset`
  - `none`
  - `editorial-frame`

- `sm_chrome_menu_visibility`
  - `off`
  - `on`

- `sm_chrome_frame_visibility`
  - `off`
  - `on`

- `sm_chrome_color_role`
  - a high-level color role aligned with Style Manager palette logic
  - examples: `strong-contrast`, `palette-1`, `palette-2`

### Implicit preset behavior

`Editorial Frame` implies the default chrome item styling:

- extras keep their icon behavior
- social links auto-iconize
- regular links get monogram markers

This behavior should stay implicit in v1, not exposed as an extra user-facing setting.

## Render Contract

The chrome shell is rendered by Anima around the FSE template wrapper.

Recommended insertion points:

- open the shell around `anima/template_html:before`
- close it around `anima/template_html:after`
- keep search overlay output on its current footer hook path

The shell sits outside block template content so it can:

- reserve layout space consistently
- apply frame accents independently of the header block
- remain site-wide regardless of block template composition

## Responsive Behavior

Desktop behavior:

- top frame accent
- left frame accent
- right utility rail

Mobile behavior:

- do not preserve the frame or right rail
- append Chrome Menu items to the existing mobile menu
- place appended Chrome Menu items at the bottom of the mobile menu
- preserve support for the same content types and extras in that mobile menu context

This keeps mobile navigation inside a single navigation system and avoids introducing a second chrome treatment on small screens.

## Fallback Behavior

The system must fail cleanly.

- If `sm_chrome_preset = none`, no chrome shell or chrome spacing reservation should affect layout.
- If `Editorial Frame` is active but no `chrome` menu is assigned, the theme should still render cleanly.
- If chrome menu visibility is disabled, frame behavior should still work independently when configured.

## Rollout

### v1

- register the `chrome` menu location
- add the new Style Manager chrome settings
- render the outer chrome shell
- render the `chrome` menu rail
- support automatic item treatment for extras, social links, and normal links
- append Chrome Menu items to the bottom of the mobile menu on smaller screens instead of rendering a separate mobile chrome

### v2

- add more chrome presets
- add optional article-specific chrome behaviors if they prove useful
- add narrowly scoped escape hatches only if real usage shows the v1 defaults are insufficient

## Verification

QA should cover:

- no chrome preset
- `Editorial Frame` with no assigned chrome menu
- chrome menu with regular links only
- chrome menu with social links only
- chrome menu with Search only
- mixed chrome menu with regular + social + Search
- desktop and mobile
- mobile menu with appended Chrome Menu items at the bottom
- light and dark palette combinations
- coexistence with Nova sticky header behavior
- coexistence with existing search overlay flow

## Naming

- System preset name: `Editorial Frame`
- Slug: `editorial-frame`
- Variation-level naming in Hive LT can reference this preset, but should not define the system concept itself
