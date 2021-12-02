import { hexToHpluv, hpluvToRgb } from 'hsluv';
import chroma from 'chroma-js';
import {
  contrastToLuminance,
  desaturateTextColor,
} from "../../../utils";
import contrastArray from "./contrast-array";

const defaultOptions = {
  correctLightness: true,
  useSources: true,
  mode: 'lch',
  bezierInterpolation: false,

  count: 12,
  center: 0.5,
  width: 1,
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
  return colors
//              .map( mapCorrectLightness( options ) )
               .map( mapUpdateProps )
//               .map( mapUseSource( options ) )
               .map( mapAddSourceIndex( options ) )
               .filter( mapCreateVariations( options ) );
}

const mapCreateVariations = ( options ) => {

  return ( palette ) => {

    const sourceColors = palette.source.map( color => ( { value: color, isSource: true } ) );

    const forcedColors = [];

    if ( options[ 'force-source' ] ) {
      forcedColors.push( ...sourceColors );
    }

    if ( options[ 'force-white' ] ) {
      forcedColors.unshift( { value: '#FFFFFF' } );
    }

    if ( options[ 'force-black' ] ) {
      forcedColors.push( { value: chroma( '#FFFFFF' ).luminance( contrastToLuminance( 19 ) ).hex() } );
    }

    const uniqueForcedColors = forcedColors.filter( ( color, index, self ) => {
      return self.findIndex( compare => color.value === compare.value ) === index;
    } );

    uniqueForcedColors.forEach( color => {
      palette.colors.sort( ( c1, c2 ) => chroma.contrast( c2.value, color.value ) - chroma.contrast( c1.value, color.value ) );
      palette.colors.pop();
    } );

    palette.colors.push( ...uniqueForcedColors );
    palette.colors.sort( ( c1, c2 ) => chroma( c2.value ).luminance() - chroma( c1.value ).luminance() );

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
      foreground2: getTextHex( palette, mycolor, options, 7 ),
    }
  } )
}

const getFilledColors = ( colors ) => {

  return colors;

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
      return chroma( color ).luminance( luminance ).hex();
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

  return contrastArray[3];
}

const getAccentHex = ( palette, color, options ) => {
  const colors = palette.colors.slice();
  const minContrast = getMinContrast( options );
  const sources = palette.source.map( hex => ( { value: hex, isSource: true } ) );
  const textColors = getTextColors( color.value );

  // always add sources and text colors to use as possible accent colors
  colors.unshift( ...sources );
  colors.push( ...textColors.map( hex => ( { value: hex } ) ) );

  const bestIndex = colors.findIndex( mycolor => chroma.contrast( mycolor.value, color.value ) > minContrast );

  if ( bestIndex < 0 ) {
    const sortedColors = colors.slice().sort( ( c1, c2 ) => chroma.contrast( c1.value, color.value ) - chroma.contrast( c2.value, color.value ) );
    return sortedColors[ sortedColors.length - 1 ].value;
  }

  return colors[ bestIndex ].value;
}

const getTextColors = ( hex ) => {

  const textContrastArray = [
    ...contrastArray.slice( 0, 1 ),
    ...contrastArray.slice( -3 )
  ];

  return textContrastArray.map( contrast => {
    const desaturated = desaturateTextColor( hex );
    const luminance = contrastToLuminance( contrast );
    return chroma( desaturated ).luminance( luminance ).hex();
  } );

}

const getTextHex = ( palette, color, options, defaultMinContrast ) => {
  const minContrast = Math.max( getMinContrast( options ), defaultMinContrast );
  const textColors = getTextColors( color.value );

  textColors.sort( ( c1, c2 ) => chroma.contrast( c1, color.value ) - chroma.contrast( c2, color.value ) );

  const bestIndex = textColors.findIndex( mycolor => chroma.contrast( mycolor, color.value ) > minContrast );

  if ( bestIndex < 0 ) {
    const sortedColors = textColors.slice().sort( ( c1, c2 ) => chroma.contrast( c1, color.value ) - chroma.contrast( c2, color.value ) );
    return sortedColors[ sortedColors.length - 1 ];
  }

  return textColors[ bestIndex ];
}

const createAutoPalette = ( colors, options = {} ) => {
  const { count, width, center, mode, bezierInterpolation } = options;
  const newColors = colors.slice();

  newColors.unshift( '#FFFFFF' );
  newColors.push( '#000000' );
  newColors.sort( ( c1, c2 ) => chroma( c2 ).luminance() - chroma( c1 ).luminance() );

  let scale = bezierInterpolation ? chroma.bezier( newColors ).scale() : chroma.scale( newColors );

  scale.correctLightness();

  let paddingLeft = ( 1 - width ) * center;
  let paddingRight = ( 1 - width ) * ( 1 - center );

  scale.padding( [ paddingLeft, paddingRight ] );

  return scale.colors( count );
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

