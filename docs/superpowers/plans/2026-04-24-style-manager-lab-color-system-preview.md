# Style Manager Lab Color System Preview Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the Lab synthetic Color Signal zone with a near-literal reusable implementation of the Customizer "The color system" preview, with live no-reload updates inside the showcase iframe.

**Architecture:** Extract the Customizer Colors preview into a shared `src/_js/color-system-preview/` component plus tested utility functions. Keep Customizer and Lab as thin adapters around the shared core; the Lab iframe receives initial PHP data, then updates through the existing `postMessage` runtime and a local custom event.

**Tech Stack:** WordPress PHP route/render classes, webpack/Babel, `@wordpress/element`, existing Lab `postMessage` runtime, focused Node JS tests, PHPUnit/Brain Monkey where existing Lab coverage applies.

---

## Preflight

**Files:**
- Reference: `docs/superpowers/specs/2026-04-24-style-manager-lab-color-system-preview-design.md`
- Reference: `.ai/design-system/plans/2026-04-23-style-manager-lab-design.md`
- Reference: `src/_js/customizer/components/colors-preview/index.js`
- Reference: `src/_js/customizer/components/colors-preview/style.scss`

- [ ] **Step 1: Create GitHub issue before implementation**

Use the repo workflow from `AGENTS.md`: create a GitHub issue describing the Color System Lab port, assign it to the latest open milestone, and use `Fixes #N` in implementation commits.

- [ ] **Step 2: Confirm current dirty worktree**

Run: `git status --short`

Expected: existing Lab implementation files may already be dirty or untracked. Do not revert unrelated user changes.

- [ ] **Step 3: Run current focused JS tests**

Run:

```bash
node --input-type=module -e "import assert from 'node:assert/strict'; await import('./tests/js/lab-state.test.js').then(m => m.runLabStateTests(assert)); await import('./tests/js/lab-showcase-runtime.test.js').then(m => m.runLabShowcaseRuntimeTests(assert));"
```

Expected: PASS before starting, or capture the current failure as baseline if the active branch is already mid-change.

---

## File Structure

Create:

- `src/_js/color-system-preview/ColorSystemPreview.jsx` - shared presentational Color System UI.
- `src/_js/color-system-preview/utils.js` - tested palette filtering, hex normalization, source detection, and variation normalization.
- `src/_js/color-system-preview/style.scss` - moved near-literal Customizer preview styles.
- `src/_js/lab-showcase/color-system-preview.js` - Lab iframe adapter that mounts and updates the shared component.
- `tests/js/color-system-preview.test.js` - focused shared utility tests.

Modify:

- `src/_js/customizer/components/colors-preview/index.js` - reduce to Customizer adapter.
- `src/_js/customizer/components/colors-preview/style.scss` - delete or replace with a compatibility import if the build still expects it.
- `src/_js/lab-showcase/index.js` - install runtime and Color System adapter.
- `src/_js/lab-showcase/runtime.js` - dispatch a state event after live updates; retire Color Signal zone updates if the zone is removed.
- `tests/js/lab-showcase-runtime.test.js` - replace old signal-card assertions with event dispatch assertions.
- `src/Lab/ShowcaseRenderer.php` - replace Color Signal markup with Color System mount.
- `src/Lab/ShowcaseRoute.php` - enqueue `wp-element`; emit Color System config.
- `src/Lab/Config.php` - expose Color System strings/palette payload if this is the cleanest shared source.
- `src/Provider/AdminAssets.php` - update registered script dependencies if the admin registration path owns showcase deps.
- `src/Provider/CustomizerAssets.php` - add `wp-element` to Customizer dependencies if shared code imports `@wordpress/element`.
- `src/_js/lab/App.jsx` - remove the visible Color Signal panel if no remaining visible surface responds to it.
- `src/_js/lab/sidebar/ColorSignalPanel.jsx` - leave unused for future diagnostics or remove only if no imports/tests require it.
- `tests/js/lab-state.test.js` - keep legacy query parsing tests, but stop requiring UI serialization of dead signal controls if the admin state is simplified.

---

## Task 1: Shared Color System Utility Tests

**Files:**
- Create: `tests/js/color-system-preview.test.js`
- Create: `src/_js/color-system-preview/utils.js`

- [ ] **Step 1: Write failing tests**

Add tests like:

```js
import {
  getInitialHoverVariation,
  getUserPalettes,
  isSourceVariation,
  normalizeHexColor,
  normalizePreviewIndex,
} from '../../src/_js/color-system-preview/utils.js';

export const runColorSystemPreviewTests = async ( assert ) => {
  const palettes = [
    { id: '_internal' },
    { id: '1' },
    { id: 2 },
  ];

  assert.deepEqual( getUserPalettes( palettes ).map( ( palette ) => palette.id ), [ '1', 2 ] );
  assert.equal( normalizePreviewIndex( 0, 1 ), 0 );
  assert.equal( normalizePreviewIndex( 0, 12 ), 11 );
  assert.equal( normalizePreviewIndex( 11, 2 ), 0 );
  assert.equal( normalizeHexColor( ' #AABBCC ' ), '#aabbcc' );
  assert.equal( normalizeHexColor( 'rgb(0,0,0)' ), '' );
  assert.equal( getInitialHoverVariation( 6 ), 7 );

  const variations = [
    { bg: '#ffffff' },
    { bg: '#00aa00' },
    { bg: '#00AA00' },
  ];

  assert.equal( isSourceVariation( { variations, workingIndex: 1, source: [ '#00aa00' ] } ), true );
  assert.equal( isSourceVariation( { variations, workingIndex: 2, source: [ '#00aa00' ] } ), false );
};
```

- [ ] **Step 2: Run tests and verify failure**

Run:

```bash
node --input-type=module -e "import assert from 'node:assert/strict'; await import('./tests/js/color-system-preview.test.js').then(m => m.runColorSystemPreviewTests(assert));"
```

Expected: FAIL because the module does not exist.

- [ ] **Step 3: Implement minimal utilities**

Implement:

```js
export const getUserPalettes = ( palettes = [] ) => (
  palettes.filter( ( palette ) => ! ( typeof palette?.id === 'string' && palette.id.charAt( 0 ) === '_' ) )
);

export const normalizePreviewIndex = ( index, siteVariation = 1 ) => {
  const parsedVariation = Number.parseInt( siteVariation, 10 );
  const variation = Number.isNaN( parsedVariation ) ? 1 : Math.min( Math.max( parsedVariation, 1 ), 12 );

  return ( index + variation - 1 + 12 ) % 12;
};

export const normalizeHexColor = ( value ) => {
  const color = typeof value === 'string' ? value.trim().toLowerCase() : '';

  return /^#[0-9a-f]{6}$/.test( color ) ? color : '';
};

export const getInitialHoverVariation = ( sourceIndex = 0 ) => Number.parseInt( sourceIndex, 10 ) + 1;

export const isSourceVariation = ( { variations = [], workingIndex = 0, source = [] } ) => {
  const background = normalizeHexColor( variations[ workingIndex ]?.bg );

  if ( ! background || ! source.some( ( color ) => normalizeHexColor( color ) === background ) ) {
    return false;
  }

  return variations.findIndex( ( variation ) => normalizeHexColor( variation?.bg ) === background ) === workingIndex;
};
```

- [ ] **Step 4: Re-run tests**

Run the same Node command.

Expected: PASS.

- [ ] **Step 5: Commit**

Run:

```bash
git add tests/js/color-system-preview.test.js src/_js/color-system-preview/utils.js
git commit -m "test: cover shared color system preview logic"
```

Use the final implementation issue number in the final commit set with `Fixes #N`.

---

## Task 2: Extract Shared Presentational Component and Styles

**Files:**
- Create: `src/_js/color-system-preview/ColorSystemPreview.jsx`
- Create: `src/_js/color-system-preview/style.scss`
- Modify: `src/_js/customizer/components/colors-preview/style.scss`

- [ ] **Step 1: Move styles near-literally**

Copy the contents of `src/_js/customizer/components/colors-preview/style.scss` into `src/_js/color-system-preview/style.scss`.

Keep class names and declarations intact. Do not restyle.

- [ ] **Step 2: Create presentational component**

Use `@wordpress/element`:

```js
/** @jsx createElement */
import { createElement, useEffect, useMemo, useState } from '@wordpress/element';
import classnames from 'classnames';
import {
  getInitialHoverVariation,
  getUserPalettes,
  isSourceVariation,
  normalizePreviewIndex,
} from './utils.js';

import './style.scss';

export const ColorSystemPreview = ( {
  palettes = [],
  isDark = false,
  siteVariation = 1,
  strings = {},
} ) => {
  const userPalettes = useMemo( () => getUserPalettes( palettes ), [ palettes ] );
  const [ activePalette, setActivePalette ] = useState( null );

  useEffect( () => {
    if ( userPalettes.length ) {
      setActivePalette( userPalettes[ 0 ].id );
    }
  }, [ userPalettes ] );

  return (
    <div className={ `palette-preview-wrap ${ isDark ? 'is-dark' : '' }` }>
      {/* preserve Customizer header and rows here */}
    </div>
  );
};

export default ColorSystemPreview;
```

Fill in the preserved JSX from the Customizer component. Replace direct `styleManager.l10n.colorPalettes.*` reads with `strings.*` props. Replace `chroma.distance` source checks with `isSourceVariation()`.

- [ ] **Step 3: Leave compatibility import if needed**

If any code still imports `src/_js/customizer/components/colors-preview/style.scss`, replace the old file with:

```scss
@import "../../../color-system-preview/style";
```

Otherwise remove the old import from the Customizer adapter in Task 3 and leave the old file unused.

- [ ] **Step 4: Run focused shared tests**

Run:

```bash
node --input-type=module -e "import assert from 'node:assert/strict'; await import('./tests/js/color-system-preview.test.js').then(m => m.runColorSystemPreviewTests(assert));"
```

Expected: PASS.

- [ ] **Step 5: Commit**

Run:

```bash
git add src/_js/color-system-preview src/_js/customizer/components/colors-preview/style.scss
git commit -m "feat: extract shared color system preview component"
```

---

## Task 3: Refactor Customizer Colors Preview Into an Adapter

**Files:**
- Modify: `src/_js/customizer/components/colors-preview/index.js`
- Modify: `src/Provider/CustomizerAssets.php`

- [ ] **Step 1: Replace visual tree with shared component**

Keep the existing `DarkMode` and `wp.customize` subscriptions, but render:

```js
return (
  <ColorSystemPreview
    palettes={ palettes }
    isDark={ isDark }
    siteVariation={ siteVariation }
    strings={ styleManager.l10n.colorPalettes }
  />
);
```

The adapter should parse `sm_advanced_palette_output` defensively:

```js
const parsePalettes = ( value ) => {
  try {
    const parsed = JSON.parse( value );
    return Array.isArray( parsed ) ? parsed : [];
  } catch ( error ) {
    return [];
  }
};
```

- [ ] **Step 2: Remove Customizer-only dependencies from the adapter**

Remove `classnames` and `chroma-js` imports from `src/_js/customizer/components/colors-preview/index.js`. Those belong either in shared core (`classnames`) or nowhere (`chroma-js`).

- [ ] **Step 3: Add explicit `wp-element` dependency**

If shared code imports `@wordpress/element`, add `wp-element` to the Customizer script dependency list in `src/Provider/CustomizerAssets.php`.

- [ ] **Step 4: Build Customizer bundle**

Run:

```bash
npm run compile:production
```

Expected: PASS. Sass `@import` deprecation warnings are acceptable if they already exist.

- [ ] **Step 5: Browser verify Customizer Colors tab**

Open the Customizer Colors tab and compare against the pre-change UI:

- header renders
- rows render
- hover card appears
- source badge appears
- dark mode still updates
- changing site variation still shifts source badges

- [ ] **Step 6: Commit**

Run:

```bash
git add src/_js/customizer/components/colors-preview/index.js src/Provider/CustomizerAssets.php dist/js/customizer.js dist/js/customizer.css
git commit -m "refactor: reuse shared color system preview in customizer"
```

---

## Task 4: Add Lab Color System PHP Payload and Mount

**Files:**
- Modify: `src/Lab/ShowcaseRenderer.php`
- Modify: `src/Lab/ShowcaseRoute.php`
- Modify: `src/Lab/Config.php`
- Test: existing `tests/phpunit/Unit/Lab/*`

- [ ] **Step 1: Replace Color Signal section markup**

In `ShowcaseRenderer::render()`, replace the call to `render_color_signal_demo()` with `render_color_system_preview()`.

Add:

```php
private function render_color_system_preview(): void {
	?>
	<section class="sm-lab-zone sm-lab-color-system" data-sm-lab-color-system-zone="1">
		<div id="style-manager-lab-color-system-root"></div>
	</section>
	<?php
}
```

Remove `render_color_signal_demo()` only after JS tests no longer depend on its DOM.

- [ ] **Step 2: Add Color System config builder**

Prefer `Config.php` if it already owns Lab payload assembly. Expose:

```php
[
	'palettes'      => /* runtime palette output array */,
	'siteVariation' => (int) \Pixelgrade\StyleManager\get_option( 'sm_site_color_variation', 1 ),
	'isDark'        => $params->dark(),
	'strings'       => [
		'palettePreviewTitle'                 => __( 'The color system', '__plugin_txtd' ),
		'palettePreviewDesc'                  => __( 'The color system presented below is designed based on your brand colors. Hover over a color grade to see a preview of how you will be able to use colors with your content blocks.', '__plugin_txtd' ),
		'palettePreviewListDesc'              => '',
		'palettePreviewSwatchSurfaceText'     => __( 'Surface', '__plugin_txtd' ),
		'palettePreviewSwatchAccentText'      => __( 'Accent', '__plugin_txtd' ),
		'palettePreviewSwatchForegroundText'  => __( 'Text', '__plugin_txtd' ),
	],
]
```

Use the existing Color Palettes l10n values if a PHP helper already exposes them. The hardcoded strings above are fallbacks, not a new copy source if a canonical source exists.

- [ ] **Step 3: Emit window config safely**

In `ShowcaseRoute::render_document()`, before `wp_footer()`:

```php
<script>
window.styleManagerLabColorSystem = <?php echo wp_json_encode( $this->config->color_system_preview( $params ) ); ?>;
</script>
```

Use the actual injected service/property names from the current Lab classes.

- [ ] **Step 4: Add `wp-element` to showcase script dependencies**

In both route-level registration and admin asset registration if both exist:

```php
wp_register_script(
	'pixelgrade_style_manager-lab-showcase',
	$this->plugin->get_url( 'dist/js/lab-showcase.js' ),
	[ 'wp-element' ],
	VERSION,
	true
);
```

- [ ] **Step 5: Run PHP syntax checks**

Run:

```bash
'/Users/georgeolaru/Library/Application Support/Local/lightning-services/php-8.2.27+1/bin/darwin-arm64/bin/php' -l src/Lab/ShowcaseRenderer.php
'/Users/georgeolaru/Library/Application Support/Local/lightning-services/php-8.2.27+1/bin/darwin-arm64/bin/php' -l src/Lab/ShowcaseRoute.php
'/Users/georgeolaru/Library/Application Support/Local/lightning-services/php-8.2.27+1/bin/darwin-arm64/bin/php' -l src/Lab/Config.php
```

Expected: no syntax errors.

- [ ] **Step 6: Commit**

Run:

```bash
git add src/Lab/ShowcaseRenderer.php src/Lab/ShowcaseRoute.php src/Lab/Config.php src/Provider/AdminAssets.php
git commit -m "feat: mount color system preview in lab showcase"
```

---

## Task 5: Add Lab Iframe Color System Adapter

**Files:**
- Create: `src/_js/lab-showcase/color-system-preview.js`
- Modify: `src/_js/lab-showcase/index.js`

- [ ] **Step 1: Write adapter**

Implement:

```js
/** @jsx createElement */
import { createElement, render, useEffect, useState } from '@wordpress/element';
import ColorSystemPreview from '../color-system-preview/ColorSystemPreview.jsx';

const EVENT_NAME = 'style-manager-lab:showcase-state';

const getInitialConfig = () => ( {
  palettes: [],
  siteVariation: 1,
  isDark: false,
  strings: {},
  ...( window.styleManagerLabColorSystem || {} ),
} );

const LabColorSystemPreview = () => {
  const [ config, setConfig ] = useState( getInitialConfig );

  useEffect( () => {
    const listener = ( event ) => {
      setConfig( ( current ) => ( {
        ...current,
        isDark: Boolean( event.detail?.state?.dark ),
        siteVariation: event.detail?.state?.variation || event.detail?.siteVariation || current.siteVariation,
      } ) );
    };

    window.addEventListener( EVENT_NAME, listener );
    return () => window.removeEventListener( EVENT_NAME, listener );
  }, [] );

  return <ColorSystemPreview { ...config } />;
};

export const installColorSystemPreview = () => {
  const root = document.getElementById( 'style-manager-lab-color-system-root' );

  if ( root ) {
    render( <LabColorSystemPreview />, root );
  }
};
```

Use the exact `render` export available from `@wordpress/element` in this WordPress version. If `render` is not exported, use the existing repo pattern for mounting `@wordpress/element` components.

- [ ] **Step 2: Install adapter from showcase entry**

In `src/_js/lab-showcase/index.js`, import and call:

```js
import { installColorSystemPreview } from './color-system-preview.js';

installColorSystemPreview();
```

Keep the existing runtime install.

- [ ] **Step 3: Run build**

Run:

```bash
npm run compile:production
```

Expected: PASS.

- [ ] **Step 4: Commit**

Run:

```bash
git add src/_js/lab-showcase/color-system-preview.js src/_js/lab-showcase/index.js dist/js/lab-showcase.js dist/js/lab-showcase.css
git commit -m "feat: render shared color system preview in lab iframe"
```

---

## Task 6: Dispatch Runtime State Event and Update Runtime Tests

**Files:**
- Modify: `src/_js/lab-showcase/runtime.js`
- Modify: `tests/js/lab-showcase-runtime.test.js`

- [ ] **Step 1: Add event dispatch test**

Extend the fake window/document test so `applyShowcaseState()` or the message-driven runtime dispatches `style-manager-lab:showcase-state` with normalized state.

Expected assertion:

```js
assert.equal( receivedEvent.detail.state.variation, 4 );
assert.equal( receivedEvent.detail.state.dark, true );
```

- [ ] **Step 2: Remove old Color Signal card assertion**

Delete the assertion that `[data-color-signal="2"]` receives `.is-highlighted` if the old Color Signal zone is removed.

- [ ] **Step 3: Implement event dispatch**

After `applyShowcaseState()` finishes, dispatch:

```js
const dispatchShowcaseState = ( windowRef, payload ) => {
  if ( typeof windowRef.CustomEvent !== 'function' ) {
    return;
  }

  windowRef.dispatchEvent( new windowRef.CustomEvent( 'style-manager-lab:showcase-state', {
    detail: {
      state: payload.state,
      siteVariation: payload.state.variation,
    },
  } ) );
};
```

Call this from `syncAndPublish()` after `applyShowcaseState()`.

- [ ] **Step 4: Keep runtime focused**

Remove `updateSignalZone()` calls if no rendered section uses `[data-sm-lab-signal-zone]`. Leave helper functions only if another test or future zone still uses them.

- [ ] **Step 5: Run JS tests**

Run:

```bash
node --input-type=module -e "import assert from 'node:assert/strict'; await import('./tests/js/color-system-preview.test.js').then(m => m.runColorSystemPreviewTests(assert)); await import('./tests/js/lab-showcase-runtime.test.js').then(m => m.runLabShowcaseRuntimeTests(assert));"
```

Expected: PASS.

- [ ] **Step 6: Commit**

Run:

```bash
git add src/_js/lab-showcase/runtime.js tests/js/lab-showcase-runtime.test.js
git commit -m "feat: broadcast live lab state to showcase adapters"
```

---

## Task 7: Remove Visible Dead Signal Controls

**Files:**
- Modify: `src/_js/lab/App.jsx`
- Modify: `src/_js/lab/state.js`
- Modify: `tests/js/lab-state.test.js`
- Optional Modify/Delete: `src/_js/lab/sidebar/ColorSignalPanel.jsx`

- [ ] **Step 1: Remove `ColorSignalPanel` from rendered sidebar**

In `App.jsx`, remove the import and JSX call:

```js
// remove
import { ColorSignalPanel } from './sidebar/ColorSignalPanel.jsx';

// remove
<ColorSignalPanel state={ state } onChange={ updateState } />
```

- [ ] **Step 2: Keep or simplify legacy URL state intentionally**

Choose one:

- Keep `signal` and `parentVariation` in `parseLabState()` only, so old URLs still sanitize.
- Stop serializing `signal` and `parentVariation` from `buildAdminUrl()` and `buildShowcaseUrl()` unless explicitly present.

The visible admin UI should not expose these values while no visible zone responds to them.

- [ ] **Step 3: Update state tests**

Update `tests/js/lab-state.test.js` to match the chosen compatibility behavior. Keep at least one parse/clamp test for older links if parsing remains.

- [ ] **Step 4: Run Lab state tests**

Run:

```bash
node --input-type=module -e "import assert from 'node:assert/strict'; await import('./tests/js/lab-state.test.js').then(m => m.runLabStateTests(assert));"
```

Expected: PASS.

- [ ] **Step 5: Commit**

Run:

```bash
git add src/_js/lab/App.jsx src/_js/lab/state.js tests/js/lab-state.test.js src/_js/lab/sidebar/ColorSignalPanel.jsx
git commit -m "refactor: hide lab signal controls without a live surface"
```

---

## Task 8: Build and Automated Verification

**Files:**
- Verify build output under `dist/js/`
- Verify PHP and JS tests

- [ ] **Step 1: Run all focused JS tests**

Run:

```bash
node --input-type=module -e "import assert from 'node:assert/strict'; await import('./tests/js/color-system-preview.test.js').then(m => m.runColorSystemPreviewTests(assert)); await import('./tests/js/lab-state.test.js').then(m => m.runLabStateTests(assert)); await import('./tests/js/lab-showcase-runtime.test.js').then(m => m.runLabShowcaseRuntimeTests(assert));"
```

Expected: PASS.

- [ ] **Step 2: Run PHP unit tests with Local PHP 8.2**

Run:

```bash
'/Users/georgeolaru/Library/Application Support/Local/lightning-services/php-8.2.27+1/bin/darwin-arm64/bin/php' vendor/bin/phpunit tests/phpunit/Unit/Lab
```

Expected: PASS.

- [ ] **Step 3: Run production build**

Run:

```bash
npm run compile:production
```

Expected: PASS. Existing Sass deprecation warnings are acceptable.

- [ ] **Step 4: Inspect built assets**

Run:

```bash
ls -1 dist/js/lab-showcase.js dist/js/lab-showcase.css dist/js/customizer.js dist/js/customizer.css
```

Expected: all files exist and timestamps reflect the build.

- [ ] **Step 5: Commit build output**

Run:

```bash
git add dist/js/lab-showcase.js dist/js/lab-showcase.css dist/js/customizer.js dist/js/customizer.css
git commit -m "build: update color system preview assets"
```

---

## Task 9: Browser Verification

**Files:**
- No source changes expected unless verification finds a defect.

- [ ] **Step 1: Open Lab admin page**

Use `http://style-manager.local/wp-admin/tools.php?page=sm-lab`.

Expected: iframe loads and includes a Color System section with the Customizer-style green/blue palette rows rather than the old Signal card grid.

- [ ] **Step 2: Verify no-reload live updates**

In the browser console or Playwriter, track iframe load count while changing sidebar controls.

Expected:

- variation changes update Color System source normalization without iframe reload
- dark toggle updates the preview without iframe reload
- palette selector updates the rest of the showcase without filtering Color System rows
- contextual color still updates the contextual section without iframe reload

- [ ] **Step 3: Verify hover behavior**

Hover several swatches in different palette rows.

Expected:

- the row variation class follows the hovered grade
- exactly one active row card is visible
- source badge appears on source grades

- [ ] **Step 4: Verify Customizer parity**

Open the Customizer Colors tab.

Expected:

- the original Color System layout still renders
- hover cards and source badges still behave
- changing dark/site variation still updates the preview

- [ ] **Step 5: Verify access and asset gating**

Check:

- showcase route still requires `manage_options` and nonce
- normal frontend pages do not enqueue Lab assets
- disabling `style_manager/enable_lab` removes the Lab menu and route

- [ ] **Step 6: Fix defects and re-run focused checks**

For each defect, write or update a focused test first when practical, then re-run Task 8 checks.

---

## Completion

- [ ] **Step 1: Review against the spec**

Compare the implementation against `docs/superpowers/specs/2026-04-24-style-manager-lab-color-system-preview-design.md` acceptance criteria.

- [ ] **Step 2: Final commit**

If earlier commits did not include the issue closure, amend or add a final commit whose message includes:

```text
Fixes #N
```

- [ ] **Step 3: Push to main**

Follow the repository workflow only after tests and browser verification pass.
