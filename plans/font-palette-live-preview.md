# Plan: Live Font Palette Preview Cards

## Overview

Replace the current hardcoded background-image PNG previews in the font palette selector with live CSS/HTML-rendered text. Each card will render the palette's name and description using the actual fonts from that palette, with a subtle "Aa" watermark character.

**Target design** (from playground exploration):
- White background (`#fff`), `1px solid #e0e0e0` border, `5px` radius
- Palette name as heading at `34px` in the primary font
- Description at `15px` in the body font, color `#333`
- Subtle "Aa" watermark at `100px`, `12%` opacity, right-center
- `5px` gap between cards, `17px` padding

## Architecture Decision

Font loading for preview cards uses the **Google Fonts CSS `<link>` tag** approach (not WebFontLoader). Reasoning:
- WebFontLoader is loaded in the Customizer but operates inside JS with `WebFont.load()`, which requires the font type detection system (`determineFontType`, `getFontDetails`)
- Preview cards render server-side in PHP — loading fonts via a CSS `<link>` is simpler and non-blocking with `font-display: swap`
- We only need 1-2 weights per font for the preview (not all weights the palette defines)
- Cloud fonts and theme fonts already have `src` URLs; Google fonts use `fonts.googleapis.com`

For **cloud fonts** and **theme fonts** that aren't on Google Fonts, the preview will use the font's `src` URL to inject an `@font-face` rule.

---

## Task 1: Extract preview font data in PHP

**File:** `src/Customize/FontPalettes.php`

**What:** Add a method that extracts the minimal font information needed for preview rendering from a palette config.

**Why:** The palette config stores fonts under `fonts_logic.sm_font_primary` and `fonts_logic.sm_font_body`. The preview needs to extract `font_family` and the appropriate `font_weight` for the heading size (34px) and body size (15px) by evaluating `font_styles_intervals`.

**Implementation:**

Add this method to the `FontPalettes` class (after `preprocess_palette_config`, around line 217):

```php
/**
 * Extract the font styling for the preview card at a given font size.
 *
 * This evaluates font_styles_intervals to determine the correct
 * weight, letter-spacing, and text-transform for a specific size.
 *
 * @param array $font_logic A single font entry from fonts_logic (e.g., sm_font_primary).
 * @param int   $size       The font size in px to evaluate intervals for.
 *
 * @return array{font_family: string, font_weight: string|int, letter_spacing: string, text_transform: string}
 */
public static function get_preview_font_style( array $font_logic, int $size ): array {
    $style = [
        'font_family'    => $font_logic['font_family'] ?? '',
        'font_weight'    => 400,
        'letter_spacing' => '0',
        'text_transform' => 'none',
    ];

    if ( empty( $font_logic['font_styles_intervals'] ) || ! is_array( $font_logic['font_styles_intervals'] ) ) {
        return $style;
    }

    // Walk through intervals to find the one that applies at this size.
    // Intervals are sorted by 'start' (ascending) after preprocessing.
    foreach ( $font_logic['font_styles_intervals'] as $interval ) {
        $start = $interval['start'] ?? 0;
        $end   = $interval['end'] ?? PHP_INT_MAX;

        if ( $size >= $start && $size < $end ) {
            if ( isset( $interval['font_weight'] ) ) {
                $weight = $interval['font_weight'];
                // Normalize 'regular' to 400.
                $style['font_weight'] = ( $weight === 'regular' ) ? 400 : $weight;
            }
            if ( isset( $interval['letter_spacing'] ) ) {
                $style['letter_spacing'] = is_numeric( $interval['letter_spacing'] ) ? $interval['letter_spacing'] . 'em' : $interval['letter_spacing'];
            }
            if ( isset( $interval['text_transform'] ) ) {
                $style['text_transform'] = $interval['text_transform'];
            }
            break;
        }
    }

    return $style;
}
```

**Verification:** This is a pure function. You can verify by mentally evaluating the Gema palette's `sm_font_primary` at size 34: intervals are `[10, 300, uppercase]`, `[12, 700, uppercase]`, `[18, 100, uppercase]`. At 34px, the last interval (start=18, no end) matches, so: `font_weight: 100, text_transform: uppercase, letter_spacing: 0.03em`.

---

## Task 2: Build Google Fonts preview URL in PHP

**File:** `src/Screen/Customizer/Control/Preset.php`

**What:** Collect all Google font families + weights needed across all palette preview cards, and output a single `<link>` tag to load them.

**Why:** Loading one combined CSS URL is much faster than individual requests per palette. We only load the specific weights actually used in the preview (typically 1-2 per font).

**Implementation:**

In the `font_palette` case block (line 266), before the `foreach` loop, collect fonts and emit the `<link>`:

```php
case 'font_palette' : { ?>
    <?php if ( ! empty( $this->label ) ) { ?>
    <span class="customize-control-title"><?php echo esc_html( $this->label ); ?></span>
    <?php }

    if ( ! empty( $this->description ) ) { ?>
        <span class="description customize-control-description"><?php echo $this->description; ?></span>
    <?php } ?>

    <?php
    // Preprocess all choices once.
    $choices = $this->sm_font_palettes->preprocess_config( $this->choices );

    // Collect all Google Fonts needed for preview cards.
    $preview_heading_size = 34;
    $preview_body_size    = 15;
    $google_font_families = []; // family => [weights]

    foreach ( $choices as $choice_config ) {
        if ( empty( $choice_config['fonts_logic'] ) ) {
            continue;
        }
        $fonts_logic = $choice_config['fonts_logic'];

        // Primary font (heading).
        if ( ! empty( $fonts_logic['sm_font_primary']['font_family'] ) ) {
            $heading_style = FontPalettes::get_preview_font_style(
                $fonts_logic['sm_font_primary'], $preview_heading_size
            );
            $family = $heading_style['font_family'];
            $weight = (int) $heading_style['font_weight'];
            $google_font_families[ $family ][] = $weight;
        }

        // Body font.
        if ( ! empty( $fonts_logic['sm_font_body']['font_family'] ) ) {
            $body_style = FontPalettes::get_preview_font_style(
                $fonts_logic['sm_font_body'], $preview_body_size
            );
            $family = $body_style['font_family'];
            $weight = (int) $body_style['font_weight'];
            $google_font_families[ $family ][] = $weight;
        }
    }

    // Build a single Google Fonts <link> tag.
    if ( ! empty( $google_font_families ) ) {
        $font_params = [];
        foreach ( $google_font_families as $family => $weights ) {
            $weights = array_unique( array_map( 'intval', $weights ) );
            sort( $weights );
            $weight_str = implode( ';', array_map( fn( $w ) => "0,$w", $weights ) );
            $font_params[] = 'family=' . rawurlencode( $family ) . ':wght@' . $weight_str;
        }
        $font_url = 'https://fonts.googleapis.com/css2?' . implode( '&', $font_params ) . '&display=swap';
        ?>
        <link rel="stylesheet" href="<?php echo esc_url( $font_url ); ?>">
        <?php
    }
    ?>

    <div class="js-style-manager-preset js-font-palette customize-control-font-palette">
```

**Note:** This approach works for Google Fonts (the vast majority of palettes). Cloud fonts and theme fonts that have a `src` URL would need an inline `@font-face` rule instead. For V1, Google Fonts coverage is sufficient since all default and cloud palettes use Google Fonts. Cloud/theme font handling can be added as a follow-up if needed.

**Verification:** View source in the Customizer and confirm the `<link>` tag appears with the correct font families and weights. Fonts should render without FOIT (Flash of Invisible Text) thanks to `display=swap`.

---

## Task 3: Render live HTML preview cards

**File:** `src/Screen/Customizer/Control/Preset.php`

**What:** Replace the current `<span>` with `background-image` with a structured HTML card containing the palette title, description, and watermark — all styled with inline font properties from the palette's `fonts_logic`.

**Implementation:**

Replace the card rendering loop (lines 308-325) with:

```php
<?php
foreach ( $choices as $choice_value => $choice_config ) {
    if ( empty( $choice_config['options'] ) && empty( $choice_config['fonts_logic'] ) ) {
        continue;
    }

    $choice_config = wp_parse_args( $choice_config, [
        'label'   => '',
        'preview' => [],
    ] );

    $choice_config['preview'] = wp_parse_args( $choice_config['preview'], [
        'title'       => $choice_config['label'],
        'description' => '',
    ] );

    $label = $choice_config['label'];

    if ( empty( $choice_config['options'] ) ) {
        $choice_config['options'] = [];
    }
    $options = $this->convertChoiceOptionsIdsToSettingIds( $choice_config['options'] );
    $data    = ' data-options=\'' . json_encode( $options ) . '\'';

    if ( empty( $choice_config['fonts_logic'] ) ) {
        $choice_config['fonts_logic'] = [];
    }
    $fonts = $this->convertChoiceOptionsIdsToSettingIds( $choice_config['fonts_logic'] );
    $data  .= ' data-fonts_logic=\'' . json_encode( $fonts ) . '\'';

    // Extract preview font styles.
    $fonts_logic   = $choice_config['fonts_logic'];
    $heading_style = FontPalettes::get_preview_font_style(
        $fonts_logic['sm_font_primary'] ?? [], $preview_heading_size
    );
    $body_style = FontPalettes::get_preview_font_style(
        $fonts_logic['sm_font_body'] ?? [], $preview_body_size
    );

    $preview_title = esc_html( $choice_config['preview']['title'] );
    $preview_desc  = esc_html( $choice_config['preview']['description'] );

    $heading_font_family = esc_attr( $heading_style['font_family'] );
    $heading_font_weight = esc_attr( (string) $heading_style['font_weight'] );
    $heading_letter_spacing = esc_attr( $heading_style['letter_spacing'] );
    $heading_text_transform = esc_attr( $heading_style['text_transform'] );

    $body_font_family = esc_attr( $body_style['font_family'] );
    $body_font_weight = esc_attr( (string) $body_style['font_weight'] );
    ?>

    <span
        class="customize-inside-control-row <?php echo( (string) $this->value() === (string) $choice_value ? 'current-font-palette' : '' ); ?>">
        <input
            <?php $this->link(); ?>
            name="<?php echo esc_attr( $this->setting->id ); ?>"
            id="<?php echo esc_attr( $choice_value ); ?>-font-palette"
            type="radio"
            value="<?php echo esc_attr( $choice_value ); ?>"
            <?php selected( $this->value(), $choice_value ); ?>
            <?php echo $data; ?>
        />
        <span class="font-palette-preview__watermark"
              style="font-family: '<?php echo $heading_font_family; ?>', sans-serif;"><?php
            echo esc_html( 'Aa' );
        ?></span>
        <span class="font-palette-preview__title"
              style="font-family: '<?php echo $heading_font_family; ?>', sans-serif; font-weight: <?php echo $heading_font_weight; ?>; letter-spacing: <?php echo $heading_letter_spacing; ?>; text-transform: <?php echo $heading_text_transform; ?>;"><?php
            echo $preview_title;
        ?></span>
        <span class="font-palette-preview__desc"
              style="font-family: '<?php echo $body_font_family; ?>', serif; font-weight: <?php echo $body_font_weight; ?>;"><?php
            echo $preview_desc;
        ?></span>
        <label for="<?php echo esc_attr( $choice_value ) . '-font-palette'; ?>">
            <span class="screen-reader-text"><?php echo esc_html( $label ); ?></span>
        </label>
    </span>
<?php } ?>
```

**Key details:**
- The outer `<span class="customize-inside-control-row">` no longer has `style="background-image: ..."` — it's removed entirely
- Three new inner spans: `__watermark`, `__title`, `__desc` — each styled with inline font properties from the palette
- The radio input and label remain unchanged — the click behavior is preserved
- `preview.title` and `preview.description` are used for the text content (they already exist in the data)

**Verification:** Load the Customizer, open Typography > font palette selector. Each card should show the palette name rendered in its own primary font, with the description in the body font. Clicking a card should still apply the palette (radio button behavior unchanged).

---

## Task 4: Update the SCSS for live preview layout

**File:** `src/_js/customizer/scss/controls/_font-palette.scss`

**What:** Replace the current background-image-based CSS with styles for the new live text preview layout.

**Implementation:**

Replace the entire file content with:

```scss
//------------------------------------*\
//    FONT PALETTES
//------------------------------------*/

.customize-control-font-palette {

  .customize-inside-control-row {
    position: relative;

    padding: 17px;
    margin-left: 0;
    overflow: hidden;

    background-color: #fff;
    border: 1px solid #e0e0e0;
    border-radius: 5px;

    & + .customize-inside-control-row {
      margin-top: 5px;
    }

    input {
      display: none;
    }

    // The label overlay for click target — covers the full card.
    input + label {
      position: absolute;
      top: 0;
      right: 0;
      bottom: 0;
      left: 0;

      border: 2px solid transparent;
      border-radius: inherit;
      cursor: pointer;
      transition: border-color 0.15s ease;
    }

    &:hover input + label {
      border-color: var(--sm-color-palette-neutral-color-3, #999);
    }

    input:checked + label {
      border-color: var(--sm-color-palette-neutral-color-5, #333);
    }
  }

  // Watermark character
  .font-palette-preview__watermark {
    position: absolute;
    right: 8px;
    top: 50%;
    transform: translateY(-50%);

    font-size: 100px;
    line-height: 1;
    opacity: 0.12;
    pointer-events: none;
    color: #222;
  }

  // Palette title (rendered in primary font)
  .font-palette-preview__title {
    display: block;
    position: relative;
    z-index: 1;

    font-size: 34px;
    line-height: 1.15;
    color: #222;
    margin-bottom: 4px;
  }

  // Palette description (rendered in body font)
  .font-palette-preview__desc {
    display: block;
    position: relative;
    z-index: 1;

    font-size: 15px;
    line-height: 1.45;
    color: #333;
  }
}
```

**Key changes from old to new:**
- `padding-top: 52%` (aspect ratio hack for background image) → `padding: 17px` (text-based content)
- `background-size/repeat/position` rules removed (no more background image)
- `background-color` changed from CSS variable to `#fff`
- Three new child selectors for `__watermark`, `__title`, `__desc`
- Border changed from variable-based to fixed `1px solid #e0e0e0`
- Gap changed from `calc(0.5 * var(--customizer-spacing))` to `5px`

**Verification:** Rebuild CSS with `npm run build` (or whatever the SCSS compilation command is). Inspect cards in the Customizer — layout should match the playground prototype exactly.

---

## Task 5: Handle the `font_styles_intervals` edge case for the last interval

**File:** `src/Customize/FontPalettes.php` (the method from Task 1)

**What:** The `preprocess_fonts_logic_config` method (line 228) already processes intervals and adds `end` values. However, the last interval in a chain has no `end`. The `get_preview_font_style` method must handle this correctly.

**Why:** When evaluating at 34px, the last interval (which has no `end`) should match. The implementation in Task 1 already handles this with `PHP_INT_MAX` as default end. But we should also handle the case where `font_styles_intervals` is empty or missing — fallback to the first available `font_weight` from the palette's `font_weights` array.

**Implementation:**

Update the method from Task 1 to add a fallback:

```php
// After the foreach, if we still have weight=400 (default) and the palette specifies font_weights,
// use the first weight from font_weights as a better default.
if ( $style['font_weight'] === 400 && ! empty( $font_logic['font_weights'] ) ) {
    $first_weight = $font_logic['font_weights'][0];
    if ( is_numeric( $first_weight ) ) {
        $style['font_weight'] = (int) $first_weight;
    }
}
```

Add this right before the `return $style;` in `get_preview_font_style`.

**Verification:** Check a palette with no intervals — the first weight from `font_weights` should be used.

---

## Task 6: Ensure `preview.title` and `preview.description` exist for cloud palettes

**File:** `src/Customize/FontPalettes.php`

**What:** Verify that cloud-provided palette configs include `preview.title` and `preview.description`. If missing, fall back to the palette's `label`.

**Why:** The hardcoded defaults have `preview.title` and `preview.description`, but cloud palettes might not. The new rendering depends on these fields.

**Implementation:**

In `preprocess_palette_config` (line 205), add defaults for the preview fields:

```php
protected function preprocess_palette_config( array $palette_config ): array {
    if ( empty( $palette_config ) ) {
        return $palette_config;
    }

    // Ensure preview fields have defaults.
    if ( ! isset( $palette_config['preview'] ) ) {
        $palette_config['preview'] = [];
    }
    if ( empty( $palette_config['preview']['title'] ) && ! empty( $palette_config['label'] ) ) {
        $palette_config['preview']['title'] = $palette_config['label'];
    }
    if ( empty( $palette_config['preview']['description'] ) ) {
        $palette_config['preview']['description'] = '';
    }

    global $wp_customize;
    // ... rest of existing code
```

**Verification:** Clear the design assets cache (or test with cloud palettes that may lack `preview.title`) — the label should be used as fallback. An empty description renders fine (card just shows the title).

---

## Execution Order

1. **Task 1** — Add `get_preview_font_style()` to `FontPalettes.php` + Task 5 edge case
2. **Task 6** — Add preview field defaults in `preprocess_palette_config()`
3. **Task 2** — Build Google Fonts `<link>` tag in `Preset.php`
4. **Task 3** — Render live HTML cards in `Preset.php`
5. **Task 4** — Update SCSS

Tasks 1-3 are sequential (each depends on the prior). Task 4 is independent but should be applied last since you need to see the new HTML structure to verify styles.

## What's NOT Changing

- `background_image_url` stays in the data structure (backwards compatibility)
- `preview.title_font` and `preview.description_font` stay (unused but harmless)
- The radio button behavior and `data-options` / `data-fonts_logic` attributes are preserved exactly
- The JS font palette application logic (`initializeFontPalettes`) is untouched
- No React/JS changes needed — this is purely PHP + SCSS

## Performance Comparison

| Aspect | Before (images) | After (live text) |
|--------|-----------------|-------------------|
| Network | 5-6 PNG requests to cloud CDN (~20-50KB each) | 1 Google Fonts CSS request (~2KB) + woff2 font files (~15-30KB each, shared with preview iframe) |
| Render | Instant after image load | Instant with `font-display: swap` (system font fallback until loaded) |
| Cache | PNG images cached per-session | Google Fonts cached aggressively by browser (long-lived cache headers) |
| Maintenance | Requires creating new PNG for each new palette | Zero — renders automatically from palette data |
