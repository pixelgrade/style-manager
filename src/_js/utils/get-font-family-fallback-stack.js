// This is a mirror logic of the server-side Utils\Fonts::getFontFamilyFallbackStack()
import _ from "lodash";

export const getFontFamilyFallbackStack = ( fontFamily ) => {
  const styleManager = styleManager || parent.styleManager;
  let fallbackStack = '';

  const fontDetails = parent.sm.customizer.getFontDetails( fontFamily );
  if ( typeof fontDetails.fallback_stack !== 'undefined' && !_.isEmpty( fontDetails.fallback_stack ) ) {
    fallbackStack = fontDetails.fallback_stack
  } else if ( typeof fontDetails.category !== 'undefined' && !_.isEmpty( fontDetails.category ) ) {
    const category = fontDetails.category;
    // Search in the available categories for a match.
    if ( typeof styleManager.fonts.categories[ category ] !== 'undefined' ) {
      // Matched by category ID/key
      fallbackStack = typeof styleManager.fonts.categories[ category ].fallback_stack !== 'undefined' ? styleManager.fonts.categories[ category ].fallback_stack : ''
    } else {
      // We need to search for aliases.
      _.find( styleManager.fonts.categories, function( categoryDetails ) {
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
