export const determineFontType = function( fontFamily ) {
  // The default is a standard font (aka no special loading or processing).
  let fontType = 'system_font';

  // We will follow a stack in the following order: Font Library fonts, third-party fonts, cloud fonts, theme fonts, Google fonts, standard fonts.
  if ( typeof styleManager.fonts.font_library_fonts?.[fontFamily] !== 'undefined' ) {
    fontType = 'font_library_font'
  } else if ( typeof styleManager.fonts.third_party_fonts[fontFamily] !== 'undefined' ) {
    fontType = 'third_party_font'
  } else if ( typeof styleManager.fonts.cloud_fonts[fontFamily] !== 'undefined' ) {
    fontType = 'cloud_font'
  } else if ( typeof styleManager.fonts.theme_fonts[fontFamily] !== 'undefined' ) {
    fontType = 'theme_font'
  } else if ( typeof styleManager.fonts.google_fonts[fontFamily] !== 'undefined' ) {
    fontType = 'google_font'
  }

  return fontType
};
