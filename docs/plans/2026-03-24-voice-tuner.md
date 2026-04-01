# Voice Tuner — Font Palette Guidance Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Add a "Voice Tuner" UI above the font palette cards that lets users set 4 personality dimensions (formality, energy, warmth, tradition) via radio-group controls, then sorts and scores palette cards by how well they match the user's voice profile.

**Architecture:** Each personality dimension is an `sm_radio`-style three-option radio group (e.g., Casual / Balanced / Formal). Selecting options recalculates a Euclidean distance score between the user's voice profile and each palette's personality vector. The palette card list is re-sorted in real time, with a "fit %" badge on each card. Personality vectors are stored as metadata on each palette config and passed to JS via `wp_localize_script`.

**Tech Stack:** PHP (Customizer controls + palette config), SCSS (radio-group styling + fit badge), vanilla JS (scoring + DOM re-sorting). No new dependencies. Reuses the existing `sm_radio` / `.sm-radio-group` pattern already in the codebase.

---

## Context for the Implementing Engineer

### Existing Radio-Group Pattern

The codebase already has a pill-shaped radio group used for Font Sizing (`Smaller / Normal / Larger`). See Image #1 in the task brief.

- **PHP control:** `src/Screen/Customizer/Control/SMRadio.php` — renders `<div class="sm-radio-group">` with `<input type="radio">` + `<label>` pairs
- **SCSS:** `src/_js/customizer/scss/controls/_radio-group.scss` — pill-shaped container (`border-radius: 999em`), checked state fills with accent color + checkmark pseudo-element
- **Registration:** Font Sizing uses `type: 'sm_radio'` with `choices` map in `FontPalettes.php:544-556`

The Voice Tuner reuses this exact visual pattern — 4 radio groups, each with 3 options.

### Font Palette Card Rendering

- **PHP:** `src/Screen/Customizer/Control/Preset.php:266-432` — the `font_palette` case renders cards as `<span class="customize-inside-control-row">` with radio inputs
- **Container class:** `.js-font-palette.customize-control-font-palette`
- **JS selection:** `src/_js/customizer/font-palettes/index.js` — listens for label clicks, applies `fonts_logic` via `wp.customize()`
- **JS preset handler:** `src/_js/customizer/fields/preset/index.js` — handles radio preset state changes

### Palette Data Source

- `FontPalettes::get_palettes()` (line 510) returns palette config from design assets (cloud) or `get_default_config()` (line 958)
- Cloud palettes arrive from `design_assets->get_entry('font_palettes')` — their structure may not include `personality` data initially
- 4 hardcoded defaults: `gema`, `julia`, `patch`, `hive`
- Each palette has: `label`, `preview`, `fonts_logic`, `options`

### Customizer Width Constraint

The Customizer sidebar is ~300px wide. Sliders would be cramped. The radio-group approach (3 options per dimension) fits perfectly — each pill group spans the full width with 3 equal-width segments. This matches the existing Font Sizing UI exactly.

---

## Task 1: Add Personality Vectors to Default Palette Configs

**Files:**
- Modify: `src/Customize/FontPalettes.php:958-1430` (inside `get_default_config()`)

**Step 1: Add `personality` key to each default palette**

Add a `personality` array to each of the 4 default palettes, right after the `preview` block and before `fonts_logic`. Values are `0.0`–`1.0` floats on 4 dimensions.

In `get_default_config()`, for the `gema` palette (around line 978, after the closing `],` of `preview`):

```php
'gema' => [
    'label'   => esc_html__( 'Gema', '__plugin_txtd' ),
    'preview' => [ /* ... existing ... */ ],

    // Voice Tuner personality vector (0.0 = low end, 1.0 = high end).
    'personality' => [
        'formality' => 0.65,  // Formal — graceful, polished
        'energy'    => 0.3,   // Calm — thin, delicate
        'warmth'    => 0.4,   // Cool — refined, not cozy
        'tradition' => 0.5,   // Balanced — classic Montserrat but light weights
    ],

    'fonts_logic' => [ /* ... existing ... */ ],
```

Repeat for the other 3 defaults:

```php
// julia
'personality' => [
    'formality' => 0.55,  // Slightly formal — Lora + PT Serif
    'energy'    => 0.4,   // Calm-balanced
    'warmth'    => 0.65,  // Warm — serif combo, inviting
    'tradition' => 0.6,   // Leans traditional — serif stack
],

// patch
'personality' => [
    'formality' => 0.25,  // Casual — handwritten Kalam, rounded
    'energy'    => 0.7,   // Energetic — playful, bouncy
    'warmth'    => 0.8,   // Very warm — handwriting feel
    'tradition' => 0.2,   // Modern — informal, contemporary
],

// hive
'personality' => [
    'formality' => 0.7,   // Formal — Playfair Display, structured
    'energy'    => 0.5,   // Balanced
    'warmth'    => 0.45,  // Slightly cool — editorial
    'tradition' => 0.7,   // Traditional — classic serif display
],
```

**Step 2: Commit**

```bash
git add src/Customize/FontPalettes.php
git commit -m "Add personality vectors to default font palette configs

Each palette now carries a personality map (formality, energy, warmth,
tradition) with float values 0.0–1.0 for Voice Tuner scoring."
```

---

## Task 2: Ensure Cloud Palettes Get Default Personality Vectors

**Files:**
- Modify: `src/Customize/FontPalettes.php` — inside `preprocess_palette_config()` (around line 205)

**Why:** Cloud palettes from the design assets API won't have `personality` data. We need a sensible default so scoring always works.

**Step 1: Add personality defaults in `preprocess_palette_config()`**

Find the `preprocess_palette_config` method. Add defaults for `personality` right at the top of the method, after the empty check:

```php
protected function preprocess_palette_config( array $palette_config ): array {
    if ( empty( $palette_config ) ) {
        return $palette_config;
    }

    // Ensure personality vector exists with balanced defaults for Voice Tuner.
    if ( ! isset( $palette_config['personality'] ) ) {
        $palette_config['personality'] = [];
    }
    $palette_config['personality'] = wp_parse_args( $palette_config['personality'], [
        'formality' => 0.5,
        'energy'    => 0.5,
        'warmth'    => 0.5,
        'tradition' => 0.5,
    ] );

    // ... rest of existing preprocessing ...
```

**Step 2: Run test to verify preprocessing**

Open the Customizer, confirm no PHP errors. Cloud palettes should still render normally. The personality data is inert at this point (nothing reads it yet).

**Step 3: Commit**

```bash
git add src/Customize/FontPalettes.php
git commit -m "Default personality vectors for cloud palettes without metadata

Ensures Voice Tuner scoring works for all palettes, including cloud
palettes that don't yet carry personality data."
```

---

## Task 3: Register 4 Voice Tuner Radio Controls in the Customizer

**Files:**
- Modify: `src/Customize/FontPalettes.php` — inside `add_style_manager_section_master_fonts_config()` (around line 542)
- Modify: `src/Customize/FontPalettes.php` — inside `reorganize_customizer_controls()` (around line 853)

**Step 1: Add the 4 sm_radio settings**

In `add_style_manager_section_master_fonts_config()`, find the `sm_font_sizing` option block (line 544). Add the Voice Tuner controls **right before** `sm_font_sizing`:

```php
'options' => [
    // ── Voice Tuner dimension controls ──
    'sm_voice_tuner_label' => [
        'type'         => 'html',
        'html'         => '<span class="customize-control-title">' . esc_html__( 'Tune your project\'s voice:', '__plugin_txtd' ) . '</span>'
                        . '<span class="description customize-control-description">' . esc_html__( 'Set the personality of your project. Font palettes will be sorted by how well they match.', '__plugin_txtd' ) . '</span>',
        'setting_type' => 'option',
        'setting_id'   => 'sm_voice_tuner_label',
        'priority'     => 0.5,
    ],
    'sm_voice_formality' => [
        'type'         => 'sm_radio',
        'setting_type' => 'option',
        'setting_id'   => 'sm_voice_formality',
        'label'        => esc_html__( 'Formality', '__plugin_txtd' ),
        'default'      => 'balanced',
        'live'         => true,
        'priority'     => 0.6,
        'choices'      => [
            'low'      => esc_html__( 'Casual', '__plugin_txtd' ),
            'balanced' => esc_html__( 'Balanced', '__plugin_txtd' ),
            'high'     => esc_html__( 'Formal', '__plugin_txtd' ),
        ],
    ],
    'sm_voice_energy' => [
        'type'         => 'sm_radio',
        'setting_type' => 'option',
        'setting_id'   => 'sm_voice_energy',
        'label'        => esc_html__( 'Energy', '__plugin_txtd' ),
        'default'      => 'balanced',
        'live'         => true,
        'priority'     => 0.7,
        'choices'      => [
            'low'      => esc_html__( 'Calm', '__plugin_txtd' ),
            'balanced' => esc_html__( 'Balanced', '__plugin_txtd' ),
            'high'     => esc_html__( 'Energetic', '__plugin_txtd' ),
        ],
    ],
    'sm_voice_warmth' => [
        'type'         => 'sm_radio',
        'setting_type' => 'option',
        'setting_id'   => 'sm_voice_warmth',
        'label'        => esc_html__( 'Warmth', '__plugin_txtd' ),
        'default'      => 'balanced',
        'live'         => true,
        'priority'     => 0.8,
        'choices'      => [
            'low'      => esc_html__( 'Cool', '__plugin_txtd' ),
            'balanced' => esc_html__( 'Balanced', '__plugin_txtd' ),
            'high'     => esc_html__( 'Warm', '__plugin_txtd' ),
        ],
    ],
    'sm_voice_tradition' => [
        'type'         => 'sm_radio',
        'setting_type' => 'option',
        'setting_id'   => 'sm_voice_tradition',
        'label'        => esc_html__( 'Style', '__plugin_txtd' ),
        'default'      => 'balanced',
        'live'         => true,
        'priority'     => 0.9,
        'choices'      => [
            'low'      => esc_html__( 'Modern', '__plugin_txtd' ),
            'balanced' => esc_html__( 'Balanced', '__plugin_txtd' ),
            'high'     => esc_html__( 'Classic', '__plugin_txtd' ),
        ],
    ],

    'sm_font_sizing' => [
        // ... existing font sizing config ...
```

**Step 2: Add the Voice Tuner controls to the font palettes section**

In `reorganize_customizer_controls()`, find `$font_palettes_fields` array (line 853). Add the Voice Tuner fields **before** `sm_font_sizing`:

```php
$font_palettes_fields = [
    'sm_voice_tuner_label',
    'sm_voice_formality',
    'sm_voice_energy',
    'sm_voice_warmth',
    'sm_voice_tradition',
    'sm_font_sizing',
    'sm_separator_0_0',
    'sm_current_font_palette',
    // ... rest of existing fields ...
```

**Step 3: Verify controls render**

Open the Customizer → Typography section. The 4 radio groups should appear above Font Sizing, each with 3 pill-shaped options. "Balanced" should be selected by default.

**Step 4: Commit**

```bash
git add src/Customize/FontPalettes.php
git commit -m "Register Voice Tuner dimension controls in Customizer

Four sm_radio controls (formality, energy, warmth, tradition) added to
the font palettes section, using the existing pill-shaped radio-group UI."
```

---

## Task 4: Pass Personality Data and Voice Profile to JavaScript

**Files:**
- Modify: `src/Customize/FontPalettes.php` — inside the `add_font_palettes_data_to_customizer_js_data()` method (find it with grep for `add_font_palettes_data`)

**Why:** The JavaScript needs two things: (1) each palette's personality vector, and (2) the current voice tuner selection. Both must be passed via `wp_localize_script` or the existing Style Manager JS data object.

**Step 1: Find and read the existing JS data method**

Search for `add_font_palettes_data_to_customizer_js_data` or `font_palettes.*js_data` or `localiz` in `FontPalettes.php`. This method adds palette data to the JS-accessible config.

If no such dedicated method exists, find where `styleManager` JS data is built. The font palettes JS localization is at line ~1539:

```php
protected function add_font_palettes_to_js_data( array $localized ): array {
```

**Step 2: Add personality map to the localized data**

In the method that builds the JS localized data, add:

```php
// Build palette personality map for Voice Tuner.
$palettes = $this->get_palettes();
$personality_map = [];
foreach ( $palettes as $palette_id => $palette_config ) {
    $personality_map[ $palette_id ] = $palette_config['personality'] ?? [
        'formality' => 0.5,
        'energy'    => 0.5,
        'warmth'    => 0.5,
        'tradition' => 0.5,
    ];
}

$localized['fontPalettes']['personalityMap'] = $personality_map;
```

This makes `styleManager.fontPalettes.personalityMap` available in JS with structure like:
```js
{
  gema: { formality: 0.65, energy: 0.3, warmth: 0.4, tradition: 0.5 },
  julia: { formality: 0.55, energy: 0.4, warmth: 0.65, tradition: 0.6 },
  // ...
}
```

**Step 3: Verify data in browser**

Open the Customizer, open browser console, type:
```js
console.log(styleManager.fontPalettes.personalityMap);
```
Should output the personality map for all palettes.

**Step 4: Commit**

```bash
git add src/Customize/FontPalettes.php
git commit -m "Pass palette personality vectors to Customizer JS

Adds personalityMap to the styleManager.fontPalettes localized data
so the Voice Tuner can compute fit scores client-side."
```

---

## Task 5: Implement Client-Side Voice Tuner Scoring and Card Reordering

**Files:**
- Create: `src/_js/customizer/font-palettes/voice-tuner.js`
- Modify: `src/_js/customizer/font-palettes/index.js` (import and initialize)

**Step 1: Create the Voice Tuner JS module**

Create `src/_js/customizer/font-palettes/voice-tuner.js`:

```js
import $ from 'jquery';

/**
 * Map radio values to numeric scores for distance calculation.
 */
const VALUE_MAP = {
  low: 0.15,
  balanced: 0.5,
  high: 0.85,
};

const DIMENSIONS = [ 'formality', 'energy', 'warmth', 'tradition' ];

/**
 * Read the current voice profile from the 4 Customizer radio controls.
 *
 * @return {Object} e.g. { formality: 0.5, energy: 0.15, warmth: 0.85, tradition: 0.5 }
 */
function getVoiceProfile() {
  const profile = {};
  DIMENSIONS.forEach( dim => {
    const setting = wp.customize( `sm_voice_${ dim }` );
    const val = setting ? setting.get() : 'balanced';
    profile[ dim ] = VALUE_MAP[ val ] ?? 0.5;
  } );
  return profile;
}

/**
 * Compute Euclidean distance between voice profile and a palette's personality.
 * Returns a 0–1 "fit" score (1 = perfect match).
 *
 * @param {Object} profile   Voice profile from getVoiceProfile().
 * @param {Object} personality Palette personality vector.
 * @return {number} Fit score 0–1.
 */
function computeFit( profile, personality ) {
  let sumSq = 0;
  DIMENSIONS.forEach( dim => {
    const diff = ( profile[ dim ] || 0.5 ) - ( personality[ dim ] || 0.5 );
    sumSq += diff * diff;
  } );
  // Max possible distance = sqrt(4 * 1^2) = 2
  const dist = Math.sqrt( sumSq ) / 2;
  return Math.max( 0, 1 - dist );
}

/**
 * Re-sort palette cards and update fit badges.
 */
function updatePaletteOrder() {
  const personalityMap = window.styleManager?.fontPalettes?.personalityMap;
  if ( ! personalityMap ) {
    return;
  }

  const profile = getVoiceProfile();
  const $container = $( '.js-font-palette' );
  if ( ! $container.length ) {
    return;
  }

  // Collect cards with their scores.
  const cards = [];
  $container.find( '.customize-inside-control-row' ).each( function() {
    const $card = $( this );
    const $input = $card.find( 'input[type="radio"]' );
    const paletteId = $input.val();
    const personality = personalityMap[ paletteId ];

    if ( ! personality ) {
      cards.push( { $card, fit: 0.5 } ); // Unknown palettes get neutral score.
      return;
    }

    const fit = computeFit( profile, personality );
    cards.push( { $card, fit } );
  } );

  // Sort: highest fit first. Stable sort preserves original order for equal scores.
  cards.sort( ( a, b ) => b.fit - a.fit );

  // Re-order DOM elements.
  cards.forEach( ( { $card, fit } ) => {
    $container.append( $card );

    // Update or create fit badge.
    let $badge = $card.find( '.voice-tuner-fit' );
    const pct = Math.round( fit * 100 );

    // Only show badge when profile is not all-balanced (i.e., user has made a choice).
    const isDefault = DIMENSIONS.every( dim => {
      const setting = wp.customize( `sm_voice_${ dim }` );
      return ! setting || setting.get() === 'balanced';
    } );

    if ( isDefault ) {
      $badge.remove();
      return;
    }

    if ( ! $badge.length ) {
      $badge = $( '<span class="voice-tuner-fit"></span>' );
      $card.append( $badge );
    }

    $badge.text( pct + '%' );
    $badge.toggleClass( 'voice-tuner-fit--high', fit >= 0.75 );
    $badge.toggleClass( 'voice-tuner-fit--mid', fit >= 0.5 && fit < 0.75 );
    $badge.toggleClass( 'voice-tuner-fit--low', fit < 0.5 );
  } );
}

/**
 * Initialize the Voice Tuner: bind to Customizer setting changes.
 */
export function initializeVoiceTuner() {
  // Wait for Customizer to be ready.
  wp.customize.bind( 'ready', () => {
    DIMENSIONS.forEach( dim => {
      wp.customize( `sm_voice_${ dim }`, setting => {
        setting.bind( () => updatePaletteOrder() );
      } );
    } );

    // Initial sort (in case saved values are non-default).
    updatePaletteOrder();
  } );
}
```

**Step 2: Import and call from the font palettes entry point**

In `src/_js/customizer/font-palettes/index.js`, add the import and initialization:

```js
import $ from 'jquery';
import { initializeVoiceTuner } from './voice-tuner';

export const initializeFontPalettes = () => {

  $( '.js-font-palette' ).each( function( i, obj ) {
    const $paletteSet = $( obj );
    const $labels = $paletteSet.find( 'label' );

    $labels.on( 'click', function( event ) {
      const $label = $( event.target );
      const forID = $label.attr( 'for' );
      const $input = $( `#${ forID }` );
      const fontsLogic = $input.data( 'fonts_logic' );

      applyFontPalette( fontsLogic );
    } );
  } );

  initializeVoiceTuner();
};

const applyFontPalette = ( fontsLogic ) => {
  $.each( fontsLogic, ( settingID, config ) => {
    wp.customize( settingID, setting => {
      setting.set( config );
    } );
  } );
};
```

**Step 3: Build and verify**

```bash
npm run build
```

Open Customizer → Typography. Change a Voice Tuner radio. Palette cards should re-sort. Fit badges should appear on each card.

**Step 4: Commit**

```bash
git add src/_js/customizer/font-palettes/voice-tuner.js src/_js/customizer/font-palettes/index.js
git commit -m "Implement Voice Tuner scoring and palette card reordering

Computes Euclidean distance between user's voice profile and each
palette's personality vector. Cards re-sort in real time. Fit badges
appear when any dimension is changed from the default."
```

---

## Task 6: Style the Voice Tuner Section and Fit Badges

**Files:**
- Modify: `src/_js/customizer/scss/controls/_font-palette.scss`
- Possibly modify: `src/_js/customizer/scss/controls/_radio-group.scss` (if Voice Tuner radio groups need compact styling)

**Step 1: Add Voice Tuner section styling**

Append to `src/_js/customizer/scss/controls/_font-palette.scss`:

```scss
// ── Voice Tuner ──

// Compact the Voice Tuner radio groups — they sit in a tight section above palette cards.
// Each dimension's control already uses .sm-radio-group from _radio-group.scss.
// We just reduce vertical spacing between consecutive Voice Tuner controls.
[id^="customize-control-sm_voice_"] {
  margin-bottom: 6px;

  .customize-control-title {
    font-size: 11px;
    font-weight: 600;
    color: #555;
    margin-bottom: 4px;
    text-transform: uppercase;
    letter-spacing: 0.04em;
  }
}

// The intro label control above the radio groups.
[id="customize-control-sm_voice_tuner_label"] {
  margin-bottom: 10px;
}

// ── Fit Badge ──

.voice-tuner-fit {
  position: absolute;
  top: 8px;
  right: 8px;
  z-index: 2;

  padding: 2px 8px;
  border-radius: 10px;
  font-size: 11px;
  font-weight: 600;
  line-height: 1.4;
  pointer-events: none;

  &--high {
    background: rgba(109, 202, 138, 0.15);
    color: #3a9e5c;
  }

  &--mid {
    background: rgba(201, 168, 108, 0.15);
    color: #a07d3a;
  }

  &--low {
    background: rgba(232, 93, 93, 0.1);
    color: #c45050;
  }
}
```

**Step 2: Build and verify**

```bash
npm run build
```

Open Customizer. The Voice Tuner radio groups should have compact dimension labels. Fit badges should be color-coded pill shapes in the top-right of each palette card.

**Step 3: Commit**

```bash
git add src/_js/customizer/scss/controls/_font-palette.scss
git commit -m "Style Voice Tuner dimension labels and palette fit badges

Compact label styling for the 4 dimension radio groups. Color-coded
fit badges (green/amber/red) positioned top-right of palette cards."
```

---

## Task 7: Persist Voice Profile Across Sessions

**Files:**
- Already handled: The `sm_voice_*` settings use `setting_type: 'option'` which stores values in `wp_options` via the Customizer's built-in save mechanism.

**Step 1: Verify persistence**

1. Open Customizer → Typography
2. Set formality to "Formal", energy to "Energetic"
3. Click "Publish"
4. Refresh the Customizer
5. Confirm the Voice Tuner radios are still set to "Formal" and "Energetic"
6. Confirm palette cards are sorted by the saved voice profile on load

**Why this works:** The `sm_radio` control type with `setting_type: 'option'` stores the value (e.g., `"high"`) in `wp_options` under the setting ID (e.g., `sm_voice_formality`). The Customizer restores these on load automatically. The `initializeVoiceTuner()` call in Task 5 runs `updatePaletteOrder()` at startup, which reads the saved values.

**Step 2: Verify no frontend impact**

The Voice Tuner settings are Customizer-only UI controls. They don't connect to any frontend CSS variable or theme output. Verify:
1. View the site frontend
2. Check page source — no `sm_voice_*` values should appear in any inline CSS or output

No commit needed — this is a verification step.

---

## Task 8: End-to-End QA and Edge Cases

**Files:** None (verification only)

**QA Checklist:**

1. **All dimensions balanced (default):** Palette cards appear in their original order. No fit badges visible.
2. **Single dimension changed:** Cards re-sort. Fit badges appear on all cards.
3. **All dimensions set to extremes:** Cards re-sort dramatically. Top card should have 85%+ fit.
4. **Cloud palettes:** If cloud palettes are available, they should get a "50%" badge (balanced default personality) and sort to the middle.
5. **Palette selection still works:** Clicking a card still applies its fonts to the preview. The radio input `checked` state updates correctly even after DOM reorder.
6. **Responsive in sidebar:** The 4 radio groups fit within the ~300px Customizer width without overflow.
7. **No JS errors:** Open browser console, cycle through all voice tuner combinations. No errors.
8. **RTL:** If RTL stylesheet exists, verify radio groups and fit badges render correctly.

---

## Architecture Notes

### Why Radio Groups Instead of Sliders

1. **Width constraint:** Customizer sidebar is ~300px. Sliders with endpoint labels would be cramped and hard to target.
2. **Cognitive load:** Three discrete options (low/balanced/high) are faster to understand than a continuous slider. Users don't need to agonize over whether they're at 0.6 or 0.7.
3. **Existing pattern:** The `sm_radio` + `.sm-radio-group` UI is already battle-tested in this codebase (Font Sizing, Coloration Level). Zero new CSS patterns needed.
4. **Value mapping:** `low=0.15`, `balanced=0.5`, `high=0.85` gives enough spread for meaningful scoring without the false precision of a 0–100 slider.

### Why Euclidean Distance

Simple, fast, and gives intuitive results. With 4 dimensions and 3 discrete values per dimension, there are only 81 possible voice profiles — Euclidean distance correctly differentiates them. No need for weighted dimensions or more complex similarity measures.

### Scoring Math

- Each dimension maps: `low → 0.15`, `balanced → 0.5`, `high → 0.85`
- Distance = `sqrt(Σ(profile[d] - personality[d])²) / 2`
- Fit = `max(0, 1 - distance)`
- Result: 0% (maximally different) to 100% (identical)

### Future Extensions

- Cloud API can start sending `personality` data per palette — the defaults in Task 2 ensure backwards compatibility
- Additional dimensions can be added by extending `DIMENSIONS` array, adding an `sm_voice_*` control, and including the dimension in palette personality vectors
- A "reset" button could clear all dimensions to "balanced" — just set each `wp.customize('sm_voice_*')` to `'balanced'`
