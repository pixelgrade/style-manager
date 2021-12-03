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
      ${ getPaletteCSS( palette ) }
      }
      ${ paletteShiftedSelector } {
      ${ getPaletteCSS( palette, true ) }
      }
    `;
  }, '');
}

const getPaletteCSS = ( palette, shifted = false ) => {
  let offset = shifted ? palette.sourceIndex : 0;

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

const getApplyPaletteVariables = ( id, suffix = '' ) => {
  let output = '';

  for ( let i = 1; i <= 12; i++ ) {
    output += `--sm-bg-color-${ i }: var(--sm-color-palette-${ id }-bg-color-${ i }${ suffix }); \n`
    output += `--sm-accent-color-${ i }: var(--sm-color-palette-${ id }-accent-color-${ i }${ suffix }); \n`
    output += `--sm-fg1-color-${ i }: var(--sm-color-palette-${ id }-fg1-color-${ i }${ suffix }); \n`
    output += `--sm-fg2-color-${ i }: var(--sm-color-palette-${ id }-fg2-color-${ i }${ suffix }); \n`
  }

  return output;
}

const getInitialColorVaraibles = ( palette ) => {
  const { colors, textColors, id } = palette;
  const prefix = '--sm-color-palette-';

  let accentColors = colors.reduce( ( colorsAcc, color, index ) => {
    return `${ colorsAcc }
      ${ prefix }${ id }-color-${ index + 1 }: ${ color.value };
    `;
  }, '' );

  let darkColors = textColors.reduce( ( colorsAcc, color, index ) => {
    return `${ colorsAcc }
      ${ prefix }${ id }-text-color-${ index + 1 }: ${ color.value };
    `;
  }, '' );

  return `
    ${ accentColors }
    ${ darkColors }
  `;
}
