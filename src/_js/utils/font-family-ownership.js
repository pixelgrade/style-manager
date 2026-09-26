/**
 * Style Manager owns font families: blocks do not offer their own font family.
 *
 * The block editor resolves every block-level setting through
 * `getBlockSettings()`, which first asks the public
 * `blockEditor.useSetting.before` filter. Answering "no font families" for the
 * font family preset paths, only when a block asks, removes the per-block
 * "Font" control the same way a block-level `settings.blocks.*` override would,
 * but for every preset origin (Font Library fonts live in the user origin,
 * which theme-level block settings cannot override).
 *
 * Untouched: font size, line height, and every other typography setting; the
 * global (non-block) reads; and existing `fontFamily` attributes, which keep
 * rendering because block supports output does not depend on these settings.
 */
export const FONT_FAMILY_SETTING_PATHS = [
  'typography.fontFamilies',
  'typography.fontFamilies.custom',
  'typography.fontFamilies.theme',
  'typography.fontFamilies.default',
];

export const FILTER_NAMESPACE = 'style-manager/font-family-ownership';

export const withoutBlockFontFamilies = ( result, path, clientId, blockName ) => {
  if ( ! blockName ) {
    return result;
  }

  return FONT_FAMILY_SETTING_PATHS.includes( path ) ? [] : result;
};

export const registerFontFamilyOwnership = ( hooks ) => {
  if ( ! hooks?.addFilter ) {
    return false;
  }

  hooks.addFilter( 'blockEditor.useSetting.before', FILTER_NAMESPACE, withoutBlockFontFamilies );

  return true;
};
