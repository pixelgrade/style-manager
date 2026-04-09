import $ from 'jquery';
import { initializeVoiceTunerAccordion } from './voice-tuner-accordion';
import { initializeVoiceTuner } from './voice-tuner';
import { applyFontPaletteSelection } from './utils';

export const initializeFontPalettes = () => {

  $( '.js-font-palette' ).each( function( i, obj ) {
    const $paletteSet = $( obj );
    const $labels = $paletteSet.find( 'label' );

    $labels.on( 'click', function( event ) {
      const $label = $( event.currentTarget );
      const forID = $label.attr( 'for' );
      const $input = $( `#${ forID }` );
      applyFontPaletteSelection(
        {
          fontsLogic: $input.data( 'fonts_logic' ) || {},
          connectedFieldsPreset: $input.data( 'connected_fields_preset' ) || '',
        },
        {
          setFontSetting: ( settingID, config ) => {
            wp.customize( settingID, setting => {
              setting.set( config );
            } );
          },
          setConnectedFieldsPreset: preset => {
            wp.customize( 'sm_fonts_connected_fields_preset', setting => {
              setting.set( preset );
            } );
          },
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
