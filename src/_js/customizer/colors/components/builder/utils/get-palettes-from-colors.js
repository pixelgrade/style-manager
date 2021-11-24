import { hexToHpluv, hpluvToRgb } from 'hsluv';
import chroma from 'chroma-js';
import {
  contrastToLuminance,
  getTextDarkColorFromSource
} from "../../../utils";
import contrastArray from "./contrast-array";

const defaultOptions = {
  correctLightness: true,
  useSources: true,
  mode: 'lch',
  bezierInterpolation: false,
};

export const getPalettesFromColors = ( colorGroups, opts = {}, simple = false ) => {
  const options = Object.assign( {}, defaultOptions, opts );
  const functionalColors = getFunctionalColors( colorGroups );
  const palettes = colorGroups.map( mapColorToPalette( options ) );
  const functionalPalettes = functionalColors.map( mapColorToPalette( options ) );
  const allPalettes = palettes.concat( functionalPalettes );

  return mapSanitizePalettes( allPalettes, options, simple );
}

const noop = palette => palette;

const mapSanitizePalettes = ( colors, options = {}, simple ) => {
  return colors.map( mapCorrectLightness( options ) )
               .map( mapUpdateProps )
               .map( mapUseSource( options ) )
               .map( mapAddSourceIndex( options ) )
               .map( mapCreateVariations( options ) );
}

const mapCreateVariations = ( options ) => {

  return ( palette ) => {

    const { colors, sourceIndex } = palette;

    let colorsArray = colors.map( color => color.value );

    if ( options[ 'simple-palettes' ] ) {

      colorsArray = [];

      const white = '#FFFFFF';
      const light = getAverage( colors, 1, 3, sourceIndex ).value;
      const color = getAverage( colors, 4, 4, sourceIndex ).value;
      const shade = getAverage( colors, 8, 3, sourceIndex ).value;
      const black = getAverage( colors, 11, 1, sourceIndex ).value;
      const source = colors[sourceIndex].value;

      if ( options[ 'force-white' ] ) {
        colorsArray.push( white );
      }

      if ( options[ 'force-tints' ] ) {
        colorsArray.push( light );
      }

      if ( options[ 'force-color' ] ) {
        colorsArray.push( color );
      }

      if ( options[ 'force-shades' ] ) {
        colorsArray.push( shade );
      }

      if ( options[ 'force-black' ] ) {
        colorsArray.push( black );
      }

      if ( options[ 'force-source' ] ) {
        colorsArray.push( source );
      }

      colorsArray = colorsArray.filter( ( value, index, self ) => {
        return self.indexOf( value ) === index;
      } );

      if ( colorsArray.length < 1 ) {
        colorsArray.push( source );
      }
    }

    let workingColors = contrastArray.map( contrast => {
      let colorToAdd = colorsArray[0];
      let minContrast = 21;

      colorsArray.forEach( color => {
        const contrastOnWhite = chroma.contrast( color, '#FFFFFF' );
        const contrastToCompare = Math.max( contrast, contrastOnWhite ) / Math.min( contrast, contrastOnWhite );

        if ( contrastToCompare < minContrast ) {
          minContrast = contrastToCompare;
          colorToAdd = color;
        }
      } );

      return colorToAdd;
    } );

    palette.variations = workingColors.map( ( color, index ) => {
      return {
        background: color,
        accent: getAccentColor( palette, workingColors, index ),
        foreground1: getTextColor( palette, color ),
        foreground2: getTextColor( palette, color, true ),
      }
    } );

    return palette;
  }
}

const mapAddTextColors = ( palette ) => {

  palette.textColors = palette.colors.slice( 9, 11 ).map( ( color, index ) => {
    return {
      ...color,
      value: getTextDarkColorFromSource( palette, 9 + index ),
    }
  } );

  return palette;
}

const mapAddSourceIndex = ( attributes ) => {

  return ( palette, index, palettes ) => {
    const { source, colors } = palette;
    let sourceIndex = getSourceIndex( palette );

    // falback sourceIndex when the source isn't used in the palette
    if ( ! sourceIndex > -1 ) {
      sourceIndex = getBestPositionInPalette( source[0], colors.map( color => color.value ), attributes );
    }

    return {
      sourceIndex,
      ...palette
    };
  }
}

const mapColorToPalette = ( ( attributes ) => {

  return ( groupObject, index ) => {

    const colorObjects = groupObject.sources;
    const sources = colorObjects.map( colorObj => colorObj.value );
    const colors = createAutoPalette( sources, attributes );

    const { label, id } = colorObjects[0];

    return {
      id: id || ( index + 1 ),
      lightColorsCount: 5,
      label: label,
      source: sources,
      colors: colors,
    };
  }
} );

const getAverage = ( colors, start, length, sourceIndex ) => {
  let colorIndex = Math.ceil( length * 0.5 ) + start - 1;

  if ( start <= sourceIndex && start + length > sourceIndex ) {
    colorIndex = sourceIndex;
  }

  let { isSource, ...color } = colors[ colorIndex ];

  return color;
}

const mapCorrectLightness = ( { correctLightness, mode } ) => {

  if ( ! correctLightness ) {
    return noop;
  }

  return ( palette ) => {
    palette.colors = palette.colors.map( ( color, index ) => {
      const luminance = contrastToLuminance( contrastArray[ index ] );
      return chroma( color ).luminance( luminance, 'rgb' ).hex();
    } );
    return palette;
  }
}

const mapUpdateProps = ( palette ) => {
  palette.colors = palette.colors.map( ( color, index ) => {
    return Object.assign( {}, {
      value: color,
    } )
  } );

  return palette;
}

const mapUseSource = ( attributes ) => {
  const { useSources } = attributes;

  if ( ! useSources ) {
    return noop;
  }

  return ( palette ) => {
    const { source } = palette;
    const position = getBestPositionInPalette( source[0], palette.colors.map( color => color.value ), attributes );

    palette.colors.splice( position, 1, {
      value: source[0],
      isSource: true
    } );

    return palette;
  }
}

const getSourceIndex = ( palette ) => {
  return palette.colors.findIndex( color => color.value === palette.source )
}

const getBestPositionInPalette = ( color, colors, attributes, byColorDistance ) => {
  let min = Number.MAX_SAFE_INTEGER;
  let pos = -1;

  for ( let i = 0; i < colors.length - 1; i++ ) {
    let distance;

    if ( !! byColorDistance ) {
      distance = chroma.distance( colors[i], color, 'rgb' );
    } else {
      distance = Math.abs( chroma( colors[i] ).luminance() - chroma( color ).luminance() );
    }

    if ( distance < min ) {
      min = distance;
      pos = i;
    }
  }

  return pos;
}

const getAccentColor = ( palette, workingColors, index ) => {
  const { sourceIndex } = palette;
  const colors = workingColors.slice();
  const color = colors[ index ];
  const source = palette.colors[sourceIndex].value;
  const sourceContrast = chroma.contrast( source, color );

  // if the current palette source color has a contrast of at least 3 (WCAG AA) use that
  if ( sourceContrast >= 3 ) {
    return source;
  }

  // otherwise search in the current palette the color that can provide the closest contrast to 4.5 (WCAG AAA)
  colors.sort( ( color1, color2 ) => {
    const c1 = chroma.contrast( color1, color );
    const c2 = chroma.contrast( color2, color );
    const abs1 = Math.max( 4.5, c1 ) / Math.min( 4.5, c1 );
    const abs2 = Math.max( 4.5, c2 ) / Math.min( 4.5, c2 );
    return abs1 - abs2;
  } );

  const accentColor = colors[0];

  if ( accentColor === color ) {
    return getTextColor( palette, color );
  }

  return accentColor;
}

const getTextColor = ( palette, background, darker = false ) => {
  const addon = darker ? 1 : 0;
  const dark = getTextDarkColorFromSource( palette, 9 + addon );
  const white = '#FFFFFF';
  const contrastOnWhite = chroma.contrast( white, background );
  const contrastOnDark = contrastArray[9] / contrastOnWhite;

  if ( 4.5 > contrastOnDark ) {
    return white;
  }

  return dark;
}

const createAutoPalette = ( colors, attributes = {} ) => {
  const { mode, bezierInterpolation } = attributes;
  const newColors = colors.slice();

  newColors.splice( 0, 0, '#FFFFFF' );
  newColors.push( '#000000' );
  newColors.sort( ( c1, c2 ) => {
    return ( chroma( c1 ).luminance() > chroma( c2 ).luminance() ) ? -1 : ( ( chroma( c1 ).luminance() < chroma( c2 ).luminance() ) ? 1 : 0 );
  } );

  if ( !! bezierInterpolation ) {
    return chroma.bezier( newColors ).scale().mode( mode ).correctLightness().colors( 12 );
  } else {
    return chroma.scale( newColors ).mode( mode ).correctLightness().colors( 12 );
  }
}

const blend = ( functionalColor, brandColor, ratio = 1 ) => {
  const l1 = chroma( functionalColor ).get( 'hsl.s' );
  const l2 = chroma( brandColor ).get( 'hsl.s' );
  const l3 = l1 * ( 1 - 0.8 * ratio ) + l2 * 0.8 * ratio;

  return chroma( functionalColor ).mix( brandColor, 0.1 * ratio ).set( 'hsl.s', l3 ).hex();
}

export const getFunctionalColors = ( colorGroups ) => {

  if ( ! colorGroups?.length || ! colorGroups[0]?.sources?.length ) {
    return [];
  }

  const color = colorGroups[0].sources[0].value;
  const blue = blend( '#2E72D2', color );
  const red = blend( '#D82C0D', color );
  const yellow = blend( '#FFCC00', color, 0.5 );
  const green = blend( '#00703c', color, 0.75 );

  return [
    { sources: [ { value: blue, label: 'Info', id: '_info' } ] },
    { sources: [ { value: red, label: 'Error', id: '_error' } ] },
    { sources: [ { value: yellow, label: 'Warning', id: '_warning' } ] },
    { sources: [ { value: green, label: 'Success', id: '_success' } ] },
  ];
}

