import { SHOWCASE_READY_MESSAGE, SHOWCASE_UPDATE_MESSAGE } from '../lab/messages.js';
import { normalizeLabState, parseLabState } from '../lab/state.js';

const CONTEXTUAL_ID = 'contextual-lab';
const CONTEXTUAL_LABEL = 'Contextual Lab';
const TOKENS = [ 'bg', 'accent', 'fg1', 'fg2' ];
const SIGNAL_LABELS = [ 'None', 'Low', 'Medium', 'High' ];
const LAB_COLOR_SIGNAL_VARIATIONS = [ 1, 2, 5, 9 ];
const COMPONENT_TOKEN_TARGETS = {
  label: {
    kind: 'label',
    selector: '[data-sm-lab-token-target="label"]',
    side: 'left',
    laneOffset: 32,
    terminal: 24,
    x: 0,
    y: 0.5,
  },
  button: {
    kind: 'action',
    selector: '[data-sm-lab-token-target="action"]',
    side: 'top',
    laneOffset: 56,
    x: 0.5,
    y: 0,
  },
  border: {
    kind: 'border',
    selector: '[data-sm-lab-token-target="border"]',
    side: 'bottom',
    terminal: 32,
    x: 0.5,
    y: 1,
  },
};
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

const normalizeVariationValue = ( value ) => {
  const parsed = Number.parseInt( value, 10 );
  const variation = Number.isNaN( parsed ) ? 1 : parsed;

  return ( ( variation + 11 ) % 12 ) + 1;
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

const formatContrastRatio = ( ratio ) => `${ ratio.toFixed( 2 ) }:1`;

const getContrastStatus = ( ratio ) => {
  if ( ratio >= 4.5 ) {
    return 'AA';
  }

  if ( ratio >= 3 ) {
    return 'Large text';
  }

  return 'Needs rescue';
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

export const buildContextualContrastReadout = ( palette, siteVariation = 1, options = {} ) => {
  const emptyReadout = {
    source: 'off',
    surface: 'n/a',
    accent: 'n/a',
    text: 'n/a',
    accentRatio: 'n/a',
    accentStatus: 'Set source color',
    textRatio: 'n/a',
    textStatus: 'Set source color',
  };

  if ( ! palette ) {
    return emptyReadout;
  }

  const variations = options.dark && Array.isArray( palette.darkVariations ) && palette.darkVariations.length
    ? palette.darkVariations
    : palette.variations;
  const variationOffset = normalizeSiteVariation( siteVariation ) - 1;
  const scopeVariation = normalizeSiteVariation( options.scopeVariation || siteVariation ) - 1;
  const variation = variations?.[ ( variationOffset + scopeVariation ) % 12 ];

  if ( ! variation ) {
    return emptyReadout;
  }

  const source = palette.source?.[0] || emptyReadout.source;
  const surface = variation.bg;
  const accent = variation.accent;
  const text = variation.fg1;
  const accentRatioValue = getContrastRatio( surface, accent );
  const textRatioValue = getContrastRatio( surface, text );

  return {
    source,
    surface,
    accent,
    text,
    accentRatio: formatContrastRatio( accentRatioValue ),
    accentStatus: getContrastStatus( accentRatioValue ),
    textRatio: formatContrastRatio( textRatioValue ),
    textStatus: getContrastStatus( textRatioValue ),
  };
};

const buildVariationCssVariables = ( variations, index, offset = 0 ) => {
  const variation = variations[ ( index + offset ) % 12 ];

  return Object.entries( variation )
    .map( ( [ key, value ] ) => `--sm-${ key }-color-${ index + 1 }: ${ value };` )
    .join( ' ' );
};

const buildReferencePaletteCssVariables = ( variations = [] ) => Array.from( { length: 12 }, ( _, index ) => (
  TOKENS.map( ( token ) => {
    const color = variations?.[ index ]?.[ token ] || '';

    return color ? `--sm-lab-reference-${ token }-color-${ index + 1 }: ${ color };` : '';
  } ).filter( Boolean ).join( ' ' )
) ).filter( Boolean ).join( ' ' );

const buildPaletteCssBlock = ( selector, variations, offset, extraVariables = '' ) => `${ selector } { ${ [
  extraVariables,
  Array.from( { length: 12 }, ( _, index ) => (
    buildVariationCssVariables( variations, index, offset )
  ) ).join( ' ' ),
].filter( Boolean ).join( ' ' ) } }`;

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
        buildPaletteCssBlock( selector, palette.variations, variationOffset, buildReferencePaletteCssVariables( palette.variations ) ),
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
    buildPaletteCssBlock( `.sm-palette-${ palette.id }`, palette.variations, variationOffset, buildReferencePaletteCssVariables( palette.variations ) ),
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
  documentRef.querySelectorAll( `[data-sm-lab-status-value="${ key }"]` ).forEach( ( node ) => {
    node.textContent = value;
  } );
};

const readResolvedColors = ( { document, getComputedStyle } ) => {
  const source = document.body || document.documentElement;
  const styles = getComputedStyle( source );

  return TOKENS.reduce( ( colors, token ) => {
    colors[ token ] = String( styles.getPropertyValue( `--sm-current-${ token }-color` ) || '' ).trim();
    return colors;
  }, {} );
};

const findTokenReadbackScope = ( node, token ) => {
  let cursor = node.parentElement;

  while ( cursor ) {
    if ( cursor.getAttribute?.( 'data-token' ) === token ) {
      return cursor;
    }

    cursor = cursor.parentElement;
  }

  return node;
};

const readScopedTokenColor = ( source, token, getComputedStyle ) => (
  String( getComputedStyle( source ).getPropertyValue( `--sm-current-${ token }-color` ) || '' ).trim()
);

const writeResolvedColors = ( documentRef, colors, getComputedStyle ) => {
  TOKENS.forEach( ( token ) => {
    documentRef.querySelectorAll( `[data-token="${ token }"] [data-token-value]` ).forEach( ( node ) => {
      const scope = findTokenReadbackScope( node, token );
      node.textContent = readScopedTokenColor( scope, token, getComputedStyle ) || colors[ token ] || 'n/a';
    } );
  } );
};

const writeContextualProof = ( documentRef, readout ) => {
  Object.entries( {
    source: readout.source,
    surface: readout.surface,
    accent: readout.accent,
    text: readout.text,
    'accent-ratio': readout.accentRatio,
    'accent-status': readout.accentStatus,
    'text-ratio': readout.textRatio,
    'text-status': readout.textStatus,
  } ).forEach( ( [ key, value ] ) => {
    documentRef.querySelectorAll( `[data-sm-lab-contextual-value="${ key }"]` ).forEach( ( node ) => {
      node.textContent = value;
    } );
  } );

  Object.entries( {
    source: readout.source,
    surface: readout.surface,
    accent: readout.accent,
    text: readout.text,
  } ).forEach( ( [ key, value ] ) => {
    documentRef.querySelectorAll( `[data-sm-lab-contextual-swatch="${ key }"]` ).forEach( ( node ) => {
      node.setAttribute( 'style', `background: ${ normalizeHex( value ) || 'transparent' };` );
    } );
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
    if ( element.getAttribute?.( 'data-color-signal' ) !== null ) {
      return;
    }

    element.setAttribute( 'data-palette-variation', String( state.variation ) );
    replaceClassByPattern( element, /^sm-variation-\d+$/, `sm-variation-${ state.variation }` );
  } );
};

const getSignalAttribute = ( element, name ) => element.getAttribute?.( `data-${ name }` );

const getSignalsForPalette = ( windowRef, paletteId ) => {
  return LAB_COLOR_SIGNAL_VARIATIONS;
};

const getPaletteConfig = ( windowRef, paletteId ) => {
  const palettes = Array.isArray( windowRef?.styleManager?.colorsConfig )
    ? windowRef.styleManager.colorsConfig
    : windowRef?.styleManagerLabColorSystem?.palettes;

  if ( ! Array.isArray( palettes ) || ! palettes.length ) {
    return { sourceIndex: 6 };
  }

  return palettes.find( ( palette ) => `${ palette.id }` === `${ paletteId }` ) || palettes[0];
};

const addSiteVariationOffset = ( variation, siteVariation ) => (
  normalizeVariationValue( normalizeVariationValue( variation ) + normalizeVariationValue( siteVariation ) - 1 )
);

const removeSiteVariationOffset = ( variation, siteVariation ) => (
  normalizeVariationValue( normalizeVariationValue( variation ) - normalizeVariationValue( siteVariation ) + 1 )
);

const getSourceIndexFromPaletteId = ( windowRef, paletteId, siteVariation ) => {
  const paletteConfig = getPaletteConfig( windowRef, paletteId );
  const sourceIndex = Number.parseInt( paletteConfig.sourceIndex, 10 );
  const normalizedSourceIndex = Number.isNaN( sourceIndex ) ? 6 : sourceIndex;

  return ( normalizedSourceIndex - normalizeVariationValue( siteVariation ) + 13 ) % 12;
};

const getSignalOptionsFromVariation = ( reference, paletteId, windowRef ) => {
  const normalizedReference = normalizeVariationValue( reference );
  const variationOptions = [ ...getSignalsForPalette( windowRef, paletteId ) ];

  variationOptions.sort( ( variationA, variationB ) => (
    Math.abs( normalizedReference - variationA ) - Math.abs( normalizedReference - variationB )
  ) );

  variationOptions[0] = normalizedReference;

  return variationOptions;
};

const getPaletteVariationColors = ( palette ) => {
  if ( Array.isArray( palette?.variations ) ) {
    return palette.variations.map( ( variation ) => variation?.bg );
  }

  if ( Array.isArray( palette?.colors ) ) {
    return palette.colors.map( ( color ) => color?.value );
  }

  return null;
};

const getSignalRelativeToVariation = ( compared, reference, paletteId, windowRef ) => {
  const normalizedCompared = normalizeVariationValue( compared );
  const normalizedReference = normalizeVariationValue( reference );
  const variationOptions = getSignalOptionsFromVariation( normalizedReference, paletteId, windowRef );
  const signal = variationOptions.reduce( ( closestIndex, currentVariation, currentIndex, options ) => (
    Math.abs( currentVariation - normalizedCompared ) < Math.abs( options[ closestIndex ] - normalizedCompared )
      ? currentIndex
      : closestIndex
  ), 0 );
  const paletteColors = getPaletteVariationColors( getPaletteConfig( windowRef, paletteId ) );

  if ( ! paletteColors ) {
    return signal;
  }

  return paletteColors[ normalizedCompared - 1 ] === paletteColors[ normalizedReference - 1 ]
    ? 0
    : Math.max( 1, signal );
};

const computeColorSignal = ( reference, colorSignal, paletteId, paletteVariation, windowRef ) => {
  const signalOptions = getSignalOptionsFromVariation( reference, paletteId, windowRef );
  const normalizedSignal = Math.max( 0, Number.isNaN( colorSignal ) ? 0 : colorSignal );

  if ( Number.isInteger( paletteVariation ) ) {
    const currentSignal = getSignalRelativeToVariation( paletteVariation, reference, paletteId, windowRef );

    if ( currentSignal === normalizedSignal ) {
      return paletteVariation;
    }
  }

  return signalOptions[ Math.min( signalOptions.length - 1, normalizedSignal ) ];
};

const isLightNovaVariation = ( windowRef, paletteId, variation ) => {
  const palette = getPaletteConfig( windowRef, paletteId );
  const variationIndex = normalizeVariationValue( variation ) - 1;
  const foreground = normalizeHex(
    palette?.variations?.[ variationIndex ]?.fg1
    || palette?.colors?.[ variationIndex ]?.value
    || ''
  );

  if ( ! foreground ) {
    return true;
  }

  return getContrastRatio( '#ffffff', foreground ) > Math.sqrt( 21 );
};

const removeNovaSignalClasses = ( element ) => {
  String( element.className || '' )
    .split( /\s+/ )
    .filter( Boolean )
    .forEach( ( className ) => {
      if (
        /^sm-palette-[a-z0-9_-]+$/i.test( className )
        || /^sm-variation-\d+$/.test( className )
        || /^sm-color-signal-\d+$/.test( className )
        || className === 'sm-light'
        || className === 'sm-dark'
        || className === 'sm-palette--shifted'
      ) {
        element.classList.remove( className );
      }
    } );
};

const addNovaSignalClasses = ( element, classNames ) => {
  String( classNames || '' )
    .split( /\s+/ )
    .filter( Boolean )
    .forEach( ( className ) => element.classList.add( className ) );
};

const buildNovaSignalClassNames = ( windowRef, attributes ) => {
  const getColorSignalClassnames = windowRef?.novablocks?.utils?.getColorSignalClassnames;

  if ( typeof getColorSignalClassnames === 'function' ) {
    const classNames = getColorSignalClassnames( attributes, true );

    if ( Array.isArray( classNames ) ) {
      return classNames.join( ' ' );
    }

    if ( typeof classNames === 'string' ) {
      return classNames;
    }
  }

  return [
    `sm-palette-${ attributes.palette }`,
    `sm-variation-${ attributes.paletteVariation }`,
    `sm-color-signal-${ attributes.colorSignal }`,
    attributes.useSourceColorAsReference ? 'sm-palette--shifted' : '',
  ].filter( Boolean ).join( ' ' );
};

const syncNovaSignalScope = ( element, parentVariation, windowRef, siteVariation ) => {
  const colorSignalAttribute = getSignalAttribute( element, 'color-signal' );
  const children = Array.from( element.children || [] );

  if ( colorSignalAttribute === null ) {
    children.forEach( ( child ) => syncNovaSignalScope( child, parentVariation, windowRef, siteVariation ) );
    return;
  }

  const palette = getSignalAttribute( element, 'palette' );

  if ( ! palette ) {
    children.forEach( ( child ) => syncNovaSignalScope( child, parentVariation, windowRef, siteVariation ) );
    return;
  }

  const parsedColorSignal = Number.parseInt( colorSignalAttribute, 10 );
  const colorSignal = Number.isNaN( parsedColorSignal ) ? 0 : Math.max( 0, parsedColorSignal );
  const parsedPaletteVariation = Number.parseInt( getSignalAttribute( element, 'palette-variation' ), 10 );
  const paletteVariation = normalizeVariationValue( Number.isNaN( parsedPaletteVariation ) ? 1 : parsedPaletteVariation );
  const useSourceColorAsReference = [ '1', 'true', 'yes' ].includes(
    String( getSignalAttribute( element, 'use-source-color-as-reference' ) || '' ).toLowerCase()
  );
  const sourceIndex = getSourceIndexFromPaletteId( windowRef, palette, siteVariation );
  const absoluteVariation = addSiteVariationOffset(
    useSourceColorAsReference ? sourceIndex + 1 : paletteVariation,
    siteVariation
  );
  const nextVariation = computeColorSignal( parentVariation, colorSignal, palette, absoluteVariation, windowRef );
  const finalVariation = useSourceColorAsReference ? 1 : removeSiteVariationOffset( nextVariation, siteVariation );
  const finalAbsoluteVariation = useSourceColorAsReference
    ? addSiteVariationOffset( sourceIndex + 1, siteVariation )
    : addSiteVariationOffset( finalVariation, siteVariation );
  const classNames = buildNovaSignalClassNames( windowRef, {
    palette,
    paletteVariation: finalVariation,
    useSourceColorAsReference,
    colorSignal,
  } );
  const isLight = isLightNovaVariation( windowRef, palette, finalAbsoluteVariation );

  removeNovaSignalClasses( element );
  addNovaSignalClasses( element, classNames );
  element.classList.toggle( 'sm-light', isLight );
  element.classList.toggle( 'sm-dark', ! isLight );

  children.forEach( ( child ) => syncNovaSignalScope( child, finalAbsoluteVariation, windowRef, siteVariation ) );
};

const hasNovaSignalAncestor = ( element ) => {
  let cursor = element.parentElement;

  while ( cursor ) {
    if ( cursor.getAttribute?.( 'data-color-signal' ) !== null ) {
      return true;
    }

    cursor = cursor.parentElement;
  }

  return false;
};

const syncNovaSignalScopes = ( documentRef, windowRef, state ) => {
  if ( ! windowRef.styleManager ) {
    windowRef.styleManager = {};
  }

  windowRef.styleManager.siteColorVariation = state.variation;

  Array.from( documentRef.querySelectorAll( '[data-color-signal]' ) )
    .filter( ( element ) => element.getAttribute?.( 'data-sm-lab-signal-preview' ) === null && ! hasNovaSignalAncestor( element ) )
    .forEach( ( element ) => {
      syncNovaSignalScope( element, state.variation, windowRef, state.variation );
    } );
};

const getInheritedVariation = ( state ) => normalizeSiteVariation( state.variation );

const normalizeSignalValue = ( signal ) => {
  const parsed = Number.parseInt( signal, 10 );

  if ( Number.isNaN( parsed ) ) {
    return 0;
  }

  return Math.min( Math.max( parsed, 0 ), 3 );
};

const getSignalVariationFromReference = ( reference, signal, paletteId, windowRef ) => normalizeVariationValue(
  computeColorSignal( normalizeSiteVariation( reference ), normalizeSignalValue( signal ), paletteId, false, windowRef )
);

const getSignalResultVariation = ( state, windowRef ) => (
  getSignalVariationFromReference( getInheritedVariation( state ), state.signal, state.palette, windowRef )
);

const getContrastGrade = ( grade ) => ( grade >= 5 ? 1 : 12 );

const getComponentTokenGrades = ( signalVariation ) => ( {
  label: getContrastGrade( signalVariation ),
  button: signalVariation,
  border: Math.min( 12, signalVariation + 2 ),
} );

const formatWireNumber = ( value ) => {
  const rounded = Math.round( value * 100 ) / 100;

  return Number.isInteger( rounded ) ? String( rounded ) : String( rounded );
};

const readWireRect = ( element ) => {
  if ( ! element || typeof element.getBoundingClientRect !== 'function' ) {
    return null;
  }

  const rect = element.getBoundingClientRect();
  const left = Number( rect.left ) || 0;
  const top = Number( rect.top ) || 0;
  const width = Number( rect.width ) || 0;
  const height = Number( rect.height ) || 0;

  return {
    left,
    top,
    width,
    height,
    right: Number( rect.right ) || left + width,
    bottom: Number( rect.bottom ) || top + height,
  };
};

const getRelativePoint = ( rect, containerRect, xRatio, yRatio ) => ( {
  x: rect.left - containerRect.left + rect.width * xRatio,
  y: rect.top - containerRect.top + rect.height * yRatio,
} );

const getComponentTargetPoint = ( rect, containerRect, target ) => getRelativePoint(
  rect,
  containerRect,
  typeof target.x === 'number' ? target.x : 0.5,
  typeof target.y === 'number' ? target.y : 0
);

const filterWirePoints = ( points ) => points.filter( ( point, index ) => {
  const previous = points[ index - 1 ];

  return ! previous || Math.abs( point.x - previous.x ) > 0.5 || Math.abs( point.y - previous.y ) > 0.5;
} );

const getTurnPoint = ( point, adjacent, radius ) => {
  if ( Math.abs( adjacent.x - point.x ) > Math.abs( adjacent.y - point.y ) ) {
    return {
      x: point.x + Math.sign( adjacent.x - point.x ) * radius,
      y: point.y,
    };
  }

  return {
    x: point.x,
    y: point.y + Math.sign( adjacent.y - point.y ) * radius,
  };
};

const buildRoundedOrthogonalPath = ( points ) => {
  const filteredPoints = filterWirePoints( points );
  const [ firstPoint ] = filteredPoints;

  if ( filteredPoints.length < 2 || ! firstPoint ) {
    return '';
  }

  const path = [
    'M',
    formatWireNumber( firstPoint.x ),
    formatWireNumber( firstPoint.y ),
  ];

  filteredPoints.slice( 1, -1 ).forEach( ( point, index ) => {
    const previous = filteredPoints[ index ];
    const next = filteredPoints[ index + 2 ];
    const previousDistance = Math.abs( point.x - previous.x ) + Math.abs( point.y - previous.y );
    const nextDistance = Math.abs( next.x - point.x ) + Math.abs( next.y - point.y );
    const radius = Math.min( 10, previousDistance / 2, nextDistance / 2 );

    if ( radius < 1 ) {
      path.push( 'L', formatWireNumber( point.x ), formatWireNumber( point.y ) );
      return;
    }

    const beforePoint = getTurnPoint( point, previous, radius );
    const afterPoint = getTurnPoint( point, next, radius );

    path.push(
      'L',
      formatWireNumber( beforePoint.x ),
      formatWireNumber( beforePoint.y ),
      'Q',
      formatWireNumber( point.x ),
      formatWireNumber( point.y ),
      formatWireNumber( afterPoint.x ),
      formatWireNumber( afterPoint.y )
    );
  } );

  const lastPoint = filteredPoints[ filteredPoints.length - 1 ];
  path.push( 'L', formatWireNumber( lastPoint.x ), formatWireNumber( lastPoint.y ) );

  return path.join( ' ' );
};

const buildComponentWirePath = ( source, target, targetConfig ) => {
  const terminal = Number.isFinite( targetConfig.terminal ) ? targetConfig.terminal : 24;
  const side = targetConfig.side || 'top';
  const laneY = 'bottom' === side
    ? target.y + terminal
    : source.y + ( Number.isFinite( targetConfig.laneOffset ) ? targetConfig.laneOffset : 24 );
  const points = [
    source,
    { x: source.x, y: laneY },
  ];

  if ( 'left' === side ) {
    const entryX = target.x - terminal;
    points.push( { x: entryX, y: laneY }, { x: entryX, y: target.y }, target );
  } else if ( 'right' === side ) {
    const entryX = target.x + terminal;
    points.push( { x: entryX, y: laneY }, { x: entryX, y: target.y }, target );
  } else {
    points.push( { x: target.x, y: laneY }, target );
  }

  return buildRoundedOrthogonalPath( points );
};

const findComponentSourceSwatch = ( map, part ) => (
  Array.from( map.querySelectorAll( `[data-sm-lab-token-source-chip="${ part }"]` ) )
    .find( ( source ) => source.getAttribute( 'data-active' ) === 'true' )
  ||
  Array.from( map.querySelectorAll( '[data-sm-lab-grade-swatch]' ) )
    .find( ( swatch ) => swatch.getAttribute( `data-token-${ part }-active` ) === 'true' )
);

const findComponentTokenTarget = ( map, target ) => (
  map.querySelector( target.selector )
);

const getWireLength = ( path ) => {
  if ( path && typeof path.getTotalLength === 'function' ) {
    const length = Number( path.getTotalLength() );

    if ( Number.isFinite( length ) && length > 0 ) {
      return Math.ceil( length );
    }
  }

  return 640;
};

const setWireDrawStyle = ( path, length, offset ) => {
  path.setAttribute(
    'style',
    `stroke-dasharray: ${ length }; stroke-dashoffset: ${ offset };`
  );
};

const animateWirePathChange = ( path, previousPath, nextPath, windowRef ) => {
  if ( ! previousPath || previousPath === nextPath ) {
    path.removeAttribute?.( 'data-sm-lab-token-wire-motion' );
    return;
  }

  const length = getWireLength( path );
  path.setAttribute( 'data-sm-lab-token-wire-motion', 'draw' );
  setWireDrawStyle( path, length, length );

  const finishAnimation = () => setWireDrawStyle( path, length, 0 );

  if ( typeof windowRef?.requestAnimationFrame === 'function' ) {
    windowRef.requestAnimationFrame( finishAnimation );
    return;
  }

  finishAnimation();
};

const buildReferenceGradeColor = ( grade, fallback = 'var(--sm-current-bg-color)' ) => (
  `var(--sm-lab-reference-bg-color-${ grade }, var(--sm-bg-color-${ grade }, ${ fallback }))`
);

const buildComponentTokenStyle = ( grades ) => [
  `--sm-lab-component-label-color: ${ buildReferenceGradeColor( grades.label ) };`,
  `--sm-lab-component-button-color: ${ buildReferenceGradeColor( grades.button, 'var(--sm-current-accent-color)' ) };`,
  `--sm-lab-component-border-color: ${ buildReferenceGradeColor( grades.border, 'var(--sm-current-fg2-color)' ) };`,
].join( ' ' );

const buildSignalCascadeStyle = ( resolvedGrade, textGrade ) => [
  `--sm-lab-cascade-surface-color: ${ buildReferenceGradeColor( resolvedGrade ) };`,
  `--sm-lab-cascade-text-color: ${ buildReferenceGradeColor( textGrade, 'var(--sm-current-fg1-color)' ) };`,
  `--sm-lab-cascade-border-color: ${ buildReferenceGradeColor( Math.min( 12, resolvedGrade + 1 ), 'var(--sm-current-fg2-color)' ) };`,
].join( ' ' );

const setSignalCascadeValue = ( node, key, value ) => {
  node.querySelectorAll( `[data-sm-lab-cascade-value="${ key }"]` ).forEach( ( valueNode ) => {
    valueNode.textContent = String( value );
  } );
};

const updateCascadeSignalUi = ( node, signal ) => {
  const label = SIGNAL_LABELS[ signal ] || SIGNAL_LABELS[0];
  const control = node.querySelector( '[data-sm-lab-cascade-signal-control]' );
  const title = node.querySelector( 'strong' )?.textContent?.trim() || 'block';

  if ( control ) {
    control.setAttribute( 'aria-label', `Change ${ title } Color Signal. Current: ${ label }` );
  }

  node.querySelectorAll( '[data-sm-lab-cascade-signal-bar]' ).forEach( ( bar ) => {
    const barValue = Number( bar.getAttribute( 'data-sm-lab-cascade-signal-bar' ) );
    bar.classList.toggle( 'is-active', barValue <= signal );
  } );

  node.querySelectorAll( '[data-sm-lab-cascade-chip-signal-label]' ).forEach( ( chip ) => {
    chip.textContent = `Color Signal: ${ label }`;
  } );

  node.querySelectorAll( '[data-sm-lab-cascade-chip-signal-input]' ).forEach( ( chip ) => {
    chip.setAttribute( 'data-sm-lab-cascade-chip-signal-input', String( signal ) );
  } );

  node.querySelectorAll( '[data-sm-lab-cascade-chip-signal-input-code]' ).forEach( ( chip ) => {
    chip.textContent = `data-color-signal="${ signal }"`;
  } );

  setSignalCascadeValue( node, 'signal', label );
};

const wireCascadeInteractions = ( cascade, documentRef ) => {
  if ( cascade.getAttribute( 'data-sm-lab-cascade-bond' ) === 'true' ) {
    return;
  }

  const setHighlight = ( id ) => {
    if ( id ) {
      cascade.setAttribute( 'data-sm-lab-cascade-highlight', id );
      return;
    }

    cascade.removeAttribute?.( 'data-sm-lab-cascade-highlight' );
  };

  cascade.addEventListener( 'pointerover', ( event ) => {
    const node = event.target.closest?.( '[data-sm-lab-cascade-node]' );
    setHighlight( node?.getAttribute( 'data-sm-lab-cascade-node' ) || null );
  } );
  cascade.addEventListener( 'pointerleave', () => setHighlight( null ) );
  cascade.addEventListener( 'focusin', ( event ) => {
    const node = event.target.closest?.( '[data-sm-lab-cascade-node]' );
    setHighlight( node?.getAttribute( 'data-sm-lab-cascade-node' ) || null );
  } );
  cascade.addEventListener( 'focusout', () => setHighlight( null ) );
  cascade.addEventListener( 'click', ( event ) => {
    const control = event.target.closest?.( '[data-sm-lab-cascade-signal-control]' );

    if ( ! control ) {
      return;
    }

    event.preventDefault?.();

    const node = control.closest?.( '[data-sm-lab-cascade-node]' );
    const state = cascade.__smLabCascadeState;

    if ( ! node || ! state ) {
      return;
    }

    const nextSignal = ( normalizeSignalValue( node.getAttribute( 'data-sm-lab-cascade-signal' ) ) + 1 ) % SIGNAL_LABELS.length;
    node.setAttribute( 'data-sm-lab-cascade-signal', String( nextSignal ) );
    updateSignalCascade( documentRef, state, cascade.__smLabCascadeWindowRef || {} );
  } );
  cascade.setAttribute( 'data-sm-lab-cascade-bond', 'true' );
};

const updateSignalCascade = ( documentRef, state, windowRef ) => {
  const inheritedVariation = getInheritedVariation( state );

  documentRef.querySelectorAll( '[data-sm-lab-signal-cascade]' ).forEach( ( cascade ) => {
    const resolvedByNode = new Map();

    cascade.__smLabCascadeState = state;
    cascade.__smLabCascadeWindowRef = windowRef;
    wireCascadeInteractions( cascade, documentRef );
    cascade.setAttribute( 'data-sm-lab-cascade-palette', state.palette );
    cascade.setAttribute( 'data-sm-lab-cascade-active-signal', String( state.signal ) );

    Array.from( cascade.querySelectorAll( '[data-sm-lab-cascade-node]' ) ).forEach( ( node ) => {
      const id = node.getAttribute( 'data-sm-lab-cascade-node' ) || '';
      const parentId = node.getAttribute( 'data-sm-lab-cascade-parent' ) || '';
      const signal = normalizeSignalValue( node.getAttribute( 'data-sm-lab-cascade-signal' ) );
      const parentGrade = parentId && resolvedByNode.has( parentId )
        ? resolvedByNode.get( parentId )
        : inheritedVariation;
      const resolvedGrade = getSignalVariationFromReference( parentGrade, signal, state.palette, windowRef );
      const textGrade = getContrastGrade( resolvedGrade );
      const isActiveSignal = signal === normalizeSignalValue( state.signal );

      if ( id ) {
        resolvedByNode.set( id, resolvedGrade );
      }

      node.setAttribute( 'data-sm-lab-cascade-parent-grade', String( parentGrade ) );
      node.setAttribute( 'data-sm-lab-cascade-resolved-grade', String( resolvedGrade ) );
      node.setAttribute( 'data-sm-lab-cascade-text-grade', String( textGrade ) );
      node.setAttribute( 'data-sm-lab-cascade-active', isActiveSignal ? 'true' : 'false' );
      node.setAttribute( 'style', buildSignalCascadeStyle( resolvedGrade, textGrade ) );

      const rail = node.querySelector( '[data-sm-lab-cascade-rail]' );
      if ( rail ) {
        rail.querySelectorAll( '[data-sm-lab-cascade-rail-grade]' ).forEach( ( segment ) => {
          const grade = Number( segment.getAttribute( 'data-sm-lab-cascade-rail-grade' ) );
          const marker = grade === parentGrade ? 'parent' : ( grade === resolvedGrade ? 'resolved' : 'none' );
          segment.setAttribute( 'data-sm-lab-cascade-rail-marker', marker );
          segment.setAttribute( 'style', `background: ${ buildReferenceGradeColor( grade ) };` );
        } );
      }

      const scopeChip = node.querySelector( '[data-sm-lab-cascade-chip-scope]' );
      if ( scopeChip ) {
        scopeChip.textContent = `.sm-palette-${ state.palette }.sm-variation-${ resolvedGrade }`;
      }

      if ( id === 'second-inner' ) {
        const inversion = cascade.querySelector( '[data-sm-lab-cascade-inversion="second-inner"]' );
        if ( inversion ) {
          inversion.setAttribute(
            'data-sm-lab-cascade-inversion-visible',
            resolvedGrade !== parentGrade ? 'true' : 'false'
          );
        }
      }

      const previewNode = cascade.querySelector( `[data-sm-lab-cascade-preview-node="${ id }"]` );
      if ( previewNode ) {
        previewNode.setAttribute( 'style', buildSignalCascadeStyle( resolvedGrade, textGrade ) );
      }

      updateCascadeSignalUi( node, signal );
      setSignalCascadeValue( node, 'parent', parentGrade );
      setSignalCascadeValue( node, 'resolved', resolvedGrade );
    } );
  } );
};

const updateButtonTokenSourceWires = ( documentRef, windowRef = {} ) => {
  documentRef.querySelectorAll( '[data-sm-lab-button-token-map]' ).forEach( ( map ) => {
    const containerRect = readWireRect( map );
    const svg = map.querySelector( '[data-sm-lab-token-source-wires]' );

    if ( ! containerRect || ! svg ) {
      return;
    }

    svg.setAttribute( 'viewBox', `0 0 ${ formatWireNumber( containerRect.width ) } ${ formatWireNumber( containerRect.height ) }` );

    Object.entries( COMPONENT_TOKEN_TARGETS ).forEach( ( [ part, targetConfig ] ) => {
      const path = map.querySelector( `[data-sm-lab-token-source-wire="${ part }"]` );
      const source = findComponentSourceSwatch( map, part );
      const target = findComponentTokenTarget( map, targetConfig );
      const sourceRect = readWireRect( source );
      const targetRect = readWireRect( target );

      if ( ! path || ! source || ! target || ! sourceRect || ! targetRect ) {
        path?.setAttribute( 'data-sm-lab-token-wire-active', 'false' );
        path?.removeAttribute?.( 'd' );
        return;
      }

      const sourcePoint = getRelativePoint( sourceRect, containerRect, 0.5, 1 );
      const targetPoint = getComponentTargetPoint( targetRect, containerRect, targetConfig );
      const sourceGrade = source.getAttribute( 'data-sm-lab-grade-swatch' )
        || source.closest?.( '[data-sm-lab-grade-swatch]' )?.getAttribute?.( 'data-sm-lab-grade-swatch' )
        || '';

      const nextPath = buildComponentWirePath( sourcePoint, targetPoint, targetConfig );
      const previousPath = path.getAttribute( 'd' );

      path.setAttribute( 'd', nextPath );
      path.setAttribute( 'data-sm-lab-token-source-grade', sourceGrade );
      path.setAttribute( 'data-sm-lab-token-target-kind', targetConfig.kind );
      path.setAttribute( 'data-sm-lab-token-entry-side', targetConfig.side || 'top' );
      path.setAttribute( 'data-sm-lab-token-wire-active', 'true' );
      animateWirePathChange( path, previousPath, nextPath, windowRef );
    } );
  } );
};

const syncButtonTokenSourceWires = ( documentRef, windowRef ) => {
  updateButtonTokenSourceWires( documentRef, windowRef );

  if ( typeof windowRef?.requestAnimationFrame === 'function' ) {
    windowRef.requestAnimationFrame( () => updateButtonTokenSourceWires( documentRef, windowRef ) );
  }

  if ( typeof windowRef?.setTimeout === 'function' ) {
    windowRef.setTimeout( () => updateButtonTokenSourceWires( documentRef, windowRef ), 190 );
  }
};

const updateSignalPreviewScopes = ( documentRef, state, windowRef ) => {
  const signalVariation = getSignalResultVariation( state, windowRef );

  documentRef.querySelectorAll( '[data-sm-lab-signal-preview]' ).forEach( ( node ) => {
    const keepsStableSurface = node.getAttribute( 'data-sm-lab-signal-preview-surface' ) === 'stable';
    const previewVariation = keepsStableSurface ? state.variation : signalVariation;

    node.setAttribute( 'data-palette', state.palette );
    node.setAttribute( 'data-palette-variation', String( previewVariation ) );
    node.setAttribute( 'data-color-signal', String( state.signal ) );
    replaceClassByPattern( node, /^sm-palette-[a-z0-9_-]+$/i, `sm-palette-${ state.palette }` );
    replaceClassByPattern( node, /^sm-variation-\d+$/, `sm-variation-${ previewVariation }` );
    replaceClassByPattern( node, /^sm-color-signal-\d+$/, keepsStableSurface ? '' : `sm-color-signal-${ state.signal }` );
  } );
};

const writeVisualProofState = ( documentRef, state, windowRef ) => {
  const inheritedVariation = getInheritedVariation( state );
  const signalVariation = getSignalResultVariation( state, windowRef );
  const signalShifted = signalVariation !== inheritedVariation;
  const componentTokenGrades = getComponentTokenGrades( signalVariation );

  documentRef.querySelectorAll( '[data-sm-lab-grade-swatch]' ).forEach( ( node ) => {
    const grade = node.getAttribute( 'data-sm-lab-grade-swatch' );
    const isActive = grade === String( inheritedVariation );
    const isSignalActive = grade === String( signalVariation );
    node.setAttribute( 'data-active', isActive ? 'true' : 'false' );
    node.setAttribute( 'data-parent-active', isActive ? 'true' : 'false' );
    node.setAttribute( 'data-signal-active', isSignalActive ? 'true' : 'false' );
    node.setAttribute( 'data-resolved-active', isSignalActive ? 'true' : 'false' );
    node.setAttribute( 'data-token-label-active', grade === String( componentTokenGrades.label ) ? 'true' : 'false' );
    node.setAttribute( 'data-token-button-active', grade === String( componentTokenGrades.button ) ? 'true' : 'false' );
    node.setAttribute( 'data-token-border-active', grade === String( componentTokenGrades.border ) ? 'true' : 'false' );

    node.querySelectorAll( '[data-sm-lab-token-source-chip]' ).forEach( ( chip ) => {
      const part = chip.getAttribute( 'data-sm-lab-token-source-chip' );
      const isSourceActive = part && grade === String( componentTokenGrades[ part ] );

      chip.setAttribute( 'data-active', isSourceActive ? 'true' : 'false' );
    } );
  } );

  documentRef.querySelectorAll( '[data-sm-lab-grade-rail]' ).forEach( ( node ) => {
    node.setAttribute( 'data-sm-lab-parent-grade', String( inheritedVariation ) );
    node.setAttribute( 'data-sm-lab-resolved-grade', String( signalVariation ) );
    node.setAttribute( 'data-sm-lab-signal-shifted', signalShifted ? 'true' : 'false' );
  } );

  documentRef.querySelectorAll( '[data-sm-lab-signal-option]' ).forEach( ( node ) => {
    const isActive = node.getAttribute( 'data-sm-lab-signal-option' ) === String( state.signal );
    node.setAttribute( 'data-active', isActive ? 'true' : 'false' );
  } );

  Object.entries( {
    signal: state.signal,
    parent: inheritedVariation,
    variation: signalVariation,
  } ).forEach( ( [ key, value ] ) => {
    documentRef.querySelectorAll( `[data-sm-lab-signal-result="${ key }"]` ).forEach( ( node ) => {
      node.textContent = String( value );
    } );
  } );

  Object.entries( componentTokenGrades ).forEach( ( [ key, value ] ) => {
    documentRef.querySelectorAll( `[data-sm-lab-component-grade="${ key }"]` ).forEach( ( node ) => {
      node.textContent = String( value );
    } );
  } );

  documentRef.querySelectorAll( '[data-sm-lab-button-token-map]' ).forEach( ( node ) => {
    node.setAttribute( 'style', buildComponentTokenStyle( componentTokenGrades ) );

    Object.entries( componentTokenGrades ).forEach( ( [ key, value ] ) => {
      node.setAttribute( `data-sm-lab-token-source-grade-${ key }`, String( value ) );
    } );
  } );

  updateSignalCascade( documentRef, state, windowRef );
};

export const applyShowcaseState = ( {
  document,
  getComputedStyle,
  state,
  siteVariation = 1,
  palettes = [],
  windowRef = document?.defaultView || {},
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
  writeVisualProofState( document, normalizedState, windowRef );

  const runtimePaletteStyleNode = ensureRuntimePaletteStyleNode( document );
  runtimePaletteStyleNode.textContent = buildRuntimePaletteCss( palettes, normalizedState.variation );

  const contextualPalette = buildContextualPalette( normalizedState.contextual );
  const contextualCss = buildContextualPaletteCss( contextualPalette, normalizedState.variation );
  const contextualStyleNode = ensureContextualStyleNode( document );
  contextualStyleNode.textContent = contextualCss;
  const contextualReadout = buildContextualContrastReadout( contextualPalette, normalizedState.variation, {
    dark: normalizedState.dark,
  } );

  syncNovaSignalScopes( document, windowRef, normalizedState );
  updateSignalPreviewScopes( document, normalizedState, windowRef );

  const colors = readResolvedColors( { document, getComputedStyle } );
  writeResolvedColors( document, colors, getComputedStyle );
  writeContextualProof( document, contextualReadout );
  syncButtonTokenSourceWires( document, windowRef );

  return {
    colors,
    contextualCss,
    contextualPalette,
    contextualReadout,
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
    contextualReadout: payload.contextualReadout,
  }, windowRef.location.origin );
};

export const installShowcaseRuntime = ( windowRef = window ) => {
  const documentRef = windowRef.document;
  const getComputedStyle = windowRef.getComputedStyle.bind( windowRef );
  let currentState = parseLabState( windowRef.location.search );
  let siteVariation = normalizeSiteVariation( documentRef.body?.getAttribute( 'data-sm-lab-site-variation' ) || 1 );
  let resizeFrame = null;
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

  const handleResize = () => {
    if ( typeof windowRef.requestAnimationFrame !== 'function' ) {
      syncAndPublish();
      return;
    }

    if ( resizeFrame ) {
      windowRef.cancelAnimationFrame?.( resizeFrame );
    }

    resizeFrame = windowRef.requestAnimationFrame( () => {
      resizeFrame = null;
      syncAndPublish();
    } );
  };

  windowRef.addEventListener( 'message', handleMessage );
  windowRef.addEventListener( 'resize', handleResize );

  if ( documentRef.readyState === 'complete' ) {
    syncAndPublish();
  } else {
    windowRef.addEventListener( 'load', syncAndPublish, { once: true } );
  }

  if ( typeof windowRef.requestAnimationFrame === 'function' ) {
    windowRef.requestAnimationFrame( () => windowRef.requestAnimationFrame( syncAndPublish ) );
  }

  windowRef.setTimeout( syncAndPublish, 250 );

  return () => {
    windowRef.removeEventListener( 'message', handleMessage );
    windowRef.removeEventListener( 'resize', handleResize );
  };
};
