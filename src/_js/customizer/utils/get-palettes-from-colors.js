import { hexToHpluv, hpluvToRgb } from 'hsluv';
import chroma from 'chroma-js';

import {
  contrastToLuminance,
  getColorOptionsDefaults,
  getMinContrast,
  getBestColor,
  contrastArray, getTextColors,
} from "./colors";

const defaultOptions = {
  mode: 'lch',
  bezierInterpolation: false,
  ...getColorOptionsDefaults()
};

export const getPalettesFromColors = ( colorGroups, opts = {}, simple = false ) => {
  const options = Object.assign( {}, defaultOptions, opts );
  const functionalColors = getFunctionalColors( colorGroups );
  const allColors = colorGroups.concat( functionalColors );

  const palettes = allColors.map( mapColorToPalette( options ) )
                            .map( mapAddColors( options ) )
                            .map( mapForceColors( options ) );

  return palettes.map( mapCreateVariations( options, palettes ) )
                 .map( mapAddSourceIndex( options ) );
}

const mapForceColors = ( options ) => {

  return ( palette ) => {
    const sourceColors = palette.source.map( color => ( { value: color } ) );
    const forcedColors = [];

    if ( options.sm_color_promotion_brand ) {
      forcedColors.push( ...sourceColors );
    }

    if ( options.sm_color_promotion_white ) {
      forcedColors.unshift( { value: '#FFFFFF' } );
    }

    if ( options.sm_color_promotion_black ) {
      forcedColors.push( { value: '#000000' } );
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

    return palette;
  }
}

const mapCreateVariations = ( options, allPalettes ) => {

  return ( palette ) => {
    const { colors, darkColors, source } = palette;

    const otherPalettes = allPalettes.filter( thisPalette => {
      const thisId = `${ thisPalette.id }`;
      return thisId !== `${ palette.id }` && thisId.charAt( 0 ) !== '_';
    } );

    palette.variations = getVariationsFromColors( colors, source, options, otherPalettes );
    palette.darkVariations = getVariationsFromColors( darkColors, source, options, otherPalettes );

    return palette;
  }
}

const getVariationsFromColors = ( colors, sources, options, otherPalettes = [] ) => {
  const newContrastArray = getNewContrastArray( colors );
  const mycolors = colors.slice();
  const grays = newContrastArray.map( contrast => chroma( '#FFFFFF' ).luminance( contrastToLuminance( contrast ) ) );

  // make sure palette colors are used at lease once
  // remove grays that are similar in luminance with the variationColor
  mycolors.forEach( color => {
    grays.sort( ( g1, g2 ) => {
      return chroma.contrast( g1, color.value ) - chroma.contrast( g2, color.value )
    } );

    grays.shift();
  } );

  grays.forEach( gray => {

    mycolors.sort( ( v1, v2 ) => {
      const contrast1 = chroma.contrast( v1.value, gray );
      const contrast2 = chroma.contrast( v2.value, gray );
      return contrast1 - contrast2;
    } );

    mycolors.push( mycolors[0] );

  } );

  mycolors.sort( ( v1, v2 ) => {
    return chroma( v2.value ).luminance() - chroma( v1.value ).luminance();
  } );

  return mycolors.map( mycolor => getVariation( colors, sources, mycolor, options, otherPalettes ) );
}

const getNewContrastArray = ( colors ) => {
  const mycolors = colors.slice();
  const contrasts = mycolors.map( c => chroma.contrast( '#FFFFFF', c.value ) );
  const minContrast = Math.min( ...contrasts );
  const maxContrast = Math.max( ...contrasts );
  const prevMin = 1;
  const prevMax = contrastArray[ contrastArray.length - 1 ];

  return contrastArray.map( contrast => {
    return minContrast + maxContrast * ( contrast - prevMin ) / ( prevMax - prevMin );
  } );
}

const getVariation = ( colors, sources, color, options, otherPalettes = [] ) => {
  const darkerContrast = getMinContrast( options );
  const darkContrast = getMinContrast( options, true );
  const background = color.value;
  const accent = getBestAccentColor( background, colors, sources );
  const textReference = ( accent && chroma.contrast( accent, '#FFFFFF' ) > 1 ) ? accent : background;
  const textColors = getTextColors( textReference );
  const darker = getBestColor( background, textColors, darkerContrast, true );
  const dark = getBestColor( background, textColors, darkContrast, true );
  const fg1 = darker;
  const fg2 = chroma.contrast( darker, dark ) > contrastArray[4] ? darker : dark;

  const variationConfig = {
    bg: background,
    accent: accent || fg2,
    fg1: fg1,
    fg2: fg2,
  }

  otherPalettes.forEach( ( otherPalette, index ) => {
    const key = `accent${ index + 2 }`;
    const otherAccent = getBestAccentColor( background, otherPalette.colors, otherPalette.source );
    variationConfig[ key ] = otherAccent || fg2;
  } );

  return variationConfig;
}

const getBestAccentColor = ( background, colors, sources ) => {
  const accentContrast = 2.5;
  const accentColorOptions = colors.slice().map( color => color.value );
  const sourceColors = sources.slice();

  return getBestColor( background, accentColorOptions, accentContrast );;
}

const mapAddSourceIndex = ( options ) => {

  return ( palette, index, palettes ) => {
    const source = palette.source[0];
    const colors = palette.variations.map( variation => variation.bg );
    const sourceIndex = getBestPositionInPalette( source, colors, options );

    return {
      sourceIndex,
      ...palette
    };
  }
}

const mapColorToPalette = ( ( options ) => {

  return ( groupObject, index ) => {

    const colorObjects = groupObject.sources;
    const sources = colorObjects.map( colorObj => colorObj.value );

    const { label, id } = colorObjects[0];

    return {
      id: id || ( index + 1 ),
      label: label,
      source: sources,
    };
  }
} );


const mapAddColors = options => {

  return palette => {

    const darkOptions = Object.assign( {}, options, {
      sm_potential_color_contrast: Math.min( 0.25, options.sm_potential_color_contrast ),
      sm_color_grade_balancer: 1,
      sm_color_grades_number: options.sm_color_grades_number,
    } );

    palette.colors = createAutoPalette( palette.source, options ).map( color => ( { value: color } ) );
    palette.darkColors = createAutoPalette( palette.source, darkOptions ).map( color => ( { value: color } ) );

    return palette;
  }
}

const getBestPositionInPalette = ( color, colors, attributes ) => {
  const mycolors = colors.map( ( color, index ) => ( { color, index } ) );
  mycolors.sort( ( c1, c2 ) => chroma.contrast( c1.color, color ) - chroma.contrast( c2.color, color ) );
  return mycolors[0].index;
}

const createAutoPalette = ( colors, options = {} ) => {
  const width = parseFloat( options.sm_potential_color_contrast );
  const center = parseFloat( options.sm_color_grade_balancer );
  const count = parseInt( options.sm_color_grades_number, 10 );

  const { mode, bezierInterpolation } = options;
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