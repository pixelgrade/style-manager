export const DEFAULT_LAB_STATE = {
  palette: '1',
  variation: 1,
  signal: 0,
  parentVariation: 1,
  dark: false,
  shifted: false,
  contextual: '',
};

const clamp = ( value, min, max ) => {
  const parsed = Number.parseInt( value, 10 );

  if ( Number.isNaN( parsed ) ) {
    return min;
  }

  return Math.min( Math.max( parsed, min ), max );
};

const normalizeBoolean = ( value ) => value === true || value === '1' || value === 'true' || value === 1;

const normalizeParentVariation = ( value ) => {
  const parsed = clamp( value, 1, 9 );
  const slots = [ 1, 5, 9 ];

  return slots.reduce( ( closest, slot ) => (
    Math.abs( slot - parsed ) < Math.abs( closest - parsed ) ? slot : closest
  ), 1 );
};

const normalizeHex = ( value ) => {
  if ( typeof value !== 'string' ) {
    return '';
  }

  const color = value.trim().toLowerCase();

  return /^#[0-9a-f]{6}$/.test( color ) ? color : '';
};

const normalizePalette = ( value ) => {
  if ( typeof value !== 'string' && typeof value !== 'number' ) {
    return DEFAULT_LAB_STATE.palette;
  }

  const palette = String( value ).trim();

  return /^[a-zA-Z0-9_-]+$/.test( palette ) ? palette : DEFAULT_LAB_STATE.palette;
};

export const normalizeLabState = ( input = {} ) => ( {
  palette: normalizePalette( input.palette ?? DEFAULT_LAB_STATE.palette ),
  variation: clamp( input.variation ?? DEFAULT_LAB_STATE.variation, 1, 12 ),
  signal: clamp( input.signal ?? DEFAULT_LAB_STATE.signal, 0, 3 ),
  parentVariation: normalizeParentVariation( input.parentVariation ?? DEFAULT_LAB_STATE.parentVariation ),
  dark: normalizeBoolean( input.dark ),
  shifted: normalizeBoolean( input.shifted ),
  contextual: normalizeHex( input.contextual ),
} );

export const parseLabState = ( search = '' ) => {
  const params = new URLSearchParams( search.startsWith( '?' ) ? search.slice( 1 ) : search );

  return normalizeLabState( Object.fromEntries( params.entries() ) );
};

export const serializeLabState = ( state ) => {
  const normalized = normalizeLabState( state );
  const params = new URLSearchParams();

  params.set( 'palette', normalized.palette );
  params.set( 'variation', String( normalized.variation ) );

  if ( normalized.dark ) {
    params.set( 'dark', '1' );
  }

  if ( normalized.shifted ) {
    params.set( 'shifted', '1' );
  }

  if ( normalized.contextual ) {
    params.set( 'contextual', normalized.contextual );
  }

  return params.toString();
};

export const buildShowcaseUrl = ( baseUrl, nonce, state ) => {
  const url = new URL( baseUrl );

  [
    'palette',
    'variation',
    'signal',
    'parentVariation',
    'dark',
    'shifted',
    'contextual',
    'sm_lab_showcase',
    '_wpnonce',
  ].forEach( ( key ) => url.searchParams.delete( key ) );

  url.searchParams.set( 'sm_lab_showcase', '1' );
  url.searchParams.set( '_wpnonce', nonce );

  new URLSearchParams( serializeLabState( state ) ).forEach( ( value, key ) => {
    url.searchParams.set( key, value );
  } );

  return url.toString();
};

export const buildAdminUrl = ( baseUrl, state ) => {
  const url = new URL( baseUrl );

  [
    'palette',
    'variation',
    'signal',
    'parentVariation',
    'dark',
    'shifted',
    'contextual',
  ].forEach( ( key ) => url.searchParams.delete( key ) );

  new URLSearchParams( serializeLabState( state ) ).forEach( ( value, key ) => {
    url.searchParams.set( key, value );
  } );

  return url.toString();
};

export const getSignalVariations = ( parentVariation ) => {
  const base = clamp( parentVariation, 1, 12 );

  return [ 0, 1, 2, 3 ].map( ( signal ) => ( {
    signal,
    variation: Math.min( base + signal, 12 ),
  } ) );
};
