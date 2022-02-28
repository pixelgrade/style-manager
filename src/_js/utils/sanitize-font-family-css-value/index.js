// Mirror logic of server-side Utils\Fonts::sanitizeFontFamilyCSSValue()
import _ from "lodash";

import { maybeExplodeList } from "./maybe-explode-list";
import { maybeImplodeList } from "./maybe-implode-list";

export const sanitizeFontFamilyCSSValue = ( value ) => {
  // Since we might get a stack, attempt to treat is a comma-delimited list.
  let fontFamilies = maybeExplodeList( value );
  if ( !fontFamilies.length ) {
    return ''
  }

  _.each( fontFamilies, function( fontFamily, key ) {
    // Make sure that the font family is free from " or ' or whitespace, at the front.
    fontFamily = fontFamily.replace( new RegExp( /^\s*["'‘’“”]*\s*/ ), '' );
    // Make sure that the font family is free from " or ' or whitespace, at the back.
    fontFamily = fontFamily.replace( new RegExp( /\s*["'‘’“”]*\s*$/ ), '' );

    if ( '' === fontFamily ) {
      delete fontFamilies[ key ];
      return;
    }

    // Now, if the font family contains spaces, wrap it in ".
    if ( fontFamily.indexOf( ' ' ) !== - 1 ) {
      fontFamily = '"' + fontFamily + '"'
    }

    // Finally, put it back.
    fontFamilies[ key ] = fontFamily
  } );

  return maybeImplodeList( fontFamilies );
};
