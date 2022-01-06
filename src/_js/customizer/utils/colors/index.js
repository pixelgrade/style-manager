import { hexToHpluv, hpluvToHex, hpluvToRgb } from "hsluv";
import chroma from "chroma-js";
import { getSettingConfig } from "../../global-service";

export const contrastToLuminance = ( contrast ) => {
  return 1.05 / contrast - 0.05;
}

export const getBestColor = ( background, colors, minContrast, please ) => {
  const bestIndex = colors.findIndex( mycolor => chroma.contrast( mycolor, background ) > minContrast );

  if ( bestIndex > -1 ) {
    return colors[ bestIndex ];
  }

  if ( !! please ) {
    const sortedColors = colors.slice().sort( ( c1, c2 ) => chroma.contrast( c1, background ) - chroma.contrast( c2, background ) );
    return sortedColors[ sortedColors.length - 1 ];
  }

  return false;
}

export const getTextColors = ( hex ) => {

  const textColors = contrastArray.slice( -3 ).map( contrast => {
    const luminance = contrastToLuminance( contrast );
    return desaturateTextColor( hex, luminance );
  } );

  textColors.unshift( '#FFFFFF' );

  return textColors;
}

export const getMinContrast = ( options = {}, largeText = false ) => {

  if ( options.sm_elements_color_contrast === 'maximum' ) {
    return largeText ? 4.5 : 7;
  }

  if ( options.sm_elements_color_contrast === 'average' ) {
    return largeText ? 3 : 4.5;
  }

  return 2.63; // arbitrary value: previously constrastArray[4]
}

export const desaturateTextColor = ( hex, luminance ) => {
  const hpluv = hexToHpluv( hex );

  const h = Math.min( Math.max( hpluv[ 0 ], 0 ), 360 );
  const p = Math.min( Math.max( hpluv[ 1 ], 0 ), 100 );
  const l = Math.min( Math.max( hpluv[ 2 ], 0 ), 100 );

  return chroma( hpluvToHex( [ h, p, l ] ) ).luminance( luminance ).hex();
}

// powers of 21 ^ 1/10 but with small adjustments for the lighter colors
export const contrastArray = [
  1,
  1.07, // 1.32
  1.25, // 1.74
  1.8,  // 2.29
  2.63, // 3.03
  3.99,

  5.26,
  6.94,
  9.15,
  12.07, // fg1
  15.92, // fg2
  19 // almost black (21)
];

export const myArray = [
  0,
  0.0335,
  0.1046,
  0.2594,
  0.3975,
  0.5356,
  0.6151,
  0.6904,
  0.7657,
  0.8410,
  0.9247,
  1
]

export const getColorOptionsIDs = () => {
  return [
    'sm_color_grades_number',
    'sm_potential_color_contrast',
    'sm_color_grade_balancer',
    'sm_site_color_variation',
    'sm_elements_color_contrast',
    'sm_color_promotion_brand',
    'sm_color_promotion_white',
    'sm_color_promotion_black',
  ];
}

export const getColorOptionsDefaults = () => {
  const settingsIDs = getColorOptionsIDs();
  const defaults = {};

  settingsIDs.forEach( settingID => {
    const config = getSettingConfig( settingID );
    defaults[ settingID ] = config.default;
  } );

  return defaults;
}
