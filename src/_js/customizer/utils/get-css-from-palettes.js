import { CONTRAST_FLOOR_ROLES, getContrastFloorRoleColors } from '../../shared/contrast-floor.js';

export const getCSSFromPalettes = ( palettesArray, variation = 1 ) => {

  const palettes = palettesArray.slice();

  if ( ! palettes.length ) {
    return '';
  }

  // the old implementation generates 3 fallback palettes and
  // we need to overwrite all 3 of them when the user starts building a new palette
  // @todo this is necessary only in the Customizer preview
  while ( palettes.length < 3 ) {
    palettes.push( palettes[0] );
  }

  return palettes.reduce( ( palettesAcc, palette, paletteIndex, palettes ) => {
    const { id } = palette;
    let paletteSelector = `.sm-palette-${ id }`;
    let darkPaletteSelector = `.is-dark .sm-palette-${ id }`;
    let paletteShiftedSelector = `.sm-palette-${ id }.sm-palette--shifted`;

    if ( id.toString() === '1' ) {
      paletteSelector = `html, ${ paletteSelector }`;
      darkPaletteSelector = `html.is-dark, ${ darkPaletteSelector }`;
    }

    return `
      ${ palettesAcc }
      ${ paletteSelector } {
        ${ getVariationsCSS( palette.variations, variation - 1 ) }
      }
      ${ darkPaletteSelector } {
        ${ getVariationsCSS( palette.darkVariations, variation - 1 ) }
      }
      ${ paletteShiftedSelector } {
        ${ getVariationsCSS( palette.variations, palette.sourceIndex ) }
      }
    `;
  }, '');
};

const getVariationsCSS = ( variations, offset ) => {

  return `
        ${ variations.reduce( ( variationsAcc, value, index ) => `
            ${ variationsAcc }
            ${ getVariationCSS( variations, index, offset ) }  
        `, '' ) }
        `
};

const getVariationCSS = ( variations, index, offset ) => {
  const variation = variations[ ( index + offset ) % 12 ];

  // Contrast-floor roles (the quiet-text `fg-muted`, style-manager#214) are always derived
  // from the variation's own colours, so a stored value can never bypass the floor.
  const stored = Object.keys( variation ).filter( key => ! Object.prototype.hasOwnProperty.call( CONTRAST_FLOOR_ROLES, key ) );
  const derived = getContrastFloorRoleColors( variation );

  const storedCSS = stored.reduce( ( acc, key ) => {
    return `${ acc }
--sm-${ key }-color-${ index + 1 }: ${ variation[ key ] };`
  }, '' );

  return Object.keys( derived ).reduce( ( acc, key ) => {
    return `${ acc }
--sm-${ key }-color-${ index + 1 }: ${ derived[ key ] };`
  }, storedCSS );

};

export default getCSSFromPalettes;
