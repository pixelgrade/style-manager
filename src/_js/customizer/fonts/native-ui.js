/**
 * Native font UI mode.
 *
 * When on (the Site Editor sidebar), the font fields are skinned with native
 * editor components (see site-editor/font-control.js): the hidden PHP-rendered
 * selects stay the engine's source of truth, but they are never select2-ified
 * and never receive the ~2,000 injected Google font <option> elements — the
 * picker reads the styleManager.fonts catalog directly.
 */
let nativeFontUI = false;

export const setNativeFontUI = value => {
  nativeFontUI = !! value;
};

export const isNativeFontUI = () => nativeFontUI;

/**
 * Make sure a <select> can hold the given font family as its value: the
 * native UI keeps the selects free of the full catalog, so the active family
 * gets its lone <option> appended on demand.
 */
export const ensureFontFamilyOption = ( select, fontFamily ) => {
  if ( ! select || ! fontFamily ) {
    return;
  }

  const exists = Array.from( select.options ).some( option => option.value === fontFamily );
  if ( exists ) {
    return;
  }

  const option = document.createElement( 'option' );
  option.value = fontFamily;
  option.textContent = fontFamily;
  select.appendChild( option );
};
