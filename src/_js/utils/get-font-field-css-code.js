import _ from "lodash";
import $ from "jquery";

import { extractAllowedCSSPropertiesFromFontFields } from './extract-allowed-css-properties';
import { getFontFieldCSSProperties } from './get-font-field-css-properties'

// Mirror logic of server-side Utils\Fonts::getFontStyle()
export const getFontFieldCSSCode = ( settingID, cssValue, value ) => {
  const styleManager = styleManager || parent.styleManager;
  const fontConfig = styleManager.config.settings[ settingID ];
  const prefix = typeof fontConfig.properties_prefix === 'undefined' ? '' : fontConfig.properties_prefix;

  let output = '';

  if ( typeof window !== 'undefined' && typeof fontConfig.callback !== 'undefined' && typeof window[ fontConfig.callback ] === 'function' ) {
    // The callbacks expect a string selector right now, not a standardized list.
    // @todo Maybe migrate all callbacks to the new standardized data and remove all this.
    const plainSelectors = [];
    _.each( fontConfig.selector, function( details, selector ) {
      plainSelectors.push( selector )
    } );
    const adjustedFontConfig = $.extend( true, {}, fontConfig );
    adjustedFontConfig.selector = plainSelectors.join( ', ' );

    // Also, "kill" all fields unit since we pass final CSS values.
    // @todo For some reason, the client-side Typeline cbs are not consistent and expect the font-size value with unit.
    _.each( adjustedFontConfig[ 'fields' ], function( fieldValue, fieldKey ) {
      if ( typeof fieldValue.unit !== 'undefined' ) {
        adjustedFontConfig[ 'fields' ][ fieldKey ][ 'unit' ] = false;
      }
    } );

    // Callbacks want the value keys with underscores, not dashes.
    // We will provide them in both versions for a smoother transition.
    _.each( cssValue, function( propertyValue, property ) {
      const newKey = property.replace( regexForMultipleReplace, '_' );
      cssValue[ newKey ] = propertyValue
    } );

    return window[ fontConfig.callback ]( cssValue, adjustedFontConfig )
  }

  if ( typeof fontConfig.selector === 'undefined' || _.isEmpty( fontConfig.selector ) || _.isEmpty( cssValue ) ) {
    return output
  }

  // The general CSS allowed properties.
  const subFieldsCSSAllowedProperties = extractAllowedCSSPropertiesFromFontFields( fontConfig[ 'fields' ] );

  // The selector is standardized to a list of simple string selectors, or a list of complex selectors with details.
  // In either case, the actual selector is in the key, and the value is an array (possibly empty).

  // Since we might have simple CSS selectors and complex ones (with special details),
  // for cleanliness we will group the simple ones under a single CSS rule,
  // and output individual CSS rules for complex ones.
  // Right now, for complex CSS selectors we are only interested in the `properties` sub-entry.
  const simpleCSSSelectors = [];
  const complexCSSSelectors = {};

  _.each( fontConfig.selector, function( details, selector ) {
    if ( _.isEmpty( details.properties ) ) {
      // This is a simple selector.
      simpleCSSSelectors.push( selector )
    } else {
      complexCSSSelectors[ selector ] = details
    }
  } );

  if ( !_.isEmpty( simpleCSSSelectors ) ) {
    output += '\n' + simpleCSSSelectors.join( ', ' ) + ' {\n';
    output += getFontFieldCSSProperties( cssValue, subFieldsCSSAllowedProperties, prefix );
    output += '}\n'
  }

  if ( !_.isEmpty( complexCSSSelectors ) ) {
    _.each( complexCSSSelectors, function( details, selector ) {
      output += '\n' + selector + ' {\n';
      output += getFontFieldCSSProperties( cssValue, details.properties, prefix );
      output += '}\n'
    } )
  }

  return output
};
