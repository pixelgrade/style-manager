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

    const { id, sourceIndex } = palette;
    const selector = paletteIndex === 0 ? `html, .sm-palette-${ id }` : `.sm-palette-${ id }`;

    return `
      ${ palettesAcc }
      
      ${ selector } {
        ${ palette.colors.sort( ( c1, c2 ) => {
          return chroma( c2.value ).luminance - chroma( c2.value ).luminance( c1 )
    } ).reduce( ( variationsAcc, value, index ) =>{
          return `
            ${ variationsAcc }
            ${ getVariationCSS( palette, index ) }  
          `
        }, '' ) }
      }
    `;
  }, '');
}

const getVariationCSS = ( palette, index ) => {
  const { id, variations } = palette;
  
  const suffix = '';
  const selectorPrefix = id.toString() !== '1' ? `.sm-palette-${ id } ` : '';
  const variation = variations[ index ];

  return `
        --sm-bg-color-${ index + 1 }: ${ variation.background }; \n;
        --sm-accent-color-${ index + 1 }: ${ variation.accent }; \n;
        --sm-fg1-color-${ index + 1 }: ${ variation.foreground1 }; \n;
        --sm-fg2-color-${ index + 1 }: ${ variation.foreground2 }; \n;
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

const getVariablesCSS = ( palette, offset = 0, isDark = false, isShifted = false ) => {
  const { colors } = palette;
  const count = colors.length;

  return colors.reduce( ( colorsAcc, color, index ) => {
    let oldColorIndex = ( index + offset ) % count;

    if ( isDark ) {
      if ( oldColorIndex < count / 2 ) {
        oldColorIndex = 11 - oldColorIndex;
      } else {
        return colorsAcc;
      }
    }

    return `${ colorsAcc }
      ${ getColorVariables( palette, index, oldColorIndex, isShifted ) }
    `;
  }, '' );
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

const getColorVariables = ( palette, newColorIndex, oldColorIndex, isShifted ) => {
  const { colors, id, lightColorsCount } = palette;
  const count = colors.length;
  const accentColorIndex = ( oldColorIndex + count / 2 ) % count;
  const prefix = '--sm-color-palette-';
  const suffix = isShifted ? '-shifted' : '';
  const newIndex = parseInt( newColorIndex, 10 ) + 1;

  let accentColors = `
    ${ prefix }${ id }-bg-color-${ newIndex }${ suffix }: var(${ prefix }${ id }-color-${ oldColorIndex + 1 });
    ${ prefix }${ id }-accent-color-${ newIndex }${ suffix }: var(${ prefix }${ id }-color-${ accentColorIndex + 1 });
  `;

  let darkColors = '';

  if ( oldColorIndex < lightColorsCount ) {
    darkColors = `
      ${ prefix }${ id }-fg1-color-${ newIndex }${ suffix }: var(${ prefix }${ id }-text-color-1);
      ${ prefix }${ id }-fg2-color-${ newIndex }${ suffix }: var(${ prefix }${ id }-text-color-2);
    `;
  } else {
    darkColors = `
      ${ prefix }${ id }-fg1-color-${ newIndex }${ suffix }: var(${ prefix }${ id }-color-1);
      ${ prefix }${ id }-fg2-color-${ newIndex }${ suffix }: var(${ prefix }${ id }-color-1);
    `;
  }

  return `
    ${ accentColors }
    ${ darkColors }
  `;
}
