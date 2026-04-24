import {
  applyShowcaseState,
  buildContextualPalette,
  buildContextualPaletteCss,
  buildRuntimePaletteCss,
  dispatchShowcaseState,
} from '../../src/_js/lab-showcase/runtime.js';

class FakeClassList {
  constructor( element ) {
    this.element = element;
    this.tokens = new Set();
  }

  setFromString( value ) {
    this.tokens = new Set( String( value ).split( /\s+/ ).filter( Boolean ) );
    this.sync();
  }

  add( ...tokens ) {
    tokens.forEach( ( token ) => this.tokens.add( token ) );
    this.sync();
  }

  remove( ...tokens ) {
    tokens.forEach( ( token ) => this.tokens.delete( token ) );
    this.sync();
  }

  contains( token ) {
    return this.tokens.has( token );
  }

  toggle( token, force ) {
    if ( typeof force === 'boolean' ) {
      if ( force ) {
        this.tokens.add( token );
      } else {
        this.tokens.delete( token );
      }
    } else if ( this.tokens.has( token ) ) {
      this.tokens.delete( token );
    } else {
      this.tokens.add( token );
    }

    this.sync();

    return this.tokens.has( token );
  }

  sync() {
    this.element._className = Array.from( this.tokens ).join( ' ' );
  }
}

class FakeElement {
  constructor( tagName, ownerDocument ) {
    this.tagName = tagName.toUpperCase();
    this.ownerDocument = ownerDocument;
    this.attributes = new Map();
    this.children = [];
    this.parentElement = null;
    this.textContent = '';
    this.classList = new FakeClassList( this );
    this._className = '';
  }

  get className() {
    return this._className;
  }

  set className( value ) {
    this.classList.setFromString( value );
  }

  appendChild( child ) {
    child.parentElement = this;
    this.children.push( child );
    this.ownerDocument.registerTree( child );
    return child;
  }

  setAttribute( name, value ) {
    const serializedValue = String( value );
    this.attributes.set( name, serializedValue );

    if ( name === 'class' ) {
      this.className = serializedValue;
    }

    if ( name === 'id' ) {
      this.ownerDocument.elementsById.set( serializedValue, this );
    }
  }

  getAttribute( name ) {
    return this.attributes.has( name ) ? this.attributes.get( name ) : null;
  }

  querySelector( selector ) {
    return this.ownerDocument.querySelectorFrom( selector, this );
  }

  querySelectorAll( selector ) {
    return this.ownerDocument.querySelectorAllFrom( selector, this );
  }
}

class FakeDocument {
  constructor() {
    this.elementsById = new Map();
    this.documentElement = this.createElement( 'html' );
    this.body = this.createElement( 'body' );
    this.documentElement.appendChild( this.body );
  }

  createElement( tagName ) {
    return new FakeElement( tagName, this );
  }

  registerTree( element ) {
    if ( element.getAttribute( 'id' ) ) {
      this.elementsById.set( element.getAttribute( 'id' ), element );
    }

    element.children.forEach( ( child ) => this.registerTree( child ) );
  }

  getElementById( id ) {
    return this.elementsById.get( id ) || null;
  }

  querySelector( selector ) {
    return this.querySelectorFrom( selector, this.documentElement );
  }

  querySelectorAll( selector ) {
    return this.querySelectorAllFrom( selector, this.documentElement );
  }

  querySelectorFrom( selector, root ) {
    return this.querySelectorAllFrom( selector, root )[ 0 ] || null;
  }

  querySelectorAllFrom( selector, root ) {
    const selectors = selector.trim().split( /\s+/ );
    let matches = [ root ];

    selectors.forEach( ( part ) => {
      const nextMatches = [];

      matches.forEach( ( match ) => {
        this.walk( match, ( candidate ) => {
          if ( candidate !== match && this.matchesSelector( candidate, part ) ) {
            nextMatches.push( candidate );
          }
        } );
      } );

      matches = nextMatches;
    } );

    return matches;
  }

  walk( element, callback ) {
    element.children.forEach( ( child ) => {
      callback( child );
      this.walk( child, callback );
    } );
  }

  matchesSelector( element, selector ) {
    if ( selector.startsWith( '#' ) ) {
      return element.getAttribute( 'id' ) === selector.slice( 1 );
    }

    if ( selector.startsWith( '.' ) ) {
      return element.classList.contains( selector.slice( 1 ) );
    }

    const dataAttrMatch = selector.match( /^\[([^=\]]+)=\"([^\"]+)\"\]$/ );
    if ( dataAttrMatch ) {
      return element.getAttribute( dataAttrMatch[1] ) === dataAttrMatch[2];
    }

    const dataPresenceMatch = selector.match( /^\[([^=\]]+)\]$/ );
    if ( dataPresenceMatch ) {
      return element.getAttribute( dataPresenceMatch[1] ) !== null;
    }

    const tagAndClassMatch = selector.match( /^([a-z0-9_-]+)\.([a-z0-9_-]+)$/i );
    if ( tagAndClassMatch ) {
      return element.tagName.toLowerCase() === tagAndClassMatch[1].toLowerCase() && element.classList.contains( tagAndClassMatch[2] );
    }

    return element.tagName.toLowerCase() === selector.toLowerCase();
  }
}

const createStatusValue = ( documentRef, key ) => {
  const value = documentRef.createElement( 'span' );
  value.setAttribute( 'data-sm-lab-status-value', key );
  return value;
};

const createSwatch = ( documentRef, token ) => {
  const swatch = documentRef.createElement( 'span' );
  swatch.setAttribute( 'data-token', token );
  const value = documentRef.createElement( 'span' );
  value.setAttribute( 'data-token-value', '1' );
  swatch.appendChild( value );
  return swatch;
};

const createRuntimePalette = () => ( {
  id: 'brand',
  sourceIndex: 6,
  variations: Array.from( { length: 12 }, ( _, index ) => ( {
    bg: `#${ String( index + 1 ).padStart( 6, '0' ) }`,
    accent: `#${ String( index + 101 ).padStart( 6, '0' ) }`,
    fg1: `#${ String( index + 201 ).padStart( 6, '0' ) }`,
    fg2: `#${ String( index + 301 ).padStart( 6, '0' ) }`,
  } ) ),
  darkVariations: Array.from( { length: 12 }, ( _, index ) => ( {
    bg: `#${ String( index + 401 ).padStart( 6, '0' ) }`,
    accent: `#${ String( index + 501 ).padStart( 6, '0' ) }`,
    fg1: `#${ String( index + 601 ).padStart( 6, '0' ) }`,
    fg2: `#${ String( index + 701 ).padStart( 6, '0' ) }`,
  } ) ),
} );

const createShowcaseDocument = () => {
  const documentRef = new FakeDocument();
  documentRef.body.className = 'sm-lab-showcase sm-palette-1 sm-variation-1';

  const contextualStyle = documentRef.createElement( 'style' );
  contextualStyle.setAttribute( 'id', 'style-manager-lab-contextual-palette' );
  documentRef.body.appendChild( contextualStyle );

  [ 'palette', 'variation', 'signal', 'contextual', 'dark' ].forEach( ( key ) => {
    documentRef.body.appendChild( createStatusValue( documentRef, key ) );
  } );

  [ 'bg', 'accent', 'fg1', 'fg2' ].forEach( ( token ) => {
    documentRef.body.appendChild( createSwatch( documentRef, token ) );
  } );

  const contextualZone = documentRef.createElement( 'section' );
  contextualZone.setAttribute( 'data-palette', 'contextual-lab' );
  contextualZone.className = 'sm-lab-contextual sm-palette-contextual-lab sm-variation-1';
  documentRef.body.appendChild( contextualZone );

  const nestedVariationZone = documentRef.createElement( 'section' );
  nestedVariationZone.setAttribute( 'data-palette-variation', '1' );
  nestedVariationZone.className = 'wp-block-group sm-palette-1 sm-variation-1';
  documentRef.body.appendChild( nestedVariationZone );

  return documentRef;
};

export const runLabShowcaseRuntimeTests = async ( assert ) => {
  {
    const palette = buildContextualPalette( '#ff5500' );

    assert.equal( palette.id, 'contextual-lab', 'contextual palettes should use the Lab palette id' );
    assert.equal( palette.variations[0].bg, '#fff1eb', 'contextual palette generation should stay aligned with the PHP helper' );
    assert.equal( palette.darkVariations[11].fg1, '#ffffff', 'dark contextual variations should preserve accessible foregrounds' );
  }

  {
    const css = buildContextualPaletteCss( buildContextualPalette( '#ff5500' ), 1 );

    assert.ok( css.includes( '.sm-palette-contextual-lab {' ), 'contextual CSS should target the Lab palette selector' );
    assert.ok( css.includes( '--sm-bg-color-1: #fff1eb;' ), 'contextual CSS should expose palette variables for the first variation' );
    assert.ok( css.includes( '.is-dark .sm-palette-contextual-lab {' ), 'contextual CSS should include the dark palette selector' );
  }

  {
    const css = buildRuntimePaletteCss( [ createRuntimePalette() ], 4 );

    assert.ok( css.includes( '.sm-palette-brand {' ), 'runtime palette CSS should target each palette selector' );
    assert.ok( css.includes( '--sm-bg-color-1: #000004;' ), 'runtime palette CSS should offset light variables by the selected variation' );
    assert.ok( css.includes( '.is-dark .sm-palette-brand {' ), 'runtime palette CSS should include dark variables' );
    assert.ok( css.includes( '--sm-bg-color-1: #000404;' ), 'runtime palette CSS should offset dark variables by the selected variation' );
    assert.ok( css.includes( '.sm-palette-brand.sm-palette--shifted {' ), 'runtime palette CSS should preserve shifted selectors' );
    assert.ok( css.includes( '--sm-bg-color-1: #000007;' ), 'shifted palette CSS should use the source index offset' );
  }

  {
    const documentRef = createShowcaseDocument();
    const computedValues = {
      '--sm-current-bg-color': '#101010',
      '--sm-current-accent-color': '#ff5500',
      '--sm-current-fg1-color': '#fafafa',
      '--sm-current-fg2-color': '#d7d7d7',
    };

    const result = applyShowcaseState( {
      document: documentRef,
      getComputedStyle: () => ( {
        getPropertyValue: ( property ) => computedValues[ property ] || '',
      } ),
      state: {
        palette: 'contextual-lab',
        variation: 4,
        signal: 2,
        parentVariation: 5,
        dark: true,
        shifted: true,
        contextual: '#ff5500',
      },
      siteVariation: 1,
      palettes: [ createRuntimePalette() ],
    } );

    assert.ok( documentRef.documentElement.classList.contains( 'is-dark' ), 'dark mode should be applied to the iframe document element' );
    assert.ok( documentRef.body.classList.contains( 'sm-palette-contextual-lab' ), 'the showcase body should switch palettes live' );
    assert.ok( documentRef.body.classList.contains( 'sm-variation-4' ), 'the showcase body should switch variations live' );
    assert.ok( documentRef.body.classList.contains( 'sm-palette--shifted' ), 'the shifted modifier should update live' );
    assert.equal(
      documentRef.querySelector( '[data-sm-lab-status-value="variation"]' )?.textContent,
      '4',
      'status labels should reflect the live variation'
    );
    assert.equal(
      documentRef.querySelector( '[data-token="accent"] [data-token-value]' )?.textContent,
      '#ff5500',
      'swatch readbacks should refresh from the live computed colors'
    );
    assert.ok(
      documentRef.querySelector( '#style-manager-lab-contextual-palette' )?.textContent.includes( '--sm-bg-color-1: #ffbb99;' ),
      'the contextual palette style element should refresh with the live variation offset'
    );
    assert.ok(
      documentRef.querySelector( '#style-manager-lab-runtime-palettes' )?.textContent.includes( '--sm-bg-color-1: #000004;' ),
      'the runtime palette style element should refresh with the live variation offset'
    );
    assert.equal(
      documentRef.querySelector( '[data-palette-variation]' )?.getAttribute( 'data-palette-variation' ),
      '4',
      'nested palette variation scopes should track the live variation'
    );
    assert.ok(
      documentRef.querySelector( '[data-palette-variation]' )?.classList.contains( 'sm-variation-4' ),
      'nested palette variation scopes should update their variation class'
    );
    assert.equal( result.colors.bg, '#101010', 'applyShowcaseState should return the live readback payload' );
    assert.equal( result.contextualPalette?.source?.[0], '#ff5500', 'applyShowcaseState should return the synthesized contextual palette payload' );

    let receivedEvent = null;
    dispatchShowcaseState( {
      CustomEvent: class {
        constructor( type, init ) {
          this.type = type;
          this.detail = init.detail;
        }
      },
      dispatchEvent: ( event ) => {
        receivedEvent = event;
      },
    }, result );

    assert.equal( receivedEvent.type, 'style-manager-lab:showcase-state', 'runtime should dispatch a state event for showcase adapters' );
    assert.equal( receivedEvent.detail.state.variation, 4, 'runtime state events should include normalized variation' );
    assert.equal( receivedEvent.detail.state.dark, true, 'runtime state events should include normalized dark mode' );
  }
};
