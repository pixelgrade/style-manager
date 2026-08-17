import { getRelativeFontSizeInterval, getTransitionFontSizeInterval, remapFontSize } from './relative-font-size-interval.js';
import { getNumericFontSize } from './font-size-value.js';

const NORMAL_FONT_SIZE_STATE = Object.freeze( { elevation: 0, pitch: 100 } );
export const FONT_SIZE_BASELINE_VERSION = 1;

const isInterval = interval => Array.isArray( interval ) &&
  interval.length === 2 &&
  interval.every( value => Number.isFinite( Number( value ) ) );

const normalizeSizes = sizes => Object.entries( sizes || {} ).reduce( ( normalized, [ key, value ] ) => {
  const numericValue = getNumericFontSize( value );

  if ( numericValue !== null ) {
    normalized[ key ] = numericValue;
  }

  return normalized;
}, {} );

export const isFontSizeBaselineEntry = entry => !! entry &&
  isInterval( entry.interval ) &&
  !! entry.sizes &&
  typeof entry.sizes === 'object' &&
  ! Array.isArray( entry.sizes );

export const normalizeFontSizeBaselineDocument = value => {
  let candidate = value;

  if ( typeof candidate === 'string' ) {
    try {
      candidate = JSON.parse( candidate );
    } catch ( error ) {
      candidate = {};
    }
  }

  if ( ! candidate || typeof candidate !== 'object' || Array.isArray( candidate ) ) {
    candidate = {};
  }

  return {
    version: FONT_SIZE_BASELINE_VERSION,
    scales: candidate.scales && typeof candidate.scales === 'object' && ! Array.isArray( candidate.scales )
      ? { ...candidate.scales }
      : {},
  };
};

export const createFontSizeBaselineEntry = ( {
  currentInterval,
  currentSizes,
  currentState,
  fallbackInterval = false,
  fallbackSizes = {},
  precision = 2,
} ) => {
  const normalizedCurrentSizes = normalizeSizes( currentSizes );
  const normalizedFallbackSizes = normalizeSizes( fallbackSizes );
  const sourceInterval = isInterval( currentInterval )
    ? currentInterval.map( Number )
    : ( isInterval( fallbackInterval ) ? fallbackInterval.map( Number ) : false );

  if ( ! sourceInterval ) {
    return { interval: false, sizes: {} };
  }

  const neutralInterval = getTransitionFontSizeInterval(
    sourceInterval,
    currentState,
    NORMAL_FONT_SIZE_STATE
  );

  if ( ! isInterval( neutralInterval ) ) {
    return {
      interval: isInterval( fallbackInterval ) ? fallbackInterval.map( Number ) : sourceInterval,
      sizes: Object.keys( normalizedFallbackSizes ).length > 0 ? normalizedFallbackSizes : normalizedCurrentSizes,
    };
  }

  const neutralSizes = Object.entries( normalizedCurrentSizes ).reduce( ( sizes, [ key, value ] ) => {
    const neutralValue = remapFontSize( value, sourceInterval, neutralInterval, precision );

    if ( neutralValue !== null ) {
      sizes[ key ] = neutralValue;
    }

    return sizes;
  }, {} );

  return {
    interval: neutralInterval,
    sizes: neutralSizes,
  };
};

export const deriveAbsoluteFontSizes = ( entry, nextState, precision = 2 ) => {
  if ( ! isFontSizeBaselineEntry( entry ) ) {
    return { interval: false, sizes: {} };
  }

  const targetInterval = getRelativeFontSizeInterval(
    entry.interval,
    Number( nextState?.elevation ),
    Number( nextState?.pitch )
  );
  const sizes = Object.entries( normalizeSizes( entry.sizes ) ).reduce( ( derived, [ key, value ] ) => {
    const derivedValue = remapFontSize( value, entry.interval, targetInterval, precision );

    if ( derivedValue !== null ) {
      derived[ key ] = derivedValue;
    }

    return derived;
  }, {} );

  return { interval: targetInterval, sizes };
};

export const reconcileFontSizeBaselineEntry = ( entry, currentSizes, currentState, precision = 2, currentFieldIds = Object.keys( currentSizes || {} ) ) => {
  if ( ! isFontSizeBaselineEntry( entry ) ) {
    return { entry, changed: false };
  }

  const currentTarget = deriveAbsoluteFontSizes( entry, currentState, precision );
  const canInvert = Number( currentState?.pitch ) !== 0 && isInterval( currentTarget.interval );
  const nextEntry = {
    interval: entry.interval.slice(),
    sizes: { ...entry.sizes },
  };
  const normalizedCurrentSizes = normalizeSizes( currentSizes );
  let changed = false;

  const currentFieldSet = new Set( currentFieldIds );
  Object.keys( nextEntry.sizes ).forEach( key => {
    if ( currentFieldSet.has( key ) && ! Object.prototype.hasOwnProperty.call( normalizedCurrentSizes, key ) ) {
      delete nextEntry.sizes[ key ];
      changed = true;
    }
  } );

  Object.entries( normalizedCurrentSizes ).forEach( ( [ key, currentValue ] ) => {
    if ( currentTarget.sizes[ key ] === currentValue ) {
      return;
    }

    if ( canInvert ) {
      const neutralValue = remapFontSize(
        currentValue,
        currentTarget.interval,
        entry.interval,
        precision
      );

      if ( neutralValue !== null ) {
        nextEntry.sizes[ key ] = neutralValue;
        changed = true;
      }
    }
  } );

  return { entry: nextEntry, changed };
};
