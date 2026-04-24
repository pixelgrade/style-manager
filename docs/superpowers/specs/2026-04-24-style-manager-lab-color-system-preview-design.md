# Style Manager Lab Color System Preview Design

Date: 2026-04-24
Status: Approved design, ready for implementation planning
Owner: Pixelgrade team

## Summary

Port the Customizer "The color system" experience into Style Manager Lab as a near-literal reuse of the existing visual language and interaction model. The Lab should stop using its synthetic Color Signal card grid as the primary color-system validation surface and instead render the same palette rows, source badges, hover card, surface/accent/text layers, header, copy, and variation-normalization behavior that already exists in the Customizer Colors tab.

This is not a redesign. The value is keeping the carefully designed Customizer UI as the canonical way to inspect the color system, while giving the Lab a live iframe runtime and sidebar controls that do not reload the page.

## Decisions

- Reuse the whole Customizer Color System surface, not only the swatch/card model.
- Keep the palette-centric Customizer logic. The section shows all user palettes and uses hover state to inspect one grade at a time.
- Do not adapt this section to the old Lab `parentVariation` and `signal` controls. Those controls should not remain visible if they no longer affect a live surface.
- Preserve the Customizer visual contract: class names, row rhythm, header layout, source badge placement, hover card structure, copy strings, and CSS variable usage.
- Extract shared presentational code rather than duplicating the Customizer component into Lab.

## Goals

- Replace the current Lab Color Signal zone with a Color System section that visually matches the Customizer Colors tab as closely as the iframe context allows.
- Keep the Color System section live-updating through the existing Lab `postMessage` runtime. Sidebar changes must update the iframe without changing `iframe.src`.
- Make the Customizer and Lab use the same core component and shared CSS so future visual changes happen in one place.
- Keep the Customizer behavior unchanged from a user perspective.
- Avoid frontend-only runtime dependencies that are not explicitly enqueued for the private showcase route.

## Non-goals

- Do not make this section an authoring tool.
- Do not write palette, variation, dark mode, or contextual state to `wp_options`.
- Do not rebuild the Customizer Colors tab visually.
- Do not make the Color System section respond to Color Signal-specific controls.
- Do not introduce a screenshot-regression framework in this phase.

## Existing Customizer Behavior to Preserve

The current implementation lives in `src/_js/customizer/components/colors-preview/index.js` with styles in `src/_js/customizer/components/colors-preview/style.scss`.

The behavior to preserve:

- Header:
  - wrapper `.palette-preview-wrap`, with `.is-dark` when dark mode is active
  - `.palette-preview-header.sm-palette-1.sm-palette--shifted.sm-variation-1`
  - `.sm-overlay__wrap`, `.sm-overlay__container`, `.palette-preview-header-wrap`
  - copy from `styleManager.l10n.colorPalettes.palettePreviewTitle` and `palettePreviewDesc`
- Palette list:
  - read all palettes from the runtime palette output
  - filter out internal palettes whose string id starts with `_`
  - default active palette is the first user palette
  - first row receives `palettePreviewListDesc`
- Palette row:
  - row class `.palette-preview.sm-palette-{id}.sm-variation-{lastHover}`
  - use `darkVariations` when dark mode is active, otherwise `variations`
  - `lastHover` defaults to `sourceIndex + 1`
  - each of 12 swatches has class `.palette-preview-swatches.sm-variation-{index + 1}`
  - hover marks that palette active and sets `lastHover`
- Variation normalization:
  - use site variation as an offset: `(index + siteVariation - 1 + 12) % 12`
  - source badge appears on the first variation whose normalized background matches one of the palette source colors
- Grade structure:
  - `.palette-preview-swatches__wrap`
  - `.palette-preview-swatches__wrap-surface`
  - `.palette-preview-swatches__wrap-background`
  - `.palette-preview-swatches__wrap-accent`
  - `.palette-preview-swatches__wrap-foreground`
  - `.palette-preview-swatches__card` and nested card content
- CSS:
  - keep the existing swatch layer layout, hover opacity, card shadow, badge mask, accent bars, and arrow buttons
  - keep the output CSS filename behavior compatible with the existing `../../images/star.svg` mask URL unless browser verification proves it is currently broken

## Architecture

### Shared Core

Create a shared color-system preview module:

```text
src/_js/color-system-preview/
- ColorSystemPreview.jsx
- utils.js
- style.scss
```

`ColorSystemPreview.jsx` is a presentational component. It receives data and callbacks through props and has no direct dependency on `wp.customize`, the Customizer `DarkMode` singleton, `window.styleManager`, or Lab globals.

Recommended props:

```js
{
  palettes: [],
  isDark: false,
  siteVariation: 1,
  strings: {
    palettePreviewTitle: '',
    palettePreviewDesc: '',
    palettePreviewListDesc: '',
    palettePreviewSwatchSurfaceText: '',
    palettePreviewSwatchAccentText: '',
    palettePreviewSwatchForegroundText: '',
  },
}
```

`utils.js` owns logic that should be tested outside the browser:

- `getUserPalettes(palettes)`
- `normalizePreviewIndex(index, siteVariation)`
- `normalizeHexColor(value)`
- `isSourceVariation({ variations, workingIndex, source })`
- `getInitialHoverVariation(sourceIndex)`

The shared utility should use normalized hex equality instead of `chroma.distance(...) === 0`. The current source and background values are hex strings, and removing `chroma-js` avoids requiring a Customizer-only dependency inside the Lab iframe.

### Customizer Adapter

Keep `src/_js/customizer/components/colors-preview/index.js` as a thin adapter:

- subscribe to `DarkMode` and pass `isDark`
- read and watch `sm_advanced_palette_output`
- read and watch `sm_site_color_variation`
- pass `styleManager.l10n.colorPalettes` into the shared component
- import the shared component and shared stylesheet

The Customizer adapter keeps the existing data sources and lifecycle, but stops owning the duplicated visual tree.

### Lab Showcase Adapter

Add a Lab iframe adapter under `src/_js/lab-showcase/`, for example:

```text
src/_js/lab-showcase/color-system-preview.js
```

It should:

- mount `ColorSystemPreview` into a PHP-rendered root element
- read initial data from `window.styleManagerLabColorSystem`
- listen for a Lab runtime custom event emitted after `applyShowcaseState()`
- update `isDark` and `siteVariation` without iframe reloads
- leave palette rows unfiltered by the sidebar palette selector

The Lab sidebar palette selector still changes the rest of the showcase body and status strip. The Color System preview itself remains the complete all-palettes surface, matching the Customizer.

### PHP Rendering

Replace `ShowcaseRenderer::render_color_signal_demo()` with a Color System mount:

```html
<section class="sm-lab-zone sm-lab-color-system" data-sm-lab-color-system-zone="1">
  <div id="style-manager-lab-color-system-root"></div>
</section>
```

The PHP route should expose the Color System configuration before `wp_footer()`:

```js
window.styleManagerLabColorSystem = {
  palettes,
  siteVariation,
  isDark,
  strings
};
```

The palette payload should come from the same runtime palette source used elsewhere in Style Manager Lab. Strings should come from the existing Color Palettes l10n keys, with PHP fallbacks matching the current English text if those strings are not already available in the showcase route.

### Runtime Live Updates

The existing Lab runtime already applies state without reloading the iframe. Extend that flow:

1. Parent sidebar sends `SHOWCASE_UPDATE_MESSAGE`.
2. `applyShowcaseState()` updates body classes, status strip, contextual CSS, and resolved-color readback.
3. After applying state, the runtime dispatches:

```js
window.dispatchEvent(new CustomEvent('style-manager-lab:showcase-state', {
  detail: {
    state: normalizedState,
    siteVariation,
  },
}));
```

The Color System adapter listens to this event and updates its component props.

### Dependencies

Use `@wordpress/element` in the shared component and Lab iframe adapter. The showcase route must enqueue the Lab showcase script with `wp-element` as a dependency.

The Customizer script currently depends on `react` and `react-dom`; add `wp-element` if the shared component imports `@wordpress/element`. This keeps the dependency explicit and avoids relying on incidental globals.

Avoid adding `chroma-js` to the Lab showcase route. The shared source-detection utility should not need it.

### Sidebar Controls

The old Color Signal panel becomes misleading once the synthetic Color Signal zone is replaced. The implementation should remove it from the visible Lab sidebar in this phase unless another visible section still responds to it.

The URL state can keep parsing legacy `signal` and `parentVariation` query params for backward compatibility, but the visible controls should not expose dead options. Future Color Signal diagnostics can reintroduce a dedicated panel alongside a dedicated live surface.

## Acceptance Criteria

- Lab Color System section visually matches the Customizer Colors tab structure:
  - same header copy and split layout
  - same palette rows
  - same 12-grade swatch layout
  - same source badges
  - same hover card
  - same surface/accent/text labels
- Hovering a grade in Lab reveals the card for that grade without reloading the iframe.
- Dark mode changes from the Lab sidebar update the Color System preview live.
- Variation changes from the Lab sidebar update source-badge normalization live.
- Palette selector changes still update the broader showcase live, but do not filter the Color System preview rows.
- Internal palettes whose id starts with `_` are hidden in both Customizer and Lab.
- Customizer Colors tab behavior remains unchanged.
- The visible Lab sidebar has no controls that fail to affect an on-screen surface.
- `npm run compile:production` builds both Customizer and Lab assets.
- Focused JS tests cover palette filtering, variation normalization, source badge detection, and Lab runtime event dispatch.
- Browser verification covers Customizer Colors tab and Lab Color System section on desktop width.

## Risks and Mitigations

- **Risk:** Lab iframe lacks React globals.
  **Mitigation:** Use `@wordpress/element` and enqueue `wp-element` for the showcase route.

- **Risk:** Shared CSS path breaks the star mask.
  **Mitigation:** Keep the emitted `../../images/star.svg` URL unchanged at first, then verify in browser. Only introduce a plugin-url CSS variable if verification shows the existing mask is broken.

- **Risk:** Customizer behavior drifts during extraction.
  **Mitigation:** Make the Customizer file an adapter and keep DOM class names unchanged in the shared core. Verify the existing Colors tab manually.

- **Risk:** Sidebar exposes controls with no visible effect.
  **Mitigation:** Remove the visible Color Signal panel in this pass and keep legacy query parsing only as compatibility.

## Open Questions for Implementation

- Where should the Color System l10n payload be assembled for the showcase route: directly in `ShowcaseRoute`, or through `Config` so reset/config AJAX can reuse it?
- Should the old `signal` and `parentVariation` URL params remain serialized by the admin UI, or only parsed when present in older links?
- Does browser verification show the current star mask URL works from both `customizer.css` and `lab-showcase.css`?
