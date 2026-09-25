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

export const CONNECTED_FIELDS_PRESET_SETTING_ID = 'sm_fonts_connected_fields_preset';
export const CONNECTED_FIELDS_PRESET_SOURCE_SETTING_ID = 'sm_fonts_connected_fields_preset_source';
export const CONNECTED_FIELDS_PRESET_SOURCE_USER = 'user';
export const CONNECTED_FIELDS_PRESET_SOURCE_PALETTE = 'palette';

/**
 * The hierarchy preset a palette writes while the site's preset is not user-set,
 * or '' to leave it alone (#204). JS twin of
 * FontPalettes::resolve_palette_connected_fields_preset().
 *
 * A palette without a declared preset restores the setting default, so the
 * previous palette's hierarchy does not linger (System -> Blair -> System
 * round-trips, #206). A preset the theme does not offer is ignored.
 */
export const resolvePaletteConnectedFieldsPreset = ( declaredPreset = '', presetConfig = {} ) => {
  const declared = 'string' === typeof declaredPreset ? declaredPreset.trim() : '';
  const fallback = 'string' === typeof presetConfig?.default ? presetConfig.default : '';
  const target = declared || fallback;

  if ( ! target ) {
    return '';
  }

  const choices = presetConfig?.choices;
  if ( choices && 'object' === typeof choices && Object.keys( choices ).length
    && ! Object.prototype.hasOwnProperty.call( choices, target ) ) {
    return '';
  }

  return target;
};

/**
 * Track who owns the hierarchy preset in an editor session (#204).
 *
 * - `applyPalette()` writes the palette's hierarchy only while the preset is
 *   not user-set, and records `palette` as its source.
 * - `onPresetChange()` runs on every preset change; any change the palette did
 *   not make is the user's choice, so it records `user` and the preset is kept
 *   from then on.
 *
 * `userSet` is the server's classification at load (a saved preset without a
 * source counts as the user's).
 */
export const createConnectedFieldsPresetOwnership = ( { userSet = false } = {} ) => {
  let isUserSet = !! userSet;
  let applyingPalette = false;

  return {
    isUserSet: () => isUserSet,

    applyPalette( declaredPreset, presetConfig, { setPreset, setSource } ) {
      if ( isUserSet ) {
        return '';
      }

      const target = resolvePaletteConnectedFieldsPreset( declaredPreset, presetConfig );
      if ( ! target ) {
        return '';
      }

      applyingPalette = true;
      try {
        setPreset( target );
        setSource( CONNECTED_FIELDS_PRESET_SOURCE_PALETTE );
      } finally {
        applyingPalette = false;
      }

      return target;
    },

    onPresetChange( setSource ) {
      if ( applyingPalette ) {
        return false;
      }

      isUserSet = true;
      setSource( CONNECTED_FIELDS_PRESET_SOURCE_USER );

      return true;
    },
  };
};
