import { initializeVoiceTunerAccordion } from '../../src/_js/customizer/font-palettes/voice-tuner-accordion.js';

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
    tokens.forEach( token => this.tokens.add( token ) );
    this.sync();
  }

  remove( ...tokens ) {
    tokens.forEach( token => this.tokens.delete( token ) );
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
    this.eventListeners = new Map();
    this.classList = new FakeClassList( this );
    this._className = '';
    this.hidden = false;
    this.textContent = '';
    this.innerHTML = '';
  }

  get className() {
    return this._className;
  }

  set className( value ) {
    this.classList.setFromString( value );
  }

  get id() {
    return this.getAttribute( 'id' ) || '';
  }

  set id( value ) {
    this.setAttribute( 'id', value );
  }

  append( ...children ) {
    children.forEach( child => this.appendChild( child ) );
  }

  appendChild( child ) {
    if ( child.parentElement ) {
      child.parentElement.removeChild( child );
    }

    child.parentElement = this;
    this.children.push( child );
    this.ownerDocument.registerTree( child );

    return child;
  }

  removeChild( child ) {
    const index = this.children.indexOf( child );

    if ( index === -1 ) {
      return child;
    }

    this.children.splice( index, 1 );
    child.parentElement = null;

    return child;
  }

  insertBefore( child, referenceChild ) {
    if ( child.parentElement ) {
      child.parentElement.removeChild( child );
    }

    const index = this.children.indexOf( referenceChild );

    if ( index === -1 ) {
      return this.appendChild( child );
    }

    child.parentElement = this;
    this.children.splice( index, 0, child );
    this.ownerDocument.registerTree( child );

    return child;
  }

  setAttribute( name, value ) {
    const serializedValue = String( value );
    this.attributes.set( name, serializedValue );

    if ( name === 'class' ) {
      this.classList.setFromString( serializedValue );
    }

    if ( name === 'id' ) {
      this.ownerDocument.elementsById.set( serializedValue, this );
    }
  }

  getAttribute( name ) {
    return this.attributes.has( name ) ? this.attributes.get( name ) : null;
  }

  addEventListener( type, listener ) {
    if ( ! this.eventListeners.has( type ) ) {
      this.eventListeners.set( type, [] );
    }

    this.eventListeners.get( type ).push( listener );
  }

  click() {
    const listeners = this.eventListeners.get( 'click' ) || [];

    listeners.forEach( listener => {
      listener( {
        currentTarget: this,
        preventDefault() {},
      } );
    } );
  }

  querySelector( selector ) {
    if ( selector.startsWith( '.' ) ) {
      const className = selector.slice( 1 );

      return this.findFirst( element => element.classList.contains( className ) );
    }

    return null;
  }

  findFirst( predicate ) {
    for ( const child of this.children ) {
      if ( predicate( child ) ) {
        return child;
      }

      const nestedMatch = child.findFirst( predicate );

      if ( nestedMatch ) {
        return nestedMatch;
      }
    }

    return null;
  }
}

class FakeDocument {
  constructor() {
    this.elementsById = new Map();
  }

  createElement( tagName ) {
    return new FakeElement( tagName, this );
  }

  getElementById( id ) {
    return this.elementsById.get( id ) || null;
  }

  registerTree( element ) {
    if ( element.id ) {
      this.elementsById.set( element.id, element );
    }

    element.children.forEach( child => this.registerTree( child ) );
  }
}

const createControl = ( documentRef, id, className, titleText = '' ) => {
  const control = documentRef.createElement( 'li' );
  control.id = id;
  control.className = className;

  if ( titleText ) {
    const title = documentRef.createElement( 'span' );
    title.className = 'customize-control-title';
    title.textContent = titleText;
    control.appendChild( title );
  }

  return control;
};

export const runVoiceTunerAccordionTests = async ( assert ) => {
  const documentRef = new FakeDocument();
  const section = documentRef.createElement( 'ul' );
  section.id = 'sub-accordion-section-sm_font_palettes_section';

  const titleControl = createControl(
    documentRef,
    'customize-control-sm_voice_tuner_label_control',
    'pix_customizer_setting customize-control customize-control-html',
    'Tune your project\'s voice:'
  );
  const formalityControl = createControl( documentRef, 'customize-control-sm_voice_formality_control', 'pix_customizer_setting customize-control customize-control-sm_radio' );
  const energyControl = createControl( documentRef, 'customize-control-sm_voice_energy_control', 'pix_customizer_setting customize-control customize-control-sm_radio' );
  const warmthControl = createControl( documentRef, 'customize-control-sm_voice_warmth_control', 'pix_customizer_setting customize-control customize-control-sm_radio' );
  const traditionControl = createControl( documentRef, 'customize-control-sm_voice_tradition_control', 'pix_customizer_setting customize-control customize-control-sm_radio' );
  const fontPaletteControl = createControl( documentRef, 'customize-control-sm_font_palette_control', 'pix_customizer_setting customize-control customize-control-preset' );
  const voiceTunerControls = [ titleControl, formalityControl, energyControl, warmthControl, traditionControl ];

  section.append( ...voiceTunerControls, fontPaletteControl );

  initializeVoiceTunerAccordion( { document: documentRef } );

  const toggleControl = documentRef.getElementById( 'customize-control-sm_voice_tuner_toggle_control' );
  const toggleButton = toggleControl?.children?.[0];
  const toggleLabel = toggleButton?.querySelector( '.sm-panel-toggle__label' );

  assert.ok( toggleControl, 'expected a Voice Tuner toggle control to be inserted' );
  assert.equal( section.children[0], toggleControl, 'expected the toggle control to render before the Voice Tuner controls' );
  assert.equal( toggleButton?.getAttribute( 'aria-expanded' ), 'false', 'expected the Voice Tuner toggle to start collapsed' );
  assert.equal( toggleLabel?.textContent, 'Tune your project\'s voice', 'expected the toggle label to reuse the Voice Tuner title without the trailing colon' );
  assert.ok( titleControl.classList.contains( 'sm-voice-tuner-accordion__intro' ), 'expected the intro control to be marked for accordion intro styling' );
  assert.deepEqual(
    voiceTunerControls.map( control => control.hidden ),
    [ true, true, true, true, true ],
    'expected the Voice Tuner controls to be hidden while collapsed'
  );

  toggleButton.click();

  assert.equal( toggleButton.getAttribute( 'aria-expanded' ), 'true', 'expected the toggle button to report the expanded state after one click' );
  assert.deepEqual(
    voiceTunerControls.map( control => control.hidden ),
    [ false, false, false, false, false ],
    'expected the Voice Tuner controls to become visible when expanded'
  );

  toggleButton.click();

  assert.equal( toggleButton.getAttribute( 'aria-expanded' ), 'false', 'expected the toggle button to report the collapsed state after a second click' );
  assert.deepEqual(
    voiceTunerControls.map( control => control.hidden ),
    [ true, true, true, true, true ],
    'expected the Voice Tuner controls to collapse again on the second click'
  );

  initializeVoiceTunerAccordion( { document: documentRef } );

  assert.equal(
    section.children.filter( child => child.id === 'customize-control-sm_voice_tuner_toggle_control' ).length,
    1,
    'expected Voice Tuner accordion initialization to avoid inserting duplicate toggle controls'
  );
};
