import $ from "jquery";
import * as globalService from "../global-service";

import {
  getFontDetails,
  getSettingID,
  handleFontPopupToggle,
  initSubfield,
  loadFontValue,
  selfUpdateValue,
  updateFontHeadTitle,
  updateVariantField,
  fontsService,
} from './utils'

import { reloadConnectedFields } from "./connected-fields";

import { getCallback, getSetting, getSettingConfig, setCallback } from "../global-service";

const wrapperSelector = '.font-options__wrapper';
const fontVariantSelector = '.style-manager_font_weight';

export const initializeFonts = function() {
  const $fontFields = $( wrapperSelector );

  $fontFields.each( ( i, obj ) => {
    const $fontField = $( obj );

    initializeFontFamilyField( $fontField );
    initializeSubfields( $fontField );
  } );

  handleFontPopupToggle();
  initializeConnectedFieldsPresets();

  reloadConnectedFields();
};

const initializeConnectedFieldsPresets = () => {

  wp.customize( 'sm_fonts_connected_fields_preset', setting => {
    const settingIDs = styleManager.fontPalettes.masterSettingIds;
    const config = globalService.getSettingConfig( 'sm_fonts_connected_fields_preset' );

    setting.bind( newValue => {
      const newValueConfig = config.choices[ newValue ].config;

      Object.keys( newValueConfig ).forEach( settingID => {
        const masterFontConfig = globalService.getSettingConfig( settingID );
        const newMasterFontConfig = Object.assign( {}, masterFontConfig, {
          connected_fields: newValueConfig[ settingID ]
        } );
        globalService.setSettingConfig( settingID, newMasterFontConfig );
      } );

      reloadConnectedFields();

      settingIDs.forEach( settingID => {
        wp.customize( settingID, setting => {
          const value = setting();
          setting.callbacks.fireWith( setting, [ value, value ] );
        } );
      } )
    } );
  } );


  wp.customize( 'sm_font_sizing', setting => {

    const configs = {
      // Felt
      smaller: {
        sm_font_primary: [ 21, 115 ],
        sm_font_secondary: [ 14, 17 ],
        sm_font_body: [ 17, 20 ],
      },
      // Rosa2
      regular: {
        sm_font_primary: [ 24, 165 ],
        sm_font_secondary: [ 16, 18 ],
        sm_font_body: [ 17, 24 ],
      },
      larger: {
        sm_font_primary: [ 28, 180 ],
        sm_font_secondary: [ 17, 22 ],
        sm_font_body: [ 18, 24 ],
      },
    }

    setting.bind( newValue => {
      const config = configs[ newValue ];

      if ( ! config ) {
        return;
      }

      Object.keys( config ).forEach( settingID => {
        wp.customize( `${ settingID }_elevation`, elevationSetting => {
          wp.customize( `${ settingID }_pitch`, pitchSetting => {
            elevationSetting.set( config[ settingID ][0] );
            pitchSetting.set( config[ settingID ][1] - config[ settingID ][0] );
          } );
        } );
      } );
    } )

  } );
};

const initializeFontFamilyField = ( $fontField ) => {
  const $fontFamilyField = $fontField.find( '.style-manager_font_family' );
  const familyPlaceholderText = styleManager.l10n.fonts.familyPlaceholderText;

  // Add the Google Fonts opts to each control
//  addGoogleFontsToFontFamilyField( $fontFamilyField );

  // Initialize the select2 field for the font family
  $fontFamilyField.select2( {
    placeholder: familyPlaceholderText
  } );

  $fontFamilyField.on( 'change', onFontFamilyChange );
  bindFontFamilySettingChange( $fontFamilyField );
};

const initializeSubfields = ( $fontField ) => {
  const $variant = $fontField.find( fontVariantSelector );
  const $select = $fontField.find( 'select' ).not( 'select[class*=\' select2\'],select[class^=\'select2\']' );
  const $range = $fontField.find( 'input[type="range"]' );

  // Initialize the select2 field for the font variant
  initSubfield( $variant, true );

  // Initialize all the regular selects in the font subfields
  initSubfield( $select, false );

  // Initialize the all the range fields in the font subfields
  initSubfield( $range, false );
};

const addGoogleFontsToFontFamilyField = ( $fontFamilyField ) => {
  const googleFontsOptions = wp.customize.settings[ 'google_fonts_opts' ];
  const $googleOptionsPlaceholder = $fontFamilyField.find( '.google-fonts-opts-placeholder' ).first();

  if ( typeof googleFontsOptions !== 'undefined' && $googleOptionsPlaceholder.length ) {

    // Replace the placeholder with the HTML for the Google fonts select options.
    $googleOptionsPlaceholder.replaceWith( googleFontsOptions );

    // The active font family might be a Google font so we need to set the current value after we've added the options.
    const activeFontFamily = $fontFamilyField.data( 'active_font_family' );

    if ( typeof activeFontFamily !== 'undefined' ) {
      $fontFamilyField.val( activeFontFamily );
    }
  }
};

const onFontFamilyChange = ( event ) => {
  const newFontFamily = event.target.value;
  const $target = $( event.target );
  const $wrapper = $target.closest( wrapperSelector );

  // Get the new font details
  const newFontDetails = getFontDetails( newFontFamily );

  // Update the font field head title (with the new font family name).
  updateFontHeadTitle( newFontDetails, $wrapper );

  // Update the variant subfield with the new options given by the selected font family.
  updateVariantField( newFontDetails, $wrapper );

  if ( typeof who !== 'undefined' && who === 'style-manager' ) {
    // The change was triggered programmatically by Style Manager.
    // No need to self-update the value.
  } else {
    // Mark this input as touched by the user.
    $( event.target ).data( 'touched', true );

    // Serialize subfield values and refresh the fonts in the preview window.
    selfUpdateValue( $wrapper, getSettingID( $target ) );
  }
};

const bindFontFamilySettingChange = ( $fontFamilyField ) => {
  const $wrapper = $fontFamilyField.closest( wrapperSelector );
  const settingID = getSettingID( $fontFamilyField );

  wp.customize( settingID, setting => {
    setting.bind( function( newValue, oldValue ) {
      // this is a costly operation
      if ( ! fontsService.isUpdating( settingID ) ) {
        loadFontValue( $wrapper, newValue, settingID )
      }
    } );
  } );
};
