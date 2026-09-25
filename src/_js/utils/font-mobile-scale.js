/**
 * JS twin of style_manager_font_mobile_scale_slope() and
 * style_manager_font_mobile_scale_css_cb() (src/sm-functions.php) — keep in sync.
 *
 * The Phone Heading Scale is the share (0-100) of their desktop size that large
 * roles keep on phones; the theme slope is `1 - value / 100`, clamped to 0-1.
 * An unset value emits nothing so the theme's own slope stays.
 */
export const getFontMobileScaleSlope = value => {
  if ( null === value || undefined === value || 'boolean' === typeof value || '' === String( value ).trim() || ! isFinite( Number( value ) ) ) {
    return null;
  }

  const share = Math.max( 0, Math.min( 100, Number( value ) ) );

  return Math.round( ( 100 - share ) / 100 * 10000 ) / 10000;
};

export const getFontMobileScaleCSS = ( value, selector, property ) => {
  const slope = getFontMobileScaleSlope( value );

  if ( null === slope ) {
    return '';
  }

  return `@media not screen and (min-width: 1440px) { ${ selector } { ${ property }: ${ slope }; } }\n`;
};
