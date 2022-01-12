import { getSettingConfig } from "../../global-service";
import { standardizeNumericalValue } from './standardize-numerical-value';
import { round } from './round';

const applyFontSizeInterval = ( fontData, fontsLogic, connectedSettingData, fontSizeInterval ) => {

  if ( ! fontSizeInterval ) {
    return;
  }

  const ab = fontSizeInterval;
  const cd = fontsLogic?.font_size_interval;

  if ( ! Array.isArray( ab ) || ! Array.isArray( cd ) || cd[0] >= cd[1] ) {
    return;
  }

  const fontSize = connectedSettingData?.font_size?.value;

  if ( !! fontSize ) {

    if ( ab[1] === ab[0] ) {
      fontData.font_size.value = Math.max( cd[0], Math.min( cd[1], fontSize ) );
    } else {
      const newFontSize = ( fontSize - ab[0] ) * ( cd[1] - cd[0] ) / ( ab[1] - ab[0] ) + cd[0];
      fontData.font_size.value = Math.round( newFontSize * 10 ) / 10;
    }
  }
}

const applyFontSizeMultiplier = ( fontData, fontSizeMultiplier ) => {

  if ( typeof fontSizeMultiplier === "undefined" ) {
    return
  }

  let multiplier = parseFloat( fontSizeMultiplier );
  multiplier = multiplier <= 0 ? 1 : multiplier;

  fontData.font_size.value = round( parseFloat( fontData.font_size.value ) * multiplier, styleManager.fonts.floatPrecision )
}

// The line height is determined by getting the value of the polynomial function determined by points.
export const applyFontStyleIntervals = ( newFontData, fontsLogic ) => {
  // The font variant, letter spacing and text transform all come together from the font styles (intervals).
  // We just need to find the one that best matches the connected field given font size (if given).
  // Please bear in mind that we expect the font logic styles to be preprocessed, without any overlapping and using numerical keys.
  if ( Array.isArray( fontsLogic.font_styles_intervals ) && fontsLogic.font_styles_intervals.length > 0 ) {
    let idx = 0;

    while ( idx < fontsLogic.font_styles_intervals.length - 1 &&
            typeof fontsLogic.font_styles_intervals[ idx ].end !== 'undefined' &&
            fontsLogic.font_styles_intervals[ idx ].end <= newFontData.font_size.value ) {

      idx++;
    }

    // We will apply what we've got.
    if ( !_.isEmpty( fontsLogic.font_styles_intervals[ idx ].font_variant ) ) {
      newFontData[ 'font_variant' ] = fontsLogic.font_styles_intervals[ idx ].font_variant
    }
    if ( !_.isEmpty( fontsLogic.font_styles_intervals[ idx ].letter_spacing ) ) {
      newFontData[ 'letter_spacing' ] = standardizeNumericalValue( fontsLogic.font_styles_intervals[ idx ].letter_spacing )
    }
    if ( !_.isEmpty( fontsLogic.font_styles_intervals[ idx ].text_transform ) ) {
      newFontData[ 'text_transform' ] = fontsLogic.font_styles_intervals[ idx ].text_transform
    }

//    applyFontSizeMultiplier( newFontData, fontsLogic.font_styles_intervals[ idx ].font_size_multiplier );
  }
}

export const applyLineHeight = ( newFontData, fontsLogic ) => {
  if ( Array.isArray( fontsLogic.font_size_to_line_height_points ) ) {
    const result = regression.logarithmic( fontsLogic.font_size_to_line_height_points, { precision: styleManager.fonts.floatPrecision } );
    const lineHeight = result.predict( newFontData[ 'font_size' ].value )[ 1 ];
    newFontData[ 'line_height' ] = standardizeNumericalValue( lineHeight );
  }
}

export const getCallbackFilter = ( connectedSettingID, connectedSetting, fontsLogic, fontSizeInterval ) => {

  /* ======================
   * Process the font logic to get the value that should be applied to the connected (font) fields.
   *
   * The font logic is already in the new value - @see setFieldFontsLogicConfig()
   */
  const newFontData = {};

  if ( typeof fontsLogic.reset !== 'undefined' ) {
    return getSettingConfig( connectedSettingID ).default;
  }

  // The font family is straight forward as it comes directly from the parent field font logic configuration.
  if ( typeof fontsLogic.font_family === 'undefined' ) {
    return;
  }

  const connectedSettingData = connectedSetting();

  newFontData[ 'font_family' ] = fontsLogic.font_family
  newFontData[ 'font_size' ] = standardizeNumericalValue( connectedSettingData.font_size );

  applyFontSizeInterval( newFontData, fontsLogic, connectedSettingData, fontSizeInterval );
//  applyFontSizeMultiplier( newFontData, fontsLogic.font_size_multiplier );
  applyFontStyleIntervals( newFontData, fontsLogic, connectedSettingData );
  applyLineHeight( newFontData, fontsLogic );

  return newFontData;
}
