import { convertFontVariantToFVD } from '../fonts/utils/convert-font-variant.js';

const uniqueStrings = values => Array.from( new Set( values.map( String ) ) );

const normalizeGoogleVariant = variant => {
  switch ( String( variant ) ) {
    case 'normal':
    case 'regular':
      return '400';
    case 'italic':
      return '400italic';
    case 'bold':
      return '700';
    default:
      return String( variant );
  }
};

const resolveGooglePreviewVariants = ( requestedVariants, availableVariants ) => {
  const requested = uniqueStrings( requestedVariants ).map( normalizeGoogleVariant );
  const available = uniqueStrings( availableVariants ).map( normalizeGoogleVariant );

  if ( ! available.length ) {
    return requested;
  }

  const supported = requested.filter( variant => available.includes( variant ) );
  if ( supported.length || ! requested.length ) {
    return supported;
  }

  return [ available.includes( '400' ) ? '400' : available[ 0 ] ];
};

/**
 * Build the Web Font Loader config for every font-palette card specimen.
 *
 * Palette typography intervals can request a weight that a family does not
 * actually ship. Google drops an invalid `Family:weight` entry from a batched
 * stylesheet entirely, so fall back to an available face while leaving the
 * card's intended CSS font-weight untouched.
 */
export const buildFontPalettePreviewWebFontConfig = ( familyVariants = {}, fonts = {} ) => {
  const googleFamilies = [];
  const customFamilies = [];
  const customUrls = [];

  Object.entries( familyVariants ).forEach( ( [ family, rawVariants ] ) => {
    const variants = Array.isArray( rawVariants )
      ? rawVariants.filter( variant => null !== variant && 'undefined' !== typeof variant && '' !== variant )
      : [];
    const googleFont = fonts.google_fonts?.[ family ];

    if ( googleFont ) {
      const resolvedVariants = resolveGooglePreviewVariants( variants, googleFont.variants || [] );
      googleFamilies.push( resolvedVariants.length ? `${ family }:${ resolvedVariants.join( ',' ) }` : family );
      return;
    }

    const cloudFont = fonts.cloud_fonts?.[ family ];
    const themeFont = fonts.theme_fonts?.[ family ];
    const customFont = cloudFont?.src ? cloudFont : themeFont;
    if ( ! customFont?.src ) {
      return;
    }

    const customVariants = uniqueStrings( variants.map( convertFontVariantToFVD ) );
    customFamilies.push( customVariants.length ? `${ family }:${ customVariants.join( ',' ) }` : family );
    customUrls.push( customFont.src );
  } );

  const config = { classes: false, events: false };
  if ( googleFamilies.length ) {
    config.google = { families: uniqueStrings( googleFamilies ) };
  }
  if ( customFamilies.length ) {
    config.custom = {
      families: uniqueStrings( customFamilies ),
      urls: uniqueStrings( customUrls ),
    };
  }

  return config;
};

export const applyFontPaletteSelection = (
  {
    fontsLogic = {},
  },
  {
    setFontSetting,
  }
) => {
  Object.entries( fontsLogic ).forEach( ( [ settingID, config ] ) => {
    setFontSetting( settingID, config );
  } );
};
