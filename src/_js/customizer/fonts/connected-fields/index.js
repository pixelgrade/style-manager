import { debounce, maybeLoadFontFamily } from "../../../utils";
import { getCallback, getSettingConfig, setCallback, unbindConnectedFields } from "../../global-service";
import { getConnectedFieldsFontSizeScale } from "./get-connected-fields-font-size-interval";
import { applyFontSizeInterval } from "./relative-font-size-interval";
export { applyFontSizeInterval } from "./relative-font-size-interval";
import { getNumericFontSize, hasNumericFontSize, resolveScalableFontSize } from "./font-size-value";
import { standardizeNumericalValue } from "../utils";
import { createConnectedFieldsBatcher } from "./batcher";
import {
  createFontSizeBaselineEntry,
  deriveAbsoluteFontSizes,
  isFontSizeBaselineEntry,
  normalizeFontSizeBaselineDocument,
  reconcileFontSizeBaselineEntry,
} from "./font-size-baseline";

const FONT_SIZE_BASELINE_SETTING_ID = 'sm_font_sizing_baseline_v1';

const connectedFieldsBatcher = createConnectedFieldsBatcher( ( settingID, typographyState ) => {
  alterConnectedFields(
    settingID,
    typographyState.fontsLogic,
    typographyState.previousState,
    typographyState,
    typographyState.baselineSetting
  );
  typographyState.markApplied();
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

      wp.customize( FONT_SIZE_BASELINE_SETTING_ID, baselineSetting => {
        wp.customize( elevationSettingID, elevationSetting => {
          wp.customize( pitchSettingID, pitchSetting => {
            let elevation = elevationSetting();
            let pitch = pitchSetting();
            let appliedState = {
              fontsLogic,
              elevation: Number( elevation ),
              pitch: Number( pitch ),
            };

            const scheduleCurrentState = () => {
              const nextState = {
                fontsLogic,
                elevation: Number( elevation ),
                pitch: Number( pitch ),
              };

              connectedFieldsBatcher.schedule( settingID, {
                ...nextState,
                previousState: appliedState,
                baselineSetting,
                markApplied: () => {
                  appliedState = nextState;
                },
              } );
            };

            setCallback( settingID, newValue => {
              fontsLogic = newValue;
              maybeLoadFontFamily( newValue, settingID );
              scheduleCurrentState();
            } );

            setCallback( elevationSettingID, newValue => {
              elevation = newValue;
              scheduleCurrentState();
            } );

            setCallback( pitchSettingID, newValue => {
              pitch = newValue;
              scheduleCurrentState();
            } );

            setting.bind( getCallback( settingID ) );
            elevationSetting.bind( getCallback( elevationSettingID ) );
            pitchSetting.bind( getCallback( pitchSettingID ) );
          } );
        } );
      } );
    } );
  } );

}, 30 );

export const getConnectedFieldFontData = ( connectedSettingID, settingID, fontsLogic, sourceFontSizeInterval = false, targetFontSizeInterval = false, sourceFontSize = null ) => {
  const newFontData = {};

  if ( typeof fontsLogic.reset !== 'undefined' ) {
    return getSettingConfig( connectedSettingID ).default;
  }

  // The font family is straight forward as it comes directly from the parent field font logic configuration.
  if ( typeof fontsLogic.font_family === 'undefined' ) {
    return null;
  }

  wp.customize( connectedSettingID, connectedSetting => {
    const connectedSettingData = connectedSetting();
    const rawFontSize = connectedSettingData.font_size && typeof connectedSettingData.font_size === 'object'
      ? connectedSettingData.font_size.value
      : connectedSettingData.font_size;
    const numericFontSize = getNumericFontSize( rawFontSize );

    newFontData[ 'font_family' ] = fontsLogic.font_family;
    newFontData[ 'font_size' ] = standardizeNumericalValue( connectedSettingData.font_size );

    // `false` means that the field inherits its size. Do not let the legacy
    // numerical standardizer turn that sentinel into NaN during a scale or
    // palette update.
    if ( numericFontSize === null ) {
      newFontData[ 'font_size' ].value = false;
    }

    if ( Array.isArray( sourceFontSizeInterval ) && Array.isArray( targetFontSizeInterval ) ) {
      const fontSize = resolveScalableFontSize( rawFontSize, sourceFontSize );

      if ( fontSize !== null ) {
        applyFontSizeInterval(
          newFontData,
          fontSize,
          sourceFontSizeInterval,
          targetFontSizeInterval,
          styleManager.fonts.floatPrecision
        );
      }
    }

    applyFontStyleIntervals( newFontData, fontsLogic, connectedSettingData );
    applyLineHeight( newFontData, fontsLogic );
  } );

  return newFontData;
}

const alterConnectedFields = ( settingID, fontsLogic, previousState, nextState, baselineSetting ) => {
  const settingConfig = getSettingConfig( settingID );
  const sizingChanged = previousState.elevation !== nextState.elevation ||
    previousState.pitch !== nextState.pitch;
  let sourceFontSizeInterval = false;
  let targetFontSizeInterval = false;
  let sourceFontSizes = {};

  if ( sizingChanged ) {
    const precision = styleManager.fonts.floatPrecision;
    const currentScale = getConnectedFieldsFontSizeScale( settingID );
    const fallbackScale = getConnectedFieldsFontSizeScale( settingID, true );
    const baselineDocument = normalizeFontSizeBaselineDocument( baselineSetting() );
    let baselineEntry = baselineDocument.scales[ settingID ];
    let baselineChanged = false;

    if ( ! isFontSizeBaselineEntry( baselineEntry ) ) {
      baselineEntry = createFontSizeBaselineEntry( {
        currentInterval: currentScale.interval,
        currentSizes: currentScale.sizes,
        currentState: previousState,
        fallbackInterval: fallbackScale.interval,
        fallbackSizes: fallbackScale.sizes,
        precision,
      } );
      baselineChanged = true;
    } else {
      const reconciled = reconcileFontSizeBaselineEntry(
        baselineEntry,
        currentScale.sizes,
        previousState,
        precision,
        currentScale.fieldIds
      );
      baselineEntry = reconciled.entry;
      baselineChanged = reconciled.changed;
    }

    const derivedScale = deriveAbsoluteFontSizes( baselineEntry, nextState, precision );
    sourceFontSizeInterval = baselineEntry.interval;
    targetFontSizeInterval = derivedScale.interval;
    sourceFontSizes = baselineEntry.sizes;

    if ( baselineChanged ) {
      baselineDocument.scales[ settingID ] = baselineEntry;
      baselineSetting.set( baselineDocument );
    }
  }

  settingConfig.connected_fields.forEach( key => {
    const connectedSettingID = `${ styleManager.config.options_name }[${ key }]`;

    wp.customize( connectedSettingID, connectedSetting => {
      const newFontData = getConnectedFieldFontData(
        connectedSettingID,
        settingID,
        fontsLogic,
        sourceFontSizeInterval,
        targetFontSizeInterval,
        sourceFontSizes[ key ] ?? null
      );
      connectedSetting.set( newFontData );
    } );
  } );
}

// The line height is determined by getting the value of the polynomial function determined by points.
export const applyFontStyleIntervals = ( newFontData, fontsLogic ) => {
  if ( ! hasNumericFontSize( newFontData ) ) {
    return;
  }

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
  if ( Array.isArray( fontsLogic.font_size_to_line_height_points ) &&
       hasNumericFontSize( newFontData ) ) {
    const result = regression.logarithmic( fontsLogic.font_size_to_line_height_points, { precision: styleManager.fonts.floatPrecision } );
    const lineHeight = result.predict( newFontData[ 'font_size' ].value )[ 1 ];
    newFontData[ 'line_height' ] = standardizeNumericalValue( lineHeight );
  }
};
