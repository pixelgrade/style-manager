export const determineFontType = function( fontFamily ) {
  // The default is a standard font (aka no special loading or processing).
  let fontType = 'system_font'

  // We will follow a stack in the following order: cloud fonts, theme fonts, Google fonts, standard fonts.
  if ( typeof styleManager.fonts.cloud_fonts[fontFamily] !== 'undefined' ) {
    fontType = 'cloud_font'
  } else if ( typeof styleManager.fonts.theme_fonts[fontFamily] !== 'undefined' ) {
    fontType = 'theme_font'
  } else if ( typeof styleManager.fonts.google_fonts[fontFamily] !== 'undefined' ) {
    fontType = 'google_font'
  }

  return fontType
}
