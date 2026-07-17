/**
 * Resolve a theme setting without coupling the Site Editor UI to a theme's
 * option container (for example `anima_options[site_title_font]`).
 */
export const resolveThemeSettingId = ( settings = {}, bareId = '' ) => {
  if ( ! bareId || ! settings || 'object' !== typeof settings ) {
    return '';
  }

  if ( Object.prototype.hasOwnProperty.call( settings, bareId ) ) {
    return bareId;
  }

  return Object.keys( settings ).find( id => id.endsWith( `[${ bareId }]` ) ) || '';
};

/**
 * Find the legacy font-control row linked to one customize setting.
 */
export const findFontControl = ( root, settingId ) => {
  if ( ! root || ! settingId || 'function' !== typeof root.querySelectorAll ) {
    return null;
  }

  const holder = Array.from( root.querySelectorAll( '[data-customize-setting-link]' ) )
    .find( element => element.getAttribute( 'data-customize-setting-link' ) === settingId );

  return holder && 'function' === typeof holder.closest
    ? holder.closest( 'li.customize-control' )
    : null;
};

/**
 * Drive the existing font family control rather than writing a parallel font
 * value. The injected callbacks keep this helper DOM-library agnostic and let
 * the caller retain the legacy jQuery change pipeline.
 */
export const applyFontFamilySelection = ( {
  root,
  settingId,
  family,
  ensureOption,
  dispatchChange,
} ) => {
  const control = findFontControl( root, settingId );
  const select = control && 'function' === typeof control.querySelector
    ? control.querySelector( 'select.style-manager_font_family' )
    : null;

  if ( ! select || ! family || 'function' !== typeof ensureOption || 'function' !== typeof dispatchChange ) {
    return false;
  }

  ensureOption( select, family );
  dispatchChange( select, family );

  return true;
};
