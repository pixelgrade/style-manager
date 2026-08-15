import { debounce, maybeLoadFontFamily } from "../../../utils";
import { getCallback, getSettingConfig, setCallback, unbindConnectedFields } from "../../global-service";
import { getConnectedFieldsFontSizeInterval } from "./get-connected-fields-font-size-interval";
import { applyFontSizeInterval, getRelativeFontSizeInterval } from "./relative-font-size-interval";
export { applyFontSizeInterval } from "./relative-font-size-interval";
import { standardizeNumericalValue } from "../utils";
import { round } from '../utils/round';
import { createConnectedFieldsBatcher } from "./batcher";

const connectedFieldsBatcher = createConnectedFieldsBatcher( ( settingID, fontsLogic ) => {
  alterConnectedFields( settingID, fontsLogic );
} );

export const runConnectedFieldsBatch = ( callback ) => {
  return connectedFieldsBatcher.run( callback );
};

export const reloadConnectedFields = debounce( () => {
  const settingIDs = styleManager.fontPalettes.masterSettingIds;
  const boundSettingIDs = settingIDs.reduce( ( acc, settingID ) => {
    return acc.concat( [ settingID, `${ settingID }_elevation`, `${ settingID }_pitch` ] );
  }, [] );

  unbindConnectedFields( boundSettingIDs );

  settingIDs.forEach( settingID => {
    const elevationSettingID = `${ settingID }_elevation`;
    const pitchSettingID = `${ settingID }_pitch`;

    wp.customize( settingID, setting => {
      let fontsLogic = setting();

      setCallback( settingID, newValue => {
        fontsLogic = newValue;
        maybeLoadFontFamily( newValue, settingID );
        connectedFieldsBatcher.schedule( settingID, fontsLogic )
      } );

      setting.bind( getCallback( settingID ) );

      wp.customize( elevationSettingID, elevationSetting => {
        wp.customize( pitchSettingID, pitchSetting => {
          let elevation = elevationSetting();
          let pitch = pitchSetting();

          setCallback( elevationSettingID, newValue => {
            elevation = newValue;
            connectedFieldsBatcher.schedule( settingID, fontsLogic )
          } );

          setCallback( pitchSettingID, newValue => {
            pitch = newValue;
            connectedFieldsBatcher.schedule( settingID, fontsLogic )
          } );

          elevationSetting.bind( getCallback( elevationSettingID ) );
          pitchSetting.bind( getCallback( pitchSettingID ) );
        } );
      } );
    } );
  } );

}, 30 );

export const getConnectedFieldFontData = ( connectedSettingID, settingID, fontsLogic ) => {
  const newFontData = {};

  if ( typeof fontsLogic.reset !== 'undefined' ) {
    return getSettingConfig( connectedSettingID ).default;
  }

  // The font family is straight forward as it comes directly from the parent field font logic configuration.
  if ( typeof fontsLogic.font_family === 'undefined' ) {
    return null;
  }

  wp.customize( connectedSettingID, connectedSetting => {
    const fontSizeInterval = getConnectedFieldsFontSizeInterval( settingID );
    const connectedSettingData = connectedSetting();

    newFontData[ 'font_family' ] = fontsLogic.font_family;
    newFontData[ 'font_size' ] = standardizeNumericalValue( connectedSettingData.font_size );

    const targetFontSizeInterval = getFontSizeInterval( settingID, fontSizeInterval );

    if ( targetFontSizeInterval ) {
      // Remap the CURRENT size (issue #203): at the neutral knob position the
      // target interval equals the source interval, so this is an identity and
      // font palettes stay size-neutral; moving Elevation/Pitch rescales the
      // user's own values, preserving fine-tune relationships.
      const fontSize = newFontData.font_size?.value;
      applyFontSizeInterval( newFontData, fontSize, fontSizeInterval, targetFontSizeInterval );
    }

    applyFontSizeMultiplier( newFontData, fontsLogic.font_size_multiplier );
    applyFontStyleIntervals( newFontData, fontsLogic, connectedSettingData );
    applyLineHeight( newFontData, fontsLogic );
  } );

  return newFontData;
}

const alterConnectedFields = ( settingID, fontsLogic ) => {
  const settingConfig = getSettingConfig( settingID );

  settingConfig.connected_fields.forEach( key => {
    const connectedSettingID = `${ styleManager.config.options_name }[${ key }]`;

    wp.customize( connectedSettingID, connectedSetting => {
      const newFontData = getConnectedFieldFontData( connectedSettingID, settingID, fontsLogic );
      connectedSetting.set( newFontData );
    } );
  } );
}

const getFontSizeInterval = ( settingID, sourceInterval ) => {
  let fontSizeInterval = false;

  wp.customize( `${ settingID }_elevation`, elevationSetting => {
    wp.customize( `${ settingID }_pitch`, pitchSetting => {
      const elevation = parseInt( elevationSetting(), 10 );
      const pitch = parseInt( pitchSetting(), 10 );

      fontSizeInterval = getRelativeFontSizeInterval( sourceInterval, elevation, pitch );
    } );
  } );

  return fontSizeInterval;
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

     applyFontSizeMultiplier( newFontData, fontsLogic.font_styles_intervals[ idx ].font_size_multiplier );
  }
};

export const applyLineHeight = ( newFontData, fontsLogic ) => {
  if ( Array.isArray( fontsLogic.font_size_to_line_height_points ) ) {
    const result = regression.logarithmic( fontsLogic.font_size_to_line_height_points, { precision: styleManager.fonts.floatPrecision } );
    const lineHeight = result.predict( newFontData[ 'font_size' ].value )[ 1 ];
    newFontData[ 'line_height' ] = standardizeNumericalValue( lineHeight );
  }
};


// Use 'font_size_multiplier' in font palette declaration to resize individual fonts 
const applyFontSizeMultiplier = ( fontData, fontSizeMultiplier ) => {

  if ( typeof fontSizeMultiplier === "undefined" ) {
    return
  }

  let multiplier = parseFloat( fontSizeMultiplier );
  multiplier = multiplier <= 0 ? 1 : multiplier;

  fontData.font_size.value = round( parseFloat( fontData.font_size.value ) * multiplier, styleManager.fonts.floatPrecision )
};
