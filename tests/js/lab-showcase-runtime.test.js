import {
  applyShowcaseState,
  buildContextualPalette,
  buildContextualPaletteCss,
  buildContextualContrastReadout,
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
    this.listeners = new Map();
    this.rect = { left: 0, top: 0, width: 0, height: 0, right: 0, bottom: 0 };
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

  removeAttribute( name ) {
    this.attributes.delete( name );
  }

  getBoundingClientRect() {
    return this.rect;
  }

  setRect( rect ) {
    this.rect = {
      left: rect.left,
      top: rect.top,
      width: rect.width,
      height: rect.height,
      right: rect.left + rect.width,
      bottom: rect.top + rect.height,
    };
  }

  addEventListener( type, listener ) {
    const listeners = this.listeners.get( type ) || [];
    listeners.push( listener );
    this.listeners.set( type, listeners );
  }

  querySelector( selector ) {
    return this.ownerDocument.querySelectorFrom( selector, this );
  }

  querySelectorAll( selector ) {
    return this.ownerDocument.querySelectorAllFrom( selector, this );
  }

  closest( selector ) {
    let cursor = this;

    while ( cursor ) {
      if ( this.ownerDocument.matchesSelector( cursor, selector ) ) {
        return cursor;
      }

      cursor = cursor.parentElement;
    }

    return null;
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

const createContextualReadout = ( documentRef, key ) => {
  const value = documentRef.createElement( 'span' );
  value.setAttribute( 'data-sm-lab-contextual-value', key );
  return value;
};

const createCascadeValue = ( documentRef, key ) => {
  const value = documentRef.createElement( 'span' );
  value.setAttribute( 'data-sm-lab-cascade-value', key );
  return value;
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

  documentRef.body.appendChild( createStatusValue( documentRef, 'variation' ) );

  const gradeRail = documentRef.createElement( 'div' );
  gradeRail.setAttribute( 'data-sm-lab-grade-rail', '1' );
  documentRef.body.appendChild( gradeRail );

  for ( let grade = 1; grade <= 12; grade += 1 ) {
    const chip = documentRef.createElement( 'span' );
    chip.setAttribute( 'data-sm-lab-grade-swatch', String( grade ) );
    gradeRail.appendChild( chip );
  }

  for ( let signal = 0; signal <= 3; signal += 1 ) {
    const option = documentRef.createElement( 'span' );
    option.setAttribute( 'data-sm-lab-signal-option', String( signal ) );
    documentRef.body.appendChild( option );
  }

  [ 'signal', 'parent', 'variation' ].forEach( ( key ) => {
    const value = documentRef.createElement( 'span' );
    value.setAttribute( 'data-sm-lab-signal-result', key );
    documentRef.body.appendChild( value );
  } );

  [ 'bg', 'accent', 'fg1', 'fg2' ].forEach( ( token ) => {
    documentRef.body.appendChild( createSwatch( documentRef, token ) );
  } );

  documentRef.body.appendChild( createSwatch( documentRef, 'accent' ) );

  [
    'source',
    'surface',
    'accent',
    'text',
    'accent-ratio',
    'accent-status',
    'text-ratio',
    'text-status',
  ].forEach( ( key ) => {
    documentRef.body.appendChild( createContextualReadout( documentRef, key ) );
  } );

  const contextualZone = documentRef.createElement( 'section' );
  contextualZone.setAttribute( 'data-palette', 'contextual-lab' );
  contextualZone.setAttribute( 'data-palette-variation', '1' );
  contextualZone.className = 'sm-lab-contextual sm-palette-contextual-lab sm-variation-1';
  documentRef.body.appendChild( contextualZone );

  const nestedVariationZone = documentRef.createElement( 'section' );
  nestedVariationZone.setAttribute( 'data-palette', '1' );
  nestedVariationZone.setAttribute( 'data-palette-variation', '1' );
  nestedVariationZone.setAttribute( 'data-color-signal', '2' );
  nestedVariationZone.className = 'wp-block-group sm-palette-1 sm-variation-1 sm-color-signal-2';
  documentRef.body.appendChild( nestedVariationZone );

  const signalCascade = documentRef.createElement( 'div' );
  signalCascade.setAttribute( 'data-sm-lab-signal-cascade', '1' );
  documentRef.body.appendChild( signalCascade );

  [ 'page', 'header', 'content', 'inner', 'second-inner', 'footer' ].forEach( ( id ) => {
    const previewNode = documentRef.createElement( 'div' );
    previewNode.setAttribute( 'data-sm-lab-cascade-preview-node', id );
    signalCascade.appendChild( previewNode );
  } );

  [
    [ 'page', 0, '' ],
    [ 'header', 3, 'page' ],
    [ 'content', 1, 'page' ],
    [ 'inner', 2, 'content' ],
    [ 'second-inner', 3, 'inner' ],
    [ 'footer', 3, 'page' ],
  ].forEach( ( [ id, signal, parent ] ) => {
    const node = documentRef.createElement( 'section' );
    node.setAttribute( 'data-sm-lab-cascade-node', id );
    node.setAttribute( 'data-sm-lab-cascade-signal', String( signal ) );

    if ( parent ) {
      node.setAttribute( 'data-sm-lab-cascade-parent', parent );
    }

    [ 'signal', 'parent', 'resolved' ].forEach( ( key ) => {
      node.appendChild( createCascadeValue( documentRef, key ) );
    } );

    const scopeChip = documentRef.createElement( 'code' );
    scopeChip.setAttribute( 'data-sm-lab-cascade-chip-scope', '1' );
    node.appendChild( scopeChip );

    const rail = documentRef.createElement( 'div' );
    rail.setAttribute( 'data-sm-lab-cascade-rail', '1' );
    node.appendChild( rail );

    for ( let grade = 1; grade <= 12; grade += 1 ) {
      const segment = documentRef.createElement( 'span' );
      segment.setAttribute( 'data-sm-lab-cascade-rail-grade', String( grade ) );
      rail.appendChild( segment );
    }

    if ( id === 'second-inner' ) {
      const inversion = documentRef.createElement( 'p' );
      inversion.setAttribute( 'data-sm-lab-cascade-inversion', 'second-inner' );
      node.appendChild( inversion );
    }

    signalCascade.appendChild( node );
  } );

  const signalPreview = documentRef.createElement( 'div' );
  signalPreview.setAttribute( 'data-sm-lab-signal-preview', '1' );
  signalPreview.setAttribute( 'data-sm-lab-button-token-map', '1' );
  signalPreview.setAttribute( 'data-sm-lab-signal-preview-surface', 'stable' );
  signalPreview.setAttribute( 'data-palette', '1' );
  signalPreview.setAttribute( 'data-palette-variation', '1' );
  signalPreview.setAttribute( 'data-color-signal', '0' );
  signalPreview.className = 'sm-palette-1 sm-variation-1 sm-color-signal-0';
  signalPreview.setRect( { left: 0, top: 0, width: 1000, height: 420 } );
  documentRef.body.appendChild( signalPreview );

  const sourceWires = documentRef.createElement( 'svg' );
  sourceWires.setAttribute( 'data-sm-lab-token-source-wires', '1' );
  signalPreview.appendChild( sourceWires );

  [ 'label', 'button', 'border' ].forEach( ( part ) => {
    const path = documentRef.createElement( 'path' );
    path.setAttribute( 'data-sm-lab-token-source-wire', part );
    sourceWires.appendChild( path );
  } );

  const componentRail = documentRef.createElement( 'div' );
  signalPreview.appendChild( componentRail );

  for ( let grade = 1; grade <= 12; grade += 1 ) {
    const chip = documentRef.createElement( 'span' );
    chip.setAttribute( 'data-sm-lab-grade-swatch', String( grade ) );
    chip.setRect( { left: 80 + ( grade - 1 ) * 48, top: 20, width: 36, height: 36 } );
    componentRail.appendChild( chip );

    const sourceGroup = documentRef.createElement( 'span' );
    chip.appendChild( sourceGroup );

    [
      [ 'label', 0 ],
      [ 'button', 1 ],
      [ 'border', 2 ],
    ].forEach( ( [ part, index ] ) => {
      const source = documentRef.createElement( 'span' );
      source.setAttribute( 'data-sm-lab-token-source-chip', part );
      source.setRect( { left: 88 + ( grade - 1 ) * 48 + index * 9, top: 50, width: 8, height: 8 } );
      sourceGroup.appendChild( source );
    } );
  }

  const labelTarget = documentRef.createElement( 'span' );
  labelTarget.setAttribute( 'data-sm-lab-token-target', 'label' );
  labelTarget.setRect( { left: 500, top: 278, width: 10, height: 10 } );
  signalPreview.appendChild( labelTarget );

  const actionTarget = documentRef.createElement( 'button' );
  actionTarget.setAttribute( 'data-sm-lab-token-target', 'action' );
  actionTarget.setRect( { left: 720, top: 250, width: 10, height: 10 } );
  signalPreview.appendChild( actionTarget );

  const borderTarget = documentRef.createElement( 'span' );
  borderTarget.setAttribute( 'data-sm-lab-token-target', 'border' );
  borderTarget.setRect( { left: 800, top: 329, width: 10, height: 10 } );
  signalPreview.appendChild( borderTarget );

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
    const readout = buildContextualContrastReadout( buildContextualPalette( '#ff5500' ), 4 );

    assert.equal( readout.source, '#ff5500', 'contextual readouts should preserve the source color' );
    assert.equal( readout.surface, '#ff5500', 'contextual readouts should follow the effective scoped variation surface' );
    assert.equal( readout.accent, '#111111', 'contextual readouts should expose the effective scoped accent role' );
    assert.equal( readout.text, '#111111', 'contextual readouts should expose the text role' );
    assert.equal( readout.textStatus, 'AA', 'contextual readouts should classify text contrast' );
    assert.match( readout.accentRatio, /^\d+\.\d{2}:1$/, 'contextual readouts should format accent contrast ratios' );
  }

  {
    const css = buildContextualPaletteCss( buildContextualPalette( '#ff5500' ), 1 );

    assert.ok( css.includes( '.sm-palette-contextual-lab {' ), 'contextual CSS should target the Lab palette selector' );
    assert.ok( css.includes( '--sm-bg-color-1: #fff1eb;' ), 'contextual CSS should expose palette variables for the first variation' );
    assert.ok( css.includes( '--sm-lab-reference-bg-color-1: #fff1eb;' ), 'contextual CSS should expose a fixed reference rail for grade 1' );
    assert.ok( css.includes( '--sm-lab-reference-bg-color-12: #2e0f00;' ), 'contextual CSS should expose a fixed reference rail for grade 12' );
    assert.ok( css.includes( '.is-dark .sm-palette-contextual-lab {' ), 'contextual CSS should include the dark palette selector' );
  }

  {
    const css = buildRuntimePaletteCss( [ createRuntimePalette() ], 4 );

    assert.ok( css.includes( '.sm-palette-brand {' ), 'runtime palette CSS should target each palette selector' );
    assert.ok( css.includes( '--sm-bg-color-1: #000004;' ), 'runtime palette CSS should offset light variables by the selected variation' );
    assert.ok( css.includes( '--sm-lab-reference-bg-color-1: #000001;' ), 'runtime palette CSS should keep grade 1 fixed for the anatomy rail' );
    assert.ok( css.includes( '--sm-lab-reference-accent-color-1: #000101;' ), 'runtime palette CSS should expose fixed action-role colors for anatomy sources' );
    assert.ok( css.includes( '--sm-lab-reference-fg2-color-8: #000308;' ), 'runtime palette CSS should expose fixed depth-role colors for anatomy sources' );
    assert.ok( css.includes( '--sm-lab-reference-bg-color-12: #000012;' ), 'runtime palette CSS should keep grade 12 fixed for the anatomy rail' );
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

    const animationFrames = [];
    const windowRef = {
      novablocks: {
        utils: {
          getSignals: () => [ 2, 5, 8, 11 ],
        },
      },
      requestAnimationFrame: ( callback ) => {
        animationFrames.push( callback );

        return animationFrames.length;
      },
      setTimeout: () => 1,
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
      windowRef,
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
    documentRef.querySelectorAll( '[data-sm-lab-status-value="variation"]' ).forEach( ( node ) => {
      assert.equal(
        node.textContent,
        '4',
        'every matching status label should reflect the live variation'
      );
    } );
    assert.equal(
      documentRef.querySelector( '[data-token="accent"] [data-token-value]' )?.textContent,
      '#ff5500',
      'swatch readbacks should refresh from the live computed colors'
    );
    documentRef.querySelectorAll( '[data-token="accent"] [data-token-value]' ).forEach( ( node ) => {
      assert.equal(
        node.textContent,
        '#ff5500',
        'every matching swatch readback should refresh from the live computed colors'
      );
    } );
    assert.ok(
      documentRef.querySelector( '#style-manager-lab-contextual-palette' )?.textContent.includes( '--sm-bg-color-1: #ffbb99;' ),
      'the contextual palette style element should refresh with the live variation offset'
    );
    assert.ok(
      documentRef.querySelector( '#style-manager-lab-runtime-palettes' )?.textContent.includes( '--sm-bg-color-1: #000004;' ),
      'the runtime palette style element should refresh with the live variation offset'
    );
    assert.ok(
      documentRef.querySelector( '#style-manager-lab-runtime-palettes' )?.textContent.includes( '--sm-lab-reference-bg-color-1: #000001;' ),
      'the runtime palette style element should keep the anatomy rail reference scale unshifted'
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
    assert.equal(
      documentRef.querySelector( '[data-sm-lab-grade-rail]' )?.getAttribute( 'data-sm-lab-parent-grade' ),
      '4',
      'grade rail should expose the active runtime variation as the parent context grade'
    );
    assert.equal(
      documentRef.querySelector( '[data-sm-lab-grade-rail]' )?.getAttribute( 'data-sm-lab-resolved-grade' ),
      '8',
      'grade rail should expose the Nova preset signal-resolved grade'
    );
    assert.equal(
      documentRef.querySelector( '[data-sm-lab-grade-rail]' )?.getAttribute( 'data-sm-lab-signal-shifted' ),
      'true',
      'grade rail should expose whether signal shifted the parent context'
    );
    assert.equal(
      documentRef.querySelector( '[data-sm-lab-grade-swatch="5"]' )?.getAttribute( 'data-parent-active' ),
      'false',
      'legacy Color Signal parent controls should not move the active runtime context marker'
    );
    assert.equal(
      documentRef.querySelector( '[data-sm-lab-grade-swatch="4"]' )?.getAttribute( 'data-parent-active' ),
      'true',
      'parent grade marker should follow the active runtime variation'
    );
    assert.equal(
      documentRef.querySelector( '[data-sm-lab-grade-swatch="8"]' )?.getAttribute( 'data-signal-active' ),
      'true',
      'signal grade marker should follow the Nova preset child variation'
    );
    assert.equal(
      documentRef.querySelector( '[data-sm-lab-grade-swatch="8"]' )?.getAttribute( 'data-resolved-active' ),
      'true',
      'resolved grade marker should follow the Nova preset child variation'
    );
    assert.equal(
      documentRef.querySelector( '[data-sm-lab-signal-option="2"]' )?.getAttribute( 'data-active' ),
      'true',
      'active signal marker should follow selected color signal'
    );
    assert.equal(
      documentRef.querySelector( '[data-sm-lab-signal-option="0"]' )?.getAttribute( 'data-active' ),
      'false',
      'inactive signal markers should be cleared when signal changes'
    );
    assert.equal(
      documentRef.querySelector( '[data-sm-lab-signal-result="signal"]' )?.textContent,
      '2',
      'signal result should expose the selected signal level'
    );
    assert.equal(
      documentRef.querySelector( '[data-sm-lab-signal-result="parent"]' )?.textContent,
      '4',
      'signal result should expose the inherited active runtime variation'
    );
    assert.equal(
      documentRef.querySelector( '[data-sm-lab-signal-result="variation"]' )?.textContent,
      '8',
      'signal result should expose the Nova preset resolved child variation'
    );
    assert.equal(
      documentRef.querySelector( '[data-sm-lab-cascade-node="page"]' )?.getAttribute( 'data-sm-lab-cascade-resolved-grade' ),
      '4',
      'signal cascade should keep the page node on the inherited runtime grade'
    );
    assert.equal(
      documentRef.querySelector( '[data-sm-lab-cascade-node="content"]' )?.getAttribute( 'data-sm-lab-cascade-parent-grade' ),
      '4',
      'signal cascade should resolve content blocks against the page grade'
    );
    assert.equal(
      documentRef.querySelector( '[data-sm-lab-cascade-node="content"]' )?.getAttribute( 'data-sm-lab-cascade-resolved-grade' ),
      '2',
      'low signal should move content to the nearest Nova preset relative to its parent'
    );
    assert.equal(
      documentRef.querySelector( '[data-sm-lab-cascade-node="content"] [data-sm-lab-cascade-value="signal"]' )?.textContent,
      'Low',
      'signal cascade should label Low signal nodes'
    );
    assert.equal(
      documentRef.querySelector( '[data-sm-lab-cascade-node="inner"]' )?.getAttribute( 'data-sm-lab-cascade-parent-grade' ),
      '2',
      'nested signal nodes should use their parent resolved grade as reference'
    );
    assert.equal(
      documentRef.querySelector( '[data-sm-lab-cascade-node="inner"]' )?.getAttribute( 'data-sm-lab-cascade-resolved-grade' ),
      '8',
      'medium signal should resolve the inner block from the content grade'
    );
    assert.equal(
      documentRef.querySelector( '[data-sm-lab-cascade-node="inner"]' )?.getAttribute( 'data-sm-lab-cascade-active' ),
      'true',
      'cascade nodes matching the selected signal should be marked active'
    );
    assert.ok(
      documentRef.querySelector( '[data-sm-lab-cascade-node="inner"]' )?.getAttribute( 'style' )?.includes( '--sm-lab-cascade-surface-color: var(--sm-lab-reference-bg-color-8' ),
      'cascade nodes should consume the fixed reference grade rail for their resolved surface'
    );
    assert.equal(
      documentRef.querySelector( '[data-sm-lab-cascade-node="inner"] [data-sm-lab-cascade-rail-grade="2"]' )?.getAttribute( 'data-sm-lab-cascade-rail-marker' ),
      'parent',
      'inner rail should mark grade 2 as parent'
    );
    assert.equal(
      documentRef.querySelector( '[data-sm-lab-cascade-node="inner"] [data-sm-lab-cascade-rail-grade="8"]' )?.getAttribute( 'data-sm-lab-cascade-rail-marker' ),
      'resolved',
      'inner rail should mark grade 8 as resolved'
    );
    assert.equal(
      documentRef.querySelector( '[data-sm-lab-cascade-node="inner"] [data-sm-lab-cascade-chip-scope]' )?.textContent,
      '.sm-palette-contextual-lab.sm-variation-8',
      'inner scope chip should reflect palette and resolved grade'
    );
    assert.ok(
      documentRef.querySelector( '[data-sm-lab-cascade-preview-node="content"]' )?.getAttribute( 'style' )?.includes( '--sm-lab-cascade-surface-color: var(--sm-lab-reference-bg-color-2' ),
      'assembled preview content node should consume its resolved grade'
    );
    assert.equal(
      documentRef.querySelector( '[data-sm-lab-cascade-node="second-inner"]' )?.getAttribute( 'data-sm-lab-cascade-parent-grade' ),
      '8',
      'second-level nested signal nodes should inherit the already resolved inner grade'
    );
    assert.equal(
      documentRef.querySelector( '[data-sm-lab-cascade-node="second-inner"]' )?.getAttribute( 'data-sm-lab-cascade-resolved-grade' ),
      '2',
      'high signal should resolve back to a light grade when the parent context is dark'
    );
    assert.equal(
      documentRef.querySelector( '[data-sm-lab-cascade-inversion="second-inner"]' )?.getAttribute( 'data-sm-lab-cascade-inversion-visible' ),
      'true',
      'inversion callout should be marked visible when resolved differs from parent'
    );
    const cascade = documentRef.querySelector( '[data-sm-lab-signal-cascade]' );
    const contentCascadeNode = documentRef.querySelector( '[data-sm-lab-cascade-node="content"]' );
    cascade.listeners.get( 'pointerover' )?.[0]?.( { target: contentCascadeNode } );
    assert.equal(
      cascade.getAttribute( 'data-sm-lab-cascade-highlight' ),
      'content',
      'hovering an inspector node should highlight the matching assembled preview node'
    );
    cascade.listeners.get( 'pointerleave' )?.[0]?.();
    assert.equal(
      cascade.getAttribute( 'data-sm-lab-cascade-highlight' ),
      null,
      'leaving the inspector should clear the cascade preview highlight'
    );
    cascade.listeners.get( 'focusin' )?.[0]?.( { target: documentRef.querySelector( '[data-sm-lab-cascade-node="inner"]' ) } );
    assert.equal(
      cascade.getAttribute( 'data-sm-lab-cascade-highlight' ),
      'inner',
      'focusing an inspector node should highlight the matching assembled preview node'
    );
    cascade.listeners.get( 'focusout' )?.[0]?.();
    assert.equal(
      cascade.getAttribute( 'data-sm-lab-cascade-highlight' ),
      null,
      'moving focus away from the inspector should clear the cascade preview highlight'
    );
    assert.equal(
      cascade.listeners.get( 'pointerover' )?.length,
      1,
      'cascade hover bond should bind pointer listeners once'
    );
    assert.equal(
      documentRef.querySelector( '[data-sm-lab-signal-preview]' )?.getAttribute( 'data-palette' ),
      'contextual-lab',
      'signal preview scope should follow the selected palette'
    );
    assert.equal(
      documentRef.querySelector( '[data-sm-lab-signal-preview]' )?.getAttribute( 'data-palette-variation' ),
      '4',
      'component anatomy scope should keep the inherited variation so its canvas background stays stable'
    );
    assert.equal(
      documentRef.querySelector( '[data-sm-lab-signal-preview]' )?.getAttribute( 'data-color-signal' ),
      '2',
      'signal preview scope should expose the selected signal level'
    );
    assert.ok(
      documentRef.querySelector( '[data-sm-lab-signal-preview]' )?.classList.contains( 'sm-palette-contextual-lab' ),
      'signal preview scope should update its palette class'
    );
    assert.ok(
      documentRef.querySelector( '[data-sm-lab-signal-preview]' )?.classList.contains( 'sm-variation-4' ),
      'component anatomy scope should keep the inherited variation class'
    );
    assert.equal(
      documentRef.querySelector( '[data-sm-lab-signal-preview]' )?.classList.contains( 'sm-color-signal-2' ),
      false,
      'component anatomy scope should not apply a signal class to its own canvas'
    );
    assert.equal(
      documentRef.querySelector( '[data-sm-lab-button-token-map]' )?.getAttribute( 'data-sm-lab-token-source-grade-label' ),
      '1',
      'label source pointer should expose the resolved grade it points to'
    );
    assert.equal(
      documentRef.querySelector( '[data-sm-lab-button-token-map]' )?.getAttribute( 'data-sm-lab-token-source-grade-button' ),
      '8',
      'button source pointer should expose the Nova preset grade it points to'
    );
    assert.equal(
      documentRef.querySelector( '[data-sm-lab-button-token-map]' )?.getAttribute( 'data-sm-lab-token-source-grade-border' ),
      '10',
      'border source pointer should expose the resolved grade it points to'
    );
    assert.ok(
      documentRef.querySelector( '[data-sm-lab-button-token-map]' )?.getAttribute( 'style' )?.includes( '--sm-lab-component-button-color: var(--sm-lab-reference-bg-color-8' ),
      'button sample should consume the same fixed grade color that the connector points to'
    );
    assert.equal(
      documentRef.querySelector( '[data-sm-lab-token-source-chip="label"]' )?.getAttribute( 'data-active' ),
      'true',
      'label wire should originate from the active role-color source chip'
    );
    assert.equal(
      documentRef.querySelector( '[data-sm-lab-token-source-wire="label"]' )?.getAttribute( 'data-sm-lab-token-source-grade' ),
      '1',
      'label wire should terminate at the resolved label source grade'
    );
    assert.equal(
      documentRef.querySelector( '[data-sm-lab-token-source-wire="button"]' )?.getAttribute( 'data-sm-lab-token-source-grade' ),
      '8',
      'button fill wire should terminate at the resolved button source grade'
    );
    assert.equal(
      documentRef.querySelector( '[data-sm-lab-token-source-wire="border"]' )?.getAttribute( 'data-sm-lab-token-source-grade' ),
      '10',
      'border wire should terminate at the resolved border source grade'
    );
    assert.equal(
      documentRef.querySelector( '[data-sm-lab-token-source-wire="label"]' )?.getAttribute( 'data-sm-lab-token-entry-side' ),
      'left',
      'label wire should expose the side it uses to enter the component'
    );
    assert.equal(
      documentRef.querySelector( '[data-sm-lab-token-source-wire="button"]' )?.getAttribute( 'data-sm-lab-token-entry-side' ),
      'top',
      'button fill wire should expose the side it uses to enter the component'
    );
    assert.equal(
      documentRef.querySelector( '[data-sm-lab-token-source-wire="border"]' )?.getAttribute( 'data-sm-lab-token-entry-side' ),
      'bottom',
      'border wire should expose the side it uses to enter the component'
    );
    assert.match(
      documentRef.querySelector( '[data-sm-lab-token-source-wire="label"]' )?.getAttribute( 'd' ) || '',
      /^M 92 58 L 92 80 Q 92 90 102 90 L 466 90 Q 476 90 476 100 L 476 273 Q 476 283 486 283 L 500 283$/,
      'label wire should enter the button label through its left-side port'
    );
    assert.match(
      documentRef.querySelector( '[data-sm-lab-token-source-wire="button"]' )?.getAttribute( 'd' ) || '',
      /^M 437 58 L 437 104 Q 437 114 447 114 L 715 114 Q 725 114 725 124 L 725 250$/,
      'button fill wire should enter the fill surface through its top-side port'
    );
    assert.match(
      documentRef.querySelector( '[data-sm-lab-token-source-wire="border"]' )?.getAttribute( 'd' ) || '',
      /^M 542 58 L 542 361 Q 542 371 552 371 L 795 371 Q 805 371 805 361 L 805 339$/,
      'border wire should enter the button border from below'
    );
    assert.notEqual(
      documentRef.querySelector( '[data-sm-lab-token-source-wire="label"]' )?.getAttribute( 'd' ),
      documentRef.querySelector( '[data-sm-lab-token-source-wire="button"]' )?.getAttribute( 'd' ),
      'role source wires should not reuse the same geometry'
    );
    assert.equal(
      documentRef.querySelector( '[data-sm-lab-grade-swatch="1"]' )?.getAttribute( 'data-token-label-active' ),
      'true',
      'label token source should mark the contrast grade on the rail'
    );
    assert.equal(
      documentRef.querySelector( '[data-sm-lab-grade-swatch="8"]' )?.getAttribute( 'data-token-button-active' ),
      'true',
      'button token source should mark the Nova preset grade on the rail'
    );
    assert.equal(
      documentRef.querySelector( '[data-sm-lab-grade-swatch="10"]' )?.getAttribute( 'data-token-border-active' ),
      'true',
      'border token source should mark the deeper support grade on the rail'
    );
    assert.equal(
      documentRef.querySelector( '[data-sm-lab-grade-swatch="4"]' )?.getAttribute( 'data-token-button-active' ),
      'false',
      'parent runtime variation should not be marked as the button token source when signal shifts it'
    );
    assert.equal( result.colors.bg, '#101010', 'applyShowcaseState should return the live readback payload' );
    assert.equal( result.contextualPalette?.source?.[0], '#ff5500', 'applyShowcaseState should return the synthesized contextual palette payload' );
    assert.equal( result.contextualReadout?.surface, '#d14600', 'applyShowcaseState should return the contextual proof readout for the active dark mode' );
    assert.equal(
      documentRef.querySelector( '[data-sm-lab-contextual-value="source"]' )?.textContent,
      '#ff5500',
      'contextual proof source values should update from live state'
    );
    assert.equal(
      documentRef.querySelector( '[data-sm-lab-contextual-value="surface"]' )?.textContent,
      '#d14600',
      'contextual proof surface values should update from the selected variation'
    );
    assert.equal(
      documentRef.querySelector( '[data-sm-lab-contextual-value="text-status"]' )?.textContent,
      'AA',
      'contextual proof contrast labels should update from the generated palette'
    );
    const buttonWireAtVariation4 = documentRef.querySelector( '[data-sm-lab-token-source-wire="button"]' )?.getAttribute( 'd' );

    applyShowcaseState( {
      document: documentRef,
      getComputedStyle: () => ( {
        getPropertyValue: ( property ) => computedValues[ property ] || '',
      } ),
      state: {
        palette: 'contextual-lab',
        variation: 8,
        signal: 2,
        parentVariation: 5,
        dark: true,
        shifted: true,
        contextual: '#ff5500',
      },
      siteVariation: 1,
      palettes: [ createRuntimePalette() ],
      windowRef,
    } );

    assert.equal(
      documentRef.querySelector( '[data-sm-lab-button-token-map]' )?.getAttribute( 'data-sm-lab-token-source-grade-button' ),
      '11',
      'changing the active variation should move the button connector to the new resolved grade on the fixed rail'
    );
    assert.equal(
      documentRef.querySelector( '[data-sm-lab-button-token-map]' )?.getAttribute( 'data-sm-lab-token-source-grade-label' ),
      '1',
      'dark resolved fill grades should keep the text connector on the light contrast grade'
    );
    assert.notEqual(
      documentRef.querySelector( '[data-sm-lab-token-source-wire="button"]' )?.getAttribute( 'd' ),
      buttonWireAtVariation4,
      'changing the active variation should redraw the fill connector geometry'
    );
    assert.equal(
      documentRef.querySelector( '[data-sm-lab-token-source-wire="button"]' )?.getAttribute( 'data-sm-lab-token-wire-motion' ),
      'draw',
      'changed connector wires should be marked for draw animation'
    );
    assert.match(
      documentRef.querySelector( '[data-sm-lab-token-source-wire="button"]' )?.getAttribute( 'style' ) || '',
      /stroke-dashoffset:\s*640/,
      'changed connector wires should start from a hidden dash offset before animating in'
    );

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

  {
    const documentRef = createShowcaseDocument();
    const contextualZone = documentRef.querySelector( '[data-palette="contextual-lab"]' );
    contextualZone.appendChild( createSwatch( documentRef, 'accent' ) );

    const computedValues = {
      '--sm-current-bg-color': '#101010',
      '--sm-current-accent-color': '#ff5500',
      '--sm-current-fg1-color': '#fafafa',
      '--sm-current-fg2-color': '#d7d7d7',
    };
    const hasContextualAncestor = ( element ) => {
      let cursor = element;

      while ( cursor ) {
        if ( cursor.getAttribute?.( 'data-palette' ) === 'contextual-lab' ) {
          return true;
        }

        cursor = cursor.parentElement;
      }

      return false;
    };

    applyShowcaseState( {
      document: documentRef,
      getComputedStyle: ( element ) => ( {
        getPropertyValue: ( property ) => {
          if ( hasContextualAncestor( element ) && property === '--sm-current-accent-color' ) {
            return '#111111';
          }

          return computedValues[ property ] || '';
        },
      } ),
      state: {
        palette: 'contextual-lab',
        variation: 4,
        contextual: '#ff5500',
      },
      palettes: [ createRuntimePalette() ],
    } );

    assert.equal(
      contextualZone.querySelector( '[data-token="accent"] [data-token-value]' )?.textContent,
      '#111111',
      'token readbacks inside scoped palette zones should read from their own computed context'
    );
  }

  {
    const documentRef = createShowcaseDocument();
    const nestedSignalZone = documentRef.querySelector( '[data-color-signal="2"]' );
    nestedSignalZone.appendChild( createSwatch( documentRef, 'bg' ) );
    nestedSignalZone.appendChild( createSwatch( documentRef, 'accent' ) );

    const hasNestedSignalAncestor = ( element ) => {
      let cursor = element;

      while ( cursor ) {
        if ( cursor.getAttribute?.( 'data-color-signal' ) === '2' ) {
          return true;
        }

        cursor = cursor.parentElement;
      }

      return false;
    };
    const windowRef = {
      novablocks: {
        utils: {
          getSignals: () => [ 2, 5, 8, 11 ],
          getColorSignalClassnames: ( attributes ) => [
            `sm-palette-${ attributes.palette }`,
            `sm-variation-${ attributes.paletteVariation }`,
            `sm-color-signal-${ attributes.colorSignal }`,
            attributes.useSourceColorAsReference ? 'sm-palette--shifted' : '',
          ].filter( Boolean ).join( ' ' ),
        },
      },
      styleManager: {
        siteColorVariation: 1,
        colorsConfig: [
          {
            id: '1',
            sourceIndex: 6,
            variations: Array.from( { length: 12 }, ( _, index ) => ( {
              bg: `#${ String( index + 1 ).padStart( 6, '0' ) }`,
              fg1: index >= 6 ? '#ffffff' : '#111111',
            } ) ),
          },
        ],
      },
    };

    applyShowcaseState( {
      document: documentRef,
      getComputedStyle: ( element ) => ( {
        getPropertyValue: ( property ) => {
          if ( hasNestedSignalAncestor( element ) ) {
            if ( property === '--sm-current-bg-color' ) {
              return '#nested-bg';
            }

            if ( property === '--sm-current-accent-color' ) {
              return '#nested-accent';
            }
          }

          return {
            '--sm-current-bg-color': '#body-bg',
            '--sm-current-accent-color': '#body-accent',
            '--sm-current-fg1-color': '#body-fg1',
            '--sm-current-fg2-color': '#body-fg2',
          }[ property ] || '';
        },
      } ),
      state: {
        palette: '1',
        variation: 4,
        contextual: '#ff5500',
      },
      palettes: [ createRuntimePalette() ],
      windowRef,
    } );

    assert.equal( windowRef.styleManager.siteColorVariation, 4, 'the Lab runtime should publish its selected variation to Nova runtime state' );
    assert.ok( nestedSignalZone.classList.contains( 'sm-palette-1' ), 'Nova signal scopes should keep their palette class' );
    assert.ok( nestedSignalZone.classList.contains( 'sm-color-signal-2' ), 'Nova signal scopes should keep their color signal class' );
    assert.ok( nestedSignalZone.classList.contains( 'sm-variation-5' ), 'Nova signal scopes should compute variation from the active Lab variation' );
    assert.equal(
      nestedSignalZone.getAttribute( 'data-palette-variation' ),
      '1',
      'Nova signal scopes should keep their saved palette variation attribute'
    );
    assert.equal(
      nestedSignalZone.querySelector( '[data-token="bg"] [data-token-value]' )?.textContent,
      '#nested-bg',
      'nested Nova signal readbacks should read from the computed signal context'
    );
    assert.equal(
      nestedSignalZone.querySelector( '[data-token="accent"] [data-token-value]' )?.textContent,
      '#nested-accent',
      'nested Nova signal action readbacks should read from the computed signal context'
    );
  }
};
