import { determineFontType } from './determine-font-type';

export const getFontDetails = function( fontFamily, fontType = false ) {
  if ( false === fontType ) {
    // We will determine the font type based on font family.
    fontType = determineFontType( fontFamily )
  }

  switch ( fontType ) {
    case 'theme_font':
      return styleManager.fonts.theme_fonts[fontFamily];
      break;
    case 'cloud_font':
      return styleManager.fonts.cloud_fonts[fontFamily];
      break;
    case 'google_font':
      return styleManager.fonts.google_fonts[fontFamily];
      break;
    case 'system_font':
      if ( typeof styleManager.fonts.system_fonts[fontFamily] !== 'undefined' ) {
        return styleManager.fonts.system_fonts[fontFamily]
      }
      break;
    case 'third_party_font':
      if ( typeof styleManager.fonts.third_party_fonts[fontFamily] !== 'undefined' ) {
        return styleManager.fonts.third_party_fonts[fontFamily]
      }
      break;
    default:
  }

  return false
};
