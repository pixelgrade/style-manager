import ReactDOM from "react-dom";

export * from './colors';

export { apiSetSettingValue } from './api-set-setting-value';
export { getCSSFromPalettes } from './get-css-from-palettes';
export { getPalettesFromColors } from './get-palettes-from-colors';
export { getColorsFromInputValue } from './get-colors-from-input-value';
export { maybeFillPalettesArray } from './maybe-fill-palettes-array';

export const insertShortcutAfter = ( id, Component ) => {
  const element = document.getElementById( id );

  if ( ! element ) {
    return;
  }

  const button = document.createElement( 'li' );

  button.setAttribute( 'class', 'customize-control' );
  button.setAttribute( 'style', 'padding: 0' );

  element.insertAdjacentElement( 'afterend', button );

  ReactDOM.render( <Component />, button );
}
