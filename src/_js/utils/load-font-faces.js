/**
 * Load the `@font-face` data a font source carries (Font Library fonts) into a
 * document through the CSS Font Loading API.
 *
 * Faces the document already knows (for example the ones WordPress printed for
 * fonts active in Global Styles) are skipped, so nothing is fetched twice.
 */
const unquote = value => String( value || '' ).trim().replace( /^["']|["']$/g, '' );

const faceKey = ( family, weight, style ) => [
  unquote( family ).toLowerCase(),
  String( weight || '400' ).trim().replace( /\s+/g, ' ' ).toLowerCase(),
  String( style || 'normal' ).trim().toLowerCase(),
].join( '|' );

const knownFaceKeys = doc => {
  const keys = new Set();

  try {
    doc.fonts.forEach( face => keys.add( faceKey( face.family, face.weight, face.style ) ) );
  } catch ( e ) {}

  return keys;
};

const toSrc = src => ( Array.isArray( src ) ? src : [ src ] )
  .filter( Boolean )
  .map( url => `url(${ JSON.stringify( String( url ) ) })` )
  .join( ', ' );

/**
 * @param {Object}   fontDetails A font source entry with a `font_faces` list.
 * @param {Document} doc         The document to load the faces into.
 * @param {string[]} variants    Optional. Only load faces matching these SM variants (e.g. `400`, `700italic`).
 *
 * @return {number} How many faces were added.
 */
export const loadFontFaces = ( fontDetails, doc = globalThis.document, variants = [] ) => {
  const faces = fontDetails?.font_faces;
  const FontFaceCtor = doc?.defaultView?.FontFace || globalThis.FontFace;

  if ( ! Array.isArray( faces ) || ! faces.length || ! doc?.fonts || 'function' !== typeof FontFaceCtor ) {
    return 0;
  }

  const wanted = ( Array.isArray( variants ) ? variants : [ variants ] ).filter( Boolean ).map( String );
  const known = knownFaceKeys( doc );
  let added = 0;

  faces.forEach( face => {
    const weight = String( face.fontWeight || '400' );
    const style = String( face.fontStyle || 'normal' );
    const key = faceKey( face.fontFamily, weight, style );

    if ( known.has( key ) ) {
      return;
    }

    if ( wanted.length && ! wanted.some( variant => variantMatchesFace( variant, weight, style ) ) ) {
      return;
    }

    const src = toSrc( face.src );
    if ( ! src ) {
      return;
    }

    try {
      const descriptors = { weight, style, display: face.fontDisplay || 'fallback' };
      if ( face.unicodeRange ) {
        descriptors.unicodeRange = face.unicodeRange;
      }
      if ( face.fontStretch ) {
        descriptors.stretch = face.fontStretch;
      }

      const fontFace = new FontFaceCtor( unquote( face.fontFamily ), src, descriptors );
      doc.fonts.add( fontFace );
      known.add( key );
      added ++;

      if ( 'function' === typeof fontFace.load ) {
        fontFace.load().catch( () => {} );
      }
    } catch ( e ) {}
  } );

  return added;
};

/**
 * Whether an SM variant (`400`, `700italic`) is covered by a face weight
 * (`400` or a variable range `100 900`) and style.
 */
export const variantMatchesFace = ( variant, weight, style ) => {
  const match = String( variant ).toLowerCase().match( /^(\d{3})?\s*(italic|regular|normal)?$/ );
  if ( ! match ) {
    return true;
  }

  const variantWeight = match[ 1 ] ? Number( match[ 1 ] ) : 400;
  const variantItalic = 'italic' === match[ 2 ];
  const faceItalic = [ 'italic', 'oblique' ].includes( String( style ).toLowerCase() );

  if ( variantItalic !== faceItalic ) {
    return false;
  }

  const range = String( weight ).trim().split( /\s+/ ).map( Number );
  if ( 2 === range.length && range.every( Number.isFinite ) ) {
    return variantWeight >= range[ 0 ] && variantWeight <= range[ 1 ];
  }

  return variantWeight === ( Number( range[ 0 ] ) || 400 );
};
