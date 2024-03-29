import { hexToHpluv, hpluvToRgb } from 'hsluv';
import chroma from 'chroma-js';

import {
  myArray,
  getBestColor,
  getColorOptionsDefaults,
  getMinContrast,
  getTextColors,
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

  return allColors.map( mapColorToPalette( options ) )
                  .map( mapAddColors )
                  .map( mapForceColors )
                  .map( mapCreateVariations )
                  .map( mapAddSourceIndex );
};

const mapForceColors = ( palette ) => {
  const { options, source } = palette;
  const forcedColors = [];

  if ( options.sm_color_promotion_brand ) {
    forcedColors.push( ...source );
  }

  const uniqueForcedColors = forcedColors.filter( ( color, index, self ) => {
    return self.findIndex( compare => color === compare ) === index;
  } );

  uniqueForcedColors.forEach( color => {
    palette.colors.sort( ( c1, c2 ) => chroma.contrast( c2, color ) - chroma.contrast( c1, color ) );
    palette.colors.pop();
  } );

  palette.colors.push( ...uniqueForcedColors );
  palette.colors.sort( ( c1, c2 ) => chroma( c2 ).luminance() - chroma( c1 ).luminance() );

  return palette;
};

const mapCreateVariations = ( palette, index, palettes ) => {

  const { colors, darkColors, source, options } = palette;

  const otherPalettes = palettes.filter( thisPalette => {
    const thisId = `${ thisPalette.id }`;
    return `${palette.id}` !== thisId && '_' !== thisId.charAt( 0 );
  } );

  palette.variations = getVariationsFromColors( colors, source, options, otherPalettes );
  palette.darkVariations = getVariationsFromColors( darkColors, source, options, otherPalettes );

  return palette;
};

const getVariationsFromColors = ( colors, sources, options, otherPalettes = [] ) => {
  const scale = chroma.scale( colors ).classes( colors.length );
  return scale.colors( 12 )
              .map( mycolor => getVariation( colors, sources, mycolor, options, otherPalettes ) );
};

const isWhite = ( hex ) => {
  return chroma.contrast( hex, '#FFFFFF' ) === 1;
};

const getVariation = ( colors, sources, color, options, otherPalettes = [] ) => {
  const darkerContrast = getMinContrast( options );
  const darkContrast = getMinContrast( options, true );
  const background = color;
  const accent = getBestAccentColor( background, colors, sources, options );
  const textReference = ( accent && ! isWhite( accent ) ) ? accent : background;
  const textColors = getTextColors( textReference );
  const dark = getBestColor( background, textColors, darkContrast, true );

  const darkerTextColors = textColors.filter( color => color !== dark || isWhite( color ) );
  const darker = getBestColor( background, darkerTextColors, darkerContrast, true );
  const fg1 = darker;
  // if there's great contrast between dark and darker, darker is probably white
  const fg2 = chroma.contrast( darker, dark ) >= getMinContrast() ? darker : dark;

  const variationConfig = {
    bg: background,
    accent: accent || fg2,
    fg1: fg1,
    fg2: fg2,
  };

  otherPalettes.forEach( ( otherPalette, index ) => {
    const key = `accent${ index + 2 }`;
    const otherAccent = getBestAccentColor( background, otherPalette.colors, otherPalette.source, options );
    variationConfig[ key ] = otherAccent || fg2;
  } );

  return variationConfig;
};

const getBestAccentColor = ( background, colors, sources, options = {} ) => {
  const accentContrast = 'maximum' !== options.sm_elements_color_contrast ? 2.5 : getMinContrast( options, true );
  const accentColorOptions = colors.slice().map( color => color );

  accentColorOptions.unshift( ...sources );

  return getBestColor( background, accentColorOptions, accentContrast );
};

const mapAddSourceIndex = ( palette ) => {
  const { source, options } = palette;

  const colors = palette.variations.map( variation => variation.bg );
  const sourceIndex = getBestPositionInPalette( source[0], colors, options );

  return {
    sourceIndex,
    ...palette
  };
};

const mapColorToPalette = ( ( options ) => {

  return ( groupObject, index ) => {

    const colorObjects = groupObject.sources;
    const sources = colorObjects.map( colorObj => colorObj.value );

    const { label, id } = colorObjects[0];

    return {
      id: id || ( index + 1 ),
      label: label,
      source: sources,
      options: options,
      darkOptions: Object.assign( {}, options, {
        sm_potential_color_contrast: Math.min( 0.25, options.sm_potential_color_contrast ),
        sm_color_grade_balancer: 1,
        sm_color_grades_number: options.sm_color_grades_number,
        sm_color_promotion_brand: true,
        sm_color_promotion_white: false,
        sm_color_promotion_black: true,
      } )
    };
  }
} );


const mapAddColors = palette => {

  const { options, darkOptions } = palette;

  palette.colors = createAutoPalette( palette.source, options );
  palette.darkColors = createAutoPalette( palette.source, darkOptions );

  return palette;
};

const getBestPositionInPalette = ( color, colors ) => {
  const mycolors = colors.map( ( color, index ) => ( { color, index } ) );
  mycolors.sort( ( c1, c2 ) => chroma.contrast( c1.color, color ) - chroma.contrast( c2.color, color ) );
  return mycolors[0].index;
};

const createAutoPalette = ( colors, options = {} ) => {
  const width = parseFloat( options.sm_potential_color_contrast );
  const center = parseFloat( options.sm_color_grade_balancer );
  const count = parseInt( options.sm_color_grades_number, 10 );

  const { mode, bezierInterpolation } = options;
  const newColors = colors.slice();

  if ( options.sm_color_promotion_white ) {
    newColors.unshift( '#FFFFFF' );
  }

  if ( options.sm_color_promotion_black ) {
    newColors.push( '#000000' );
  }

  newColors.sort( ( hex1, hex2 ) => chroma( hex2 ).luminance() - chroma( hex1 ).luminance() );

  const scale = chroma.scale( newColors ).correctLightness();

  let paddingLeft = ( 1 - width ) * ( center * 0.5 + 0.5 );
  let paddingRight = ( 1 - width ) * ( 0.5 - center * 0.5 );

  scale.padding( [ paddingLeft, paddingRight ] );

  const tempColors = myArray.map( position => scale( position ).hex() );

  return chroma.scale( tempColors ).colors( count );
};

const blend = ( functionalColor, brandColor, ratio = 1 ) => {
  const l1 = chroma( functionalColor ).get( 'hsl.s' );
  const l2 = chroma( brandColor ).get( 'hsl.s' );
  const l3 = l1 * ( 1 - 0.8 * ratio ) + l2 * 0.8 * ratio;

  return chroma( functionalColor ).mix( brandColor, 0.1 * ratio ).set( 'hsl.s', l3 ).hex();
};

export const getFunctionalColors = ( colorGroups ) => {

  if ( ! colorGroups?.length || ! colorGroups[0]?.sources?.length ) {
    return [];
  }

  const hex = colorGroups[0].sources[0].value;
  const blue = blend( '#2E72D2', hex );
  const red = blend( '#D82C0D', hex );
  const yellow = blend( '#FFCC00', hex, 0.5 );
  const green = blend( '#00703c', hex, 0.75 );

  return [
    { sources: [ { value: blue, label: 'Info', id: '_info' } ] },
    { sources: [ { value: red, label: 'Error', id: '_error' } ] },
    { sources: [ { value: yellow, label: 'Warning', id: '_warning' } ] },
    { sources: [ { value: green, label: 'Success', id: '_success' } ] },
  ];
};
