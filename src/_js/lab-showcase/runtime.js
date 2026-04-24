import { SHOWCASE_READY_MESSAGE, SHOWCASE_UPDATE_MESSAGE } from '../lab/messages.js';
import { normalizeLabState, parseLabState } from '../lab/state.js';

const CONTEXTUAL_ID = 'contextual-lab';
const CONTEXTUAL_LABEL = 'Contextual Lab';
const TOKENS = [ 'bg', 'accent', 'fg1', 'fg2' ];
const MIXES = [
  [ '#ffffff', 0.92 ],
  [ '#ffffff', 0.84 ],
  [ '#ffffff', 0.72 ],
  [ '#ffffff', 0.60 ],
  [ '#ffffff', 0.40 ],
  [ '#ffffff', 0.20 ],
  [ null, 0.00 ],
  [ '#000000', 0.18 ],
  [ '#000000', 0.34 ],
  [ '#000000', 0.50 ],
  [ '#000000', 0.66 ],
  [ '#000000', 0.82 ],
];

const normalizeHex = ( value ) => {
  if ( typeof value !== 'string' ) {
    return '';
  }

  const color = value.trim().toLowerCase();

  return /^#[0-9a-f]{6}$/.test( color ) ? color : '';
};

const normalizeSiteVariation = ( value ) => {
  const parsed = Number.parseInt( value, 10 );

  if ( Number.isNaN( parsed ) ) {
    return 1;
  }

  return Math.min( Math.max( parsed, 1 ), 12 );
};

const hexToRgbChannels = ( color ) => {
  const normalized = color.replace( '#', '' );

  return [ 0, 1, 2 ].map( ( index ) => Number.parseInt( normalized.slice( index * 2, index * 2 + 2 ), 16 ) );
};

const mixHexColors = ( base, mix, ratio ) => {
  const normalizedRatio = Math.min( Math.max( ratio, 0 ), 1 );
  const baseRgb = hexToRgbChannels( base );
  const mixRgb = hexToRgbChannels( mix );

  return `#${ [ 0, 1, 2 ].map( ( index ) => (
    Math.round( baseRgb[ index ] * ( 1 - normalizedRatio ) + mixRgb[ index ] * normalizedRatio )
      .toString( 16 )
      .padStart( 2, '0' )
  ) ).join( '' ) }`;
};

const relativeLuminance = ( color ) => hexToRgbChannels( color )
  .map( ( channel ) => {
    const normalized = channel / 255;

    if ( normalized <= 0.03928 ) {
      return normalized / 12.92;
    }

    return ( ( normalized + 0.055 ) / 1.055 ) ** 2.4;
  } )
  .reduce( ( luminance, channel, index ) => {
    const weights = [ 0.2126, 0.7152, 0.0722 ];

    return luminance + channel * weights[ index ];
  }, 0 );

const getContrastRatio = ( colorA, colorB ) => {
  const luminanceA = relativeLuminance( colorA );
  const luminanceB = relativeLuminance( colorB );
  const light = Math.max( luminanceA, luminanceB );
  const dark = Math.min( luminanceA, luminanceB );

  return ( light + 0.05 ) / ( dark + 0.05 );
};

const pickContextualTextColor = ( background ) => (
  getContrastRatio( background, '#ffffff' ) >= getContrastRatio( background, '#111111' ) ? '#ffffff' : '#111111'
);

const getAccessibleContextualAccent = ( background, source, fallback ) => {
  if ( getContrastRatio( background, source ) >= 2.5 ) {
    return source;
  }

  const enhancedSource = fallback.toLowerCase() === '#ffffff'
    ? mixHexColors( source, '#ffffff', 0.35 )
    : mixHexColors( source, '#000000', 0.35 );

  if ( getContrastRatio( background, enhancedSource ) >= 2.5 ) {
    return enhancedSource;
  }

  return fallback;
};

const buildContextualVariation = ( background, source ) => {
  const foreground = pickContextualTextColor( background );

  return {
    bg: background,
    accent: getAccessibleContextualAccent( background, source, foreground ),
    fg1: foreground,
    fg2: foreground,
  };
};

export const buildContextualPalette = ( color, options = {} ) => {
  const normalizedColor = normalizeHex( color );

  if ( ! normalizedColor ) {
    return null;
  }

  const palette = {
    id: options.id || CONTEXTUAL_ID,
    label: options.label || CONTEXTUAL_LABEL,
    source: [ normalizedColor ],
    sourceIndex: 6,
    variations: [],
    darkVariations: [],
  };

  MIXES.forEach( ( [ reference, ratio ] ) => {
    const background = reference ? mixHexColors( normalizedColor, reference, ratio ) : normalizedColor;
    const darkBackground = mixHexColors( background, '#000000', 0.18 );

    palette.variations.push( buildContextualVariation( background, normalizedColor ) );
    palette.darkVariations.push( buildContextualVariation( darkBackground, normalizedColor ) );
  } );

  return palette;
};

const buildVariationCssVariables = ( variations, index, offset = 0 ) => {
  const variation = variations[ ( index + offset ) % 12 ];

  return Object.entries( variation )
    .map( ( [ key, value ] ) => `--sm-${ key }-color-${ index + 1 }: ${ value };` )
    .join( ' ' );
};

const buildPaletteCssBlock = ( selector, variations, offset ) => `${ selector } { ${ Array.from( { length: 12 }, ( _, index ) => (
  buildVariationCssVariables( variations, index, offset )
) ).join( ' ' ) } }`;

export const buildRuntimePaletteCss = ( palettes = [], siteVariation = 1 ) => {
  const variationOffset = normalizeSiteVariation( siteVariation ) - 1;

  return palettes
    .filter( ( palette ) => palette?.id && Array.isArray( palette.variations ) && palette.variations.length )
    .map( ( palette ) => {
      const selector = `.sm-palette-${ palette.id }`;
      const darkSelector = `.is-dark .sm-palette-${ palette.id }`;
      const shiftedSelector = `.sm-palette-${ palette.id }.sm-palette--shifted`;
      const sourceIndex = Number.parseInt( palette.sourceIndex, 10 );
      const shiftedOffset = Number.isNaN( sourceIndex ) ? 0 : sourceIndex;

      return [
        buildPaletteCssBlock( selector, palette.variations, variationOffset ),
        Array.isArray( palette.darkVariations )
          ? buildPaletteCssBlock( darkSelector, palette.darkVariations, variationOffset )
          : '',
        buildPaletteCssBlock( shiftedSelector, palette.variations, shiftedOffset ),
      ].filter( Boolean ).join( '\n' );
    } )
    .join( '\n' );
};

export const buildContextualPaletteCss = ( palette, siteVariation = 1 ) => {
  if ( ! palette ) {
    return '';
  }

  const variationOffset = normalizeSiteVariation( siteVariation ) - 1;

  return [
    buildPaletteCssBlock( `.sm-palette-${ palette.id }`, palette.variations, variationOffset ),
    buildPaletteCssBlock( `.is-dark .sm-palette-${ palette.id }`, palette.darkVariations, variationOffset ),
    buildPaletteCssBlock( `.sm-palette-${ palette.id }.sm-palette--shifted`, palette.variations, palette.sourceIndex ),
  ].join( '\n' );
};

const replaceClassByPattern = ( element, pattern, nextClass ) => {
  if ( ! element ) {
    return;
  }

  String( element.className || '' )
    .split( /\s+/ )
    .filter( Boolean )
    .forEach( ( className ) => {
      if ( pattern.test( className ) ) {
        element.classList.remove( className );
      }
    } );

  if ( nextClass ) {
    element.classList.add( nextClass );
  }
};

const setStatusValue = ( documentRef, key, value ) => {
  const node = documentRef.querySelector( `[data-sm-lab-status-value="${ key }"]` );

  if ( node ) {
    node.textContent = value;
  }
};

const readResolvedColors = ( { document, getComputedStyle } ) => {
  const source = document.body || document.documentElement;
  const styles = getComputedStyle( source );

  return TOKENS.reduce( ( colors, token ) => {
    colors[ token ] = String( styles.getPropertyValue( `--sm-current-${ token }-color` ) || '' ).trim();
    return colors;
  }, {} );
};

const writeResolvedColors = ( documentRef, colors ) => {
  TOKENS.forEach( ( token ) => {
    const node = documentRef.querySelector( `[data-token="${ token }"] [data-token-value]` );

    if ( node ) {
      node.textContent = colors[ token ] || 'n/a';
    }
  } );
};

const ensureContextualStyleNode = ( documentRef ) => {
  const existing = documentRef.getElementById( 'style-manager-lab-contextual-palette' );

  if ( existing ) {
    return existing;
  }

  const style = documentRef.createElement( 'style' );
  style.setAttribute( 'id', 'style-manager-lab-contextual-palette' );

  if ( documentRef.head ) {
    documentRef.head.appendChild( style );
  } else if ( documentRef.body ) {
    documentRef.body.appendChild( style );
  } else {
    documentRef.documentElement.appendChild( style );
  }

  return style;
};

const ensureRuntimePaletteStyleNode = ( documentRef ) => {
  const existing = documentRef.getElementById( 'style-manager-lab-runtime-palettes' );

  if ( existing ) {
    return existing;
  }

  const style = documentRef.createElement( 'style' );
  style.setAttribute( 'id', 'style-manager-lab-runtime-palettes' );

  if ( documentRef.head ) {
    documentRef.head.appendChild( style );
  } else if ( documentRef.body ) {
    documentRef.body.appendChild( style );
  } else {
    documentRef.documentElement.appendChild( style );
  }

  return style;
};

const updateContextualZone = ( documentRef, state ) => {
  const zone = documentRef.querySelector( `[data-palette="${ CONTEXTUAL_ID }"]` );

  if ( zone ) {
    replaceClassByPattern( zone, /^sm-variation-\d+$/, `sm-variation-${ state.variation }` );
  }
};

const updatePaletteVariationScopes = ( documentRef, state ) => {
  documentRef.querySelectorAll( '[data-palette-variation]' ).forEach( ( element ) => {
    element.setAttribute( 'data-palette-variation', String( state.variation ) );
    replaceClassByPattern( element, /^sm-variation-\d+$/, `sm-variation-${ state.variation }` );
  } );
};

export const applyShowcaseState = ( {
  document,
  getComputedStyle,
  state,
  siteVariation = 1,
  palettes = [],
} ) => {
  const normalizedState = normalizeLabState( state );
  const documentElement = document.documentElement;
  const body = document.body || documentElement;

  documentElement.classList.toggle( 'is-dark', normalizedState.dark );
  body.classList.toggle( 'is-dark', normalizedState.dark );

  replaceClassByPattern( body, /^sm-palette-[a-z0-9_-]+$/i, `sm-palette-${ normalizedState.palette }` );
  replaceClassByPattern( body, /^sm-variation-\d+$/, `sm-variation-${ normalizedState.variation }` );
  body.classList.toggle( 'sm-palette--shifted', normalizedState.shifted );

  setStatusValue( document, 'palette', normalizedState.palette );
  setStatusValue( document, 'variation', String( normalizedState.variation ) );
  setStatusValue( document, 'signal', String( normalizedState.signal ) );
  setStatusValue( document, 'contextual', normalizedState.contextual || 'off' );
  setStatusValue( document, 'dark', normalizedState.dark ? 'on' : 'off' );

  updateContextualZone( document, normalizedState );
  updatePaletteVariationScopes( document, normalizedState );

  const runtimePaletteStyleNode = ensureRuntimePaletteStyleNode( document );
  runtimePaletteStyleNode.textContent = buildRuntimePaletteCss( palettes, normalizedState.variation );

  const contextualPalette = buildContextualPalette( normalizedState.contextual );
  const contextualCss = buildContextualPaletteCss( contextualPalette, normalizedState.variation );
  const contextualStyleNode = ensureContextualStyleNode( document );
  contextualStyleNode.textContent = contextualCss;

  const colors = readResolvedColors( { document, getComputedStyle } );
  writeResolvedColors( document, colors );

  return {
    colors,
    contextualCss,
    contextualPalette,
    state: normalizedState,
  };
};

export const dispatchShowcaseState = ( windowRef, payload ) => {
  if ( typeof windowRef.CustomEvent !== 'function' || typeof windowRef.dispatchEvent !== 'function' ) {
    return;
  }

  windowRef.dispatchEvent( new windowRef.CustomEvent( 'style-manager-lab:showcase-state', {
    detail: {
      state: payload.state,
      siteVariation: payload.state.variation,
    },
  } ) );
};

const publishReadback = ( windowRef, payload ) => {
  if ( ! windowRef.parent ) {
    return;
  }

  windowRef.parent.postMessage( {
    type: SHOWCASE_READY_MESSAGE,
    height: windowRef.document.documentElement.scrollHeight,
    colors: payload.colors,
    contextualPalette: payload.contextualPalette,
  }, windowRef.location.origin );
};

export const installShowcaseRuntime = ( windowRef = window ) => {
  const documentRef = windowRef.document;
  const getComputedStyle = windowRef.getComputedStyle.bind( windowRef );
  let currentState = parseLabState( windowRef.location.search );
  let siteVariation = normalizeSiteVariation( documentRef.body?.getAttribute( 'data-sm-lab-site-variation' ) || 1 );
  const palettes = Array.isArray( windowRef.styleManagerLabColorSystem?.palettes )
    ? windowRef.styleManagerLabColorSystem.palettes
    : [];

  const syncAndPublish = () => {
    const payload = applyShowcaseState( {
      document: documentRef,
      getComputedStyle,
      state: currentState,
      siteVariation,
      palettes,
    } );

    dispatchShowcaseState( windowRef, payload );
    publishReadback( windowRef, payload );
  };

  const scheduleSync = () => {
    if ( typeof windowRef.requestAnimationFrame === 'function' ) {
      windowRef.requestAnimationFrame( syncAndPublish );
      return;
    }

    syncAndPublish();
  };

  const handleMessage = ( event ) => {
    if ( event.origin !== windowRef.location.origin || event.data?.type !== SHOWCASE_UPDATE_MESSAGE ) {
      return;
    }

    currentState = normalizeLabState( event.data.state || {} );
    siteVariation = normalizeSiteVariation( event.data.siteVariation || siteVariation );
    scheduleSync();
  };

  windowRef.addEventListener( 'message', handleMessage );

  if ( documentRef.readyState === 'complete' ) {
    syncAndPublish();
  } else {
    windowRef.addEventListener( 'load', syncAndPublish, { once: true } );
  }

  if ( typeof windowRef.requestAnimationFrame === 'function' ) {
    windowRef.requestAnimationFrame( () => windowRef.requestAnimationFrame( syncAndPublish ) );
  }

  windowRef.setTimeout( syncAndPublish, 250 );

  return () => windowRef.removeEventListener( 'message', handleMessage );
};
