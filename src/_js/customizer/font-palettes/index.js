/* global WebFont */
import $ from 'jquery';
import { initializeVoiceTunerAccordion } from './voice-tuner-accordion';
import { initializeVoiceTuner } from './voice-tuner';
import * as globalService from '../global-service';
import {
  CONNECTED_FIELDS_PRESET_SETTING_ID,
  CONNECTED_FIELDS_PRESET_SOURCE_SETTING_ID,
  applyFontPaletteSelection,
  buildFontPalettePreviewWebFontConfig,
  createConnectedFieldsPresetOwnership,
} from './utils';

const setCustomizeSetting = ( settingID, value ) => {
  wp.customize( settingID, setting => {
    setting.set( value );
  } );
};

export const initializeFontPalettes = () => {

  // #204: a palette applies its own hierarchy preset only while the user has not set one.
  const hierarchyOwnership = createConnectedFieldsPresetOwnership( {
    userSet: !! window.styleManager?.fontPalettes?.connectedFieldsPresetUserSet,
  } );
  const setHierarchySource = value => setCustomizeSetting( CONNECTED_FIELDS_PRESET_SOURCE_SETTING_ID, value );

  wp.customize( CONNECTED_FIELDS_PRESET_SETTING_ID, setting => {
    setting.bind( () => {
      hierarchyOwnership.onPresetChange( setHierarchySource );
    } );
  } );

  $( '.js-font-palette' ).each( function( i, obj ) {
    const $paletteSet = $( obj );
    const $labels = $paletteSet.find( 'label' );
    const previewFontConfig = buildFontPalettePreviewWebFontConfig(
      $paletteSet.data( 'previewFontFamilies' ) || {},
      window.styleManager?.fonts || {}
    );

    if ( 'undefined' !== typeof WebFont && ( previewFontConfig.google || previewFontConfig.custom ) ) {
      WebFont.load( previewFontConfig );
    }

    $labels.on( 'click', function( event ) {
      const $label = $( event.currentTarget );
      const forID = $label.attr( 'for' );
      const $input = $( `#${ forID }` );
      applyFontPaletteSelection(
        {
          fontsLogic: $input.data( 'fonts_logic' ) || {},
        },
        {
          setFontSetting: ( settingID, config ) => {
            wp.customize( settingID, setting => {
              setting.set( config );
            } );
          },
        }
      );

      hierarchyOwnership.applyPalette(
        String( $input.attr( 'data-connected_fields_preset' ) || '' ),
        globalService.getSettingConfig( CONNECTED_FIELDS_PRESET_SETTING_ID ) || {},
        {
          setPreset: preset => setCustomizeSetting( CONNECTED_FIELDS_PRESET_SETTING_ID, preset ),
          setSource: setHierarchySource,
        }
      );
    } );
  } );

  initializeVoiceTunerAccordion();
  const scheduleAccordionPlacement = window.requestAnimationFrame || ( callback => window.setTimeout( callback, 0 ) );
  scheduleAccordionPlacement( () => {
    initializeVoiceTunerAccordion();
  } );
  initializeVoiceTuner();
};
