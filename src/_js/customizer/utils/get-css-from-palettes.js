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
    let paletteShiftedSelector = `.sm-palette-${ id }.sm-palette--shifted`;

    if ( id.toString() === '1' ) {
      paletteSelector = `html, ${ paletteSelector }`;
    }

    return `
      ${ palettesAcc }
      ${ paletteSelector } {
      ${ getPaletteCSS( palette, variation - 1 ) }
      }
      ${ paletteShiftedSelector } {
      ${ getPaletteCSS( palette, palette.sourceIndex ) }
      }
    `;
  }, '');
}

const getPaletteCSS = ( palette, offset ) => {

  return `
        ${ palette.variations.reduce( ( variationsAcc, value, index ) => `
            ${ variationsAcc }
            ${ getVariationCSS( palette, index, offset ) }  
        `, '' ) }
        `
}

const getVariationCSS = ( palette, index, offset ) => {
  const variation = palette.variations[ ( index + offset ) % 12 ];

  return `
        --sm-bg-color-${ index + 1 }: ${ variation.background };
        --sm-accent-color-${ index + 1 }: ${ variation.accent };
        --sm-fg1-color-${ index + 1 }: ${ variation.foreground1 };
        --sm-fg2-color-${ index + 1 }: ${ variation.foreground2 };
  `;

}

export default getCSSFromPalettes;
