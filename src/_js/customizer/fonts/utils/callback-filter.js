import { standardizeNumericalValue } from './standardize-numerical-value';
import { round } from './round';

const applyFontSizeInterval = ( fontData, fontsLogic, connectedFieldData, fontSizeInterval ) => {

  if ( ! fontSizeInterval ) {
    return;
  }

  const ab = fontSizeInterval;
  const cd = fontsLogic?.font_size_interval;

  if ( ! Array.isArray( ab ) || ! Array.isArray( cd ) || cd[0] >= cd[1] ) {
    return;
  }

  const connectedSettingID = connectedFieldData.setting_id;

  wp.customize( connectedSettingID, connectedSetting => {
    const connectedSettingValue = connectedSetting();
    const fontSize = connectedSettingValue?.font_size?.value;

    if ( !! fontSize ) {
      const newFontSize = ( fontSize - ab[0] ) * ( cd[1] - cd[0] ) / ( ab[1] - ab[0] ) + cd[0];
      fontData.font_size.value = Math.round( newFontSize * 10 ) / 10;
      console.log( ab, fontSize, cd, fontData.font_size.value );
    }
  } );
}

const applyFontSizeMultiplier = ( fontData, fontSizeMultiplier ) => {

  if ( typeof fontSizeMultiplier === "undefined" ) {
    return
  }

  let multiplier = parseFloat( fontSizeMultiplier );
  multiplier = multiplier <= 0 ? 1 : multiplier;

  fontData.font_size.value = round( parseFloat( fontData.font_size.value ) * multiplier, styleManager.fonts.floatPrecision )
}

export const getCallbackFilter = ( fontsLogic, connectedFieldData, fontSizeInterval ) => {

  /* ======================
   * Process the font logic to get the value that should be applied to the connected (font) fields.
   *
   * The font logic is already in the new value - @see setFieldFontsLogicConfig()
   */
  const newFontData = {};

  if ( typeof fontsLogic.reset !== 'undefined' ) {
    const settingID = connectedFieldData.setting_id;
    const defaultValue = styleManager.config.settings[ settingID ].default

    if ( !_.isUndefined( setting ) && !_.isEmpty( defaultValue ) ) {
      newFontData[ 'font_family' ] = defaultValue[ 'font_family' ];
      newFontData[ 'font_size' ] = defaultValue[ 'font_size' ];
      newFontData[ 'line_height' ] = defaultValue[ 'line_height' ];
      newFontData[ 'letter_spacing' ] = defaultValue[ 'letter_spacing' ];
      newFontData[ 'text_transform' ] = defaultValue[ 'text_transform' ];
      newFontData[ 'font_variant' ] = defaultValue[ 'font_variant' ];

      return newFontData;
    }
  }

  /* ===========
   * We need to determine the 6 subfields values to be able to determine the value of the font field.
   */

  // The font family is straight forward as it comes directly from the parent field font logic configuration.
  if ( typeof fontsLogic.font_family !== 'undefined' ) {
    newFontData[ 'font_family' ] = fontsLogic.font_family
  }

  if ( _.isEmpty( newFontData[ 'font_family' ] ) ) {
    // If we don't have a font family, we really can't do much.
    return
  }

  newFontData[ 'font_size' ] = standardizeNumericalValue( connectedFieldData.font_size );

  applyFontSizeInterval( newFontData, fontsLogic, connectedFieldData, fontSizeInterval );
//  applyFontSizeMultiplier( newFontData, fontsLogic.font_size_multiplier );

  if ( typeof connectedFieldData.font_size !== 'undefined' && false !== connectedFieldData.font_size ) {



    // The font variant, letter spacing and text transform all come together from the font styles (intervals).
    // We just need to find the one that best matches the connected field given font size (if given).
    // Please bear in mind that we expect the font logic styles to be preprocessed, without any overlapping and using numerical keys.
    if ( typeof fontsLogic.font_styles_intervals !== 'undefined' && _.isArray( fontsLogic.font_styles_intervals ) && fontsLogic.font_styles_intervals.length > 0 ) {
      let idx = 0
      while ( idx < fontsLogic.font_styles_intervals.length - 1 &&
              typeof fontsLogic.font_styles_intervals[ idx ].end !== 'undefined' &&
              fontsLogic.font_styles_intervals[ idx ].end <= connectedFieldData.font_size.value ) {

        idx ++
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

//      applyFontSizeMultiplier( fontData, fontsLogic.font_styles_intervals[ idx ].font_size_multiplier );
    }

    // The line height is determined by getting the value of the polynomial function determined by points.
    if ( typeof fontsLogic.font_size_to_line_height_points !== 'undefined' && _.isArray( fontsLogic.font_size_to_line_height_points ) ) {
      const result = regression.logarithmic( fontsLogic.font_size_to_line_height_points, { precision: styleManager.fonts.floatPrecision } )
      const lineHeight = result.predict( newFontData[ 'font_size' ].value )[ 1 ]
      newFontData[ 'line_height' ] = standardizeNumericalValue( lineHeight )
    }
  }

  return newFontData;
}
