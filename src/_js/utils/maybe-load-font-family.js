import _ from "lodash";

import { standardizeToArray } from './standardize-to-array';

export const maybeLoadFontFamily = function( font, settingID ) {

  const styleManager = styleManager || parent.styleManager;

  window.fontsCache = window.fontsCache ?? [];

  if ( typeof font.font_family === 'undefined' ) {
    return
  }

  const fontConfig = styleManager.config.settings[ settingID ];

  let family = font.font_family;
  // The font family may be a comma separated list like "Roboto, sans"
  const fontType = parent.sm.customizer.determineFontType( family );

  if ( 'system_font' === fontType ) {
    // Nothing to do for standard fonts
    return
  }

  const fontDetails = parent.sm.customizer.getFontDetails( family, fontType );

  // Handle theme defined fonts and cloud fonts together since they are very similar.
  if ( fontType === 'theme_font' || fontType === 'cloud_font' ) {

    // Bail if we have no src.
    if ( typeof fontDetails.src === undefined ) {
      return
    }

    // Handle the font variants.
    // If there is a selected font variant and we haven't been instructed to load all, load only that,
    // otherwise load all the available variants.
    let variants =
      (
        typeof font.font_variant !== 'undefined'
        && ( typeof fontConfig[ 'fields' ][ 'font-weight' ][ 'loadAllVariants' ] === 'undefined' || !fontConfig[ 'fields' ][ 'font-weight' ][ 'loadAllVariants' ] )
        && typeof fontDetails.variants !== 'undefined' // If the font has no variants, any variant value we may have received should be ignored.
        && _.includes( fontDetails.variants, font.font_variant ) // If the value variant is not amongst the available ones, load all available variants.
      ) ? font.font_variant : typeof fontDetails.variants !== 'undefined' ? fontDetails.variants : [];

    if ( !_.isEmpty( variants ) ) {
      variants = standardizeToArray( variants );

      if ( !_.isEmpty( variants ) ) {
        family = family + ':' + variants.map( function( variant ) {
          return parent.sm.customizer.convertFontVariantToFVD( variant )
        } ).join( ',' )
      }
    }

    if ( window.fontsCache.indexOf( family ) === - 1 ) {
      WebFont.load( {
        custom: {
          families: [ family ],
          urls: [ fontDetails.src ]
        },
        classes: false,
        events: false,
      } );

      // Remember we've loaded this family (with it's variants) so we don't load it again.
      window.fontsCache.push( family )
    }
  }
  // Handle Google fonts since Web Font Loader has a special module for them.
  else if ( fontType === 'google_font' ) {

    // Handle the font variants
    // If there is a selected font variant and we haven't been instructed to load all, load only that,
    // otherwise load all the available variants.
    let variants =
      (
        typeof font.font_variant !== 'undefined'
        && ( typeof fontConfig[ 'fields' ][ 'font-weight' ][ 'loadAllVariants' ] === 'undefined' || !fontConfig[ 'fields' ][ 'font-weight' ][ 'loadAllVariants' ] )
        && typeof fontDetails.variants !== 'undefined' // If the font has no variants, any variant value we may have received should be ignored.
        && _.includes( fontDetails.variants, font.font_variant ) // If the value variant is not amongst the available ones, load all available variants.
      ) ? font.font_variant : typeof fontDetails.variants !== 'undefined' ? fontDetails.variants : [];

    if ( !_.isEmpty( variants ) ) {
      variants = standardizeToArray( variants );

      if ( !_.isEmpty( variants ) ) {
        family = family + ':' + variants.join( ',' )
      }
    }

    if ( window.fontsCache.indexOf( family ) === - 1 ) {
      WebFont.load( {
        google: { families: [ family ] },
        classes: false,
        events: false,
      } );

      // Remember we've loaded this family (with it's variants) so we don't load it again.
      window.fontsCache.push( family )
    }

  } else {
    // Maybe Typekit, Fonts.com or Fontdeck fonts
  }
};

