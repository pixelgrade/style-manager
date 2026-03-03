// This is a mirror logic of the server-side Utils\Fonts::getFontFamilyFallbackStack()
import _ from "lodash";

export const getFontFamilyFallbackStack = ( fontFamily ) => {
  let sm;
  try {
    sm = window.styleManager || parent.styleManager;
  } catch ( e ) {
    sm = window.styleManager;
  }

  let smCustomizer;
  try {
    smCustomizer = parent.sm?.customizer;
  } catch ( e ) {
    smCustomizer = null;
  }

  if ( ! sm || ! smCustomizer ) {
    return '';
  }

  let fallbackStack = '';

  const fontDetails = smCustomizer.getFontDetails( fontFamily );
  if ( typeof fontDetails.fallback_stack !== 'undefined' && !_.isEmpty( fontDetails.fallback_stack ) ) {
    fallbackStack = fontDetails.fallback_stack
  } else if ( typeof fontDetails.category !== 'undefined' && !_.isEmpty( fontDetails.category ) ) {
    const category = fontDetails.category;
    // Search in the available categories for a match.
    if ( typeof sm.fonts.categories[ category ] !== 'undefined' ) {
      // Matched by category ID/key
      fallbackStack = typeof sm.fonts.categories[ category ].fallback_stack !== 'undefined' ? sm.fonts.categories[ category ].fallback_stack : ''
    } else {
      // We need to search for aliases.
      _.find( sm.fonts.categories, function( categoryDetails ) {
        if ( typeof categoryDetails.aliases !== 'undefined' ) {
          const aliases = maybeImplodeList( categoryDetails.aliases );
          if ( aliases.indexOf( category ) !== - 1 ) {
            // Found it.
            fallbackStack = typeof categoryDetails.fallback_stack !== 'undefined' ? categoryDetails.fallback_stack : '';
            return true
          }
        }

        return false
      } )
    }
  }

  return fallbackStack
};
