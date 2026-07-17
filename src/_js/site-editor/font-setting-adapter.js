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
 * Bind a font setting without treating the initial read as a new mutation.
 * Site Title controls mount only while the block is selected, so hydration on
 * reselect must preserve provenance for any still-unsaved direct edit.
 */
export const bindFontFamilySetting = ( {
  setting,
  onChange,
  onExternalChange = () => {},
  isDirectMutation = () => false,
} ) => {
  if (
    'function' !== typeof setting
    || 'function' !== typeof setting.bind
    || 'function' !== typeof setting.unbind
    || 'function' !== typeof onChange
  ) {
    return () => {};
  }

  const sync = value => {
    if ( ! isDirectMutation() ) {
      onExternalChange( value );
    }
    onChange( value?.font_family || '' );
  };

  setting.bind( sync );
  onChange( setting()?.font_family || '' );

  return () => setting.unbind( sync );
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
