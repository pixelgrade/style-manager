/* global WebFont */
import $ from 'jquery';
import { initializeVoiceTunerAccordion } from './voice-tuner-accordion';
import { initializeVoiceTuner } from './voice-tuner';
import { applyFontPaletteSelection, buildFontPalettePreviewWebFontConfig } from './utils';

export const initializeFontPalettes = () => {

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
    } );
  } );

  initializeVoiceTunerAccordion();
  const scheduleAccordionPlacement = window.requestAnimationFrame || ( callback => window.setTimeout( callback, 0 ) );
  scheduleAccordionPlacement( () => {
    initializeVoiceTunerAccordion();
  } );
  initializeVoiceTuner();
};
