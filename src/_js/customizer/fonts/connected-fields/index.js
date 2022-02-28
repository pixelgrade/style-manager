import { debounce, maybeLoadFontFamily } from "../../../utils";
import { getCallback, getSettingConfig, setCallback, unbindConnectedFields } from "../../global-service";
import { getConnectedFieldsFontSizeInterval } from "./get-connected-fields-font-size-interval";
import { standardizeNumericalValue } from "../utils";

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
        alterConnectedFields( settingID, fontsLogic )
      } );

      setting.bind( getCallback( settingID ) );

      wp.customize( elevationSettingID, elevationSetting => {
        wp.customize( pitchSettingID, pitchSetting => {
          let elevation = elevationSetting();
          let pitch = pitchSetting();

          setCallback( elevationSettingID, newValue => {
            elevation = newValue;
            alterConnectedFields( settingID, fontsLogic )
          } );

          setCallback( pitchSettingID, newValue => {
            pitch = newValue;
            alterConnectedFields( settingID, fontsLogic )
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

  const connectedSetting = wp.customize( connectedSettingID, connectedSetting => {
    const fontSizeInterval = getConnectedFieldsFontSizeInterval( settingID );
    const connectedSettingData = connectedSetting();

    newFontData[ 'font_family' ] = fontsLogic.font_family;
    newFontData[ 'font_size' ] = standardizeNumericalValue( connectedSettingData.font_size );

    const targetFontSizeInterval = getFontSizeInterval( settingID );

    if ( targetFontSizeInterval ) {
      const connectedSettingConfig = getSettingConfig( connectedSettingID );
      const fontSize = connectedSettingConfig?.default?.font_size?.value;
      applyFontSizeInterval( newFontData, fontSize, fontSizeInterval, targetFontSizeInterval );
    }

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

const getFontSizeInterval = ( settingID ) => {
  let fontSizeInterval;

  wp.customize( `${ settingID }_elevation`, elevationSetting => {
    wp.customize( `${ settingID }_pitch`, pitchSetting => {
      const elevation = parseInt( elevationSetting(), 10 );
      const pitch = parseInt( pitchSetting(), 10 );

      fontSizeInterval = getInterval( elevation, pitch );
    } );
  } );

  return fontSizeInterval;
}

const getInterval = ( elevation, pitch ) => {
//  const elevationInterval = [ 8, 120 ];
//  const pitchInterval = [ 0, 120 ];
//  const min = elevationInterval[ 0 ] + elevationInterval[ 1 ] * elevation / 100;
//  const max = min + pitchInterval[ 0 ] + pitchInterval[ 1 ] * pitch / 100;
//  return [ min, max ];

  return [ elevation, elevation + pitch ];
}

export const applyFontSizeInterval = ( fontData, fontSize, fontSizeInterval, targetFontSizeInterval ) => {

  if ( ! fontSizeInterval ) {
    return;
  }

  const ab = fontSizeInterval;
  const cd = targetFontSizeInterval;

  if ( ! Array.isArray( ab ) || ! Array.isArray( cd ) ) {
    return;
  }

  if ( !! fontSize ) {

    if ( ab[1] === ab[0] ) {
      fontData.font_size.value = Math.max( cd[0], Math.min( cd[1], fontSize ) );
    } else {
      const newFontSize = ( fontSize - ab[0] ) * ( cd[1] - cd[0] ) / ( ab[1] - ab[0] ) + cd[0];
      fontData.font_size.value = Math.round( newFontSize * 10 ) / 10;
    }
  }
};

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
  }
};

export const applyLineHeight = ( newFontData, fontsLogic ) => {
  if ( Array.isArray( fontsLogic.font_size_to_line_height_points ) ) {
    const result = regression.logarithmic( fontsLogic.font_size_to_line_height_points, { precision: styleManager.fonts.floatPrecision } );
    const lineHeight = result.predict( newFontData[ 'font_size' ].value )[ 1 ];
    newFontData[ 'line_height' ] = standardizeNumericalValue( lineHeight );
  }
};
