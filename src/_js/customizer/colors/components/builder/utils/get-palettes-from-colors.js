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
               .filter( mapCreateVariations( options ) );
}

const mapCreateVariations = ( options ) => {

  return ( palette ) => {

    let colors = palette.colors.slice();

    const white = { value: '#FFFFFF' };
    const light = getAverageColor( palette, 1, 3 );
    const color = getAverageColor( palette, 4, 4 );
    const shade = getAverageColor( palette, 8, 3 );
    const black = getAverageColor( palette, 11, 1 );
    const sourceColors = palette.source.map( color => ( { value: color, isSource: true } ) );

    if ( options[ 'simple-palettes' ] ) {

      colors = [];

      if ( options[ 'force-source' ] ) {
        colors.push( ...sourceColors );
      }

      if ( options[ 'force-white' ] ) {
        colors.push( white );
      }

      if ( options[ 'force-tints' ] ) {
        colors.push( light );
      }

      if ( options[ 'force-color' ] ) {
        colors.push( color );
      }

      if ( options[ 'force-shades' ] ) {
        colors.push( shade );
      }

      if ( options[ 'force-black' ] ) {
        colors.push( black );
      }

      if ( colors.length < 1 ) {
        colors.push( ...sourceColors );
      }
    }

    colors = colors.filter( ( variation, index, self ) => {
      return self.findIndex( compare => variation.value === compare.value ) === index;
    } );

    colors.sort( ( c1, c2 ) => chroma( c2.value ).luminance() - chroma( c1.value ).luminance() );

    palette.colors = colors;

    addVariationsToPalette( palette, options );

    return palette;
  }
}

const addVariationsToPalette = ( palette, options ) => {
  const mycolors = getFilledColors( palette.colors.slice() );

  palette.variations = mycolors.map( mycolor => {
    return {
      background: mycolor.value,
      accent: getAccentHex( palette, mycolor, options ),
      foreground1: getTextHex( palette, mycolor, options ),
      foreground2: getTextHex( palette, mycolor, options, true ),
    }
  } )
}

const getFilledColors = ( colors ) => {
  const white = chroma( '#FFFFFF' );
  const mycolors = colors.slice();
  const output = [];

  const grays = contrastArray.map( contrast => {
    const luminance = contrastToLuminance( contrast );
    return white.luminance( luminance );
  } );

  grays.forEach( ( gray, index ) => {

    mycolors.sort( ( v1, v2 ) => {
      const contrast1 = chroma.contrast( v1.value, gray );
      const contrast2 = chroma.contrast( v2.value, gray );
      return contrast1 - contrast2;
    } );

    output[ index ] = mycolors[0];

  } );

  output.sort( ( v1, v2 ) => {
    const contrast1 = chroma.contrast( white, v1.value );
    const contrast2 = chroma.contrast( white, v2.value );
    return contrast1 - contrast2;
  } );

  return output;
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

const getAverageColor = ( palette, start, length, sourceIndex ) => {
  const { source } = palette;
  const colors = palette.colors.slice(start, start + length);
  let midIndex = Math.ceil( length * 0.5 ) - 1;
  let srcIndex = colors.findIndex( color => source.some( mycolor => chroma.contrast( mycolor, color.value ) === 1 ) );

  let index = srcIndex > -1 ? srcIndex : midIndex;

  return palette.colors[ index + start ];
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

    source.forEach( mycolor => {
      const position = getBestPositionInPalette( mycolor, palette.colors.map( color => color.value ), attributes );
      palette.colors[position] = {
        value: mycolor,
        isSource: true
      }
    } )

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

const getMinContrast = ( options, largeText = false ) => {

  if ( options[ 'wcag-aaa' ] ) {
    return largeText ? 4.5 : 7;
  }

  if ( options[ 'wcag-aa' ] ) {
    return largeText ? 3 : 4.5;
  }

  return contrastArray[4];
}

const getAccentHex = ( palette, color, options ) => {
  const colors = palette.colors.slice();
  const minContrast = getMinContrast( options );

  colors.push( { value: getTextHex( palette, color ) } );
  colors.push( { value: getTextHex( palette, color, options, true ) } );

  // otherwise search in the current palette the color that can provide the closest contrast to 4.5 (WCAG AAA)
  colors.sort( ( color1, color2 ) => color1.isSource ? -1 : 0 );

  const bestIndex = colors.findIndex( mycolor => chroma.contrast( mycolor.value, color.value ) > minContrast );

  if ( bestIndex < 0 ) {
    return colors[ colors.length - 1 ].value;
  }

  return colors[ bestIndex ].value;
}

const getTextHex = ( palette, color, options, darker = false ) => {
  const addon = darker ? 1 : 0;
  const dark = getTextDarkColorFromSource( palette, 9 + addon );
  const white = '#FFFFFF';
  const contrastOnWhite = chroma.contrast( white, color.value );
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

