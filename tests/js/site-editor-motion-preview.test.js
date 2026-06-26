import test from 'node:test';
import assert from 'node:assert/strict';

import {
  normalizeMotionPreviewState,
  playSiteEditorMotionPreview,
  renderTransitionSymbolMarkup,
} from '../../src/_js/site-editor/motion-preview.js';

const createPreviewDocument = () => {
  const elements = new Map();
  const timers = [];

  const documentRef = {
    body: {
      appendChild: element => {
        element.parentNode = documentRef.body;
        elements.set( element.id, element );
      },
    },
    defaultView: {
      setTimeout: ( callback, duration ) => {
        timers.push( { callback, duration } );
        return timers.length;
      },
      clearTimeout: () => {},
      __smSiteEditorMotionPreviewTimer: null,
    },
    createElement: tagName => ( {
      tagName: tagName.toUpperCase(),
      id: '',
      className: '',
      innerHTML: '',
      attributes: {},
      parentNode: null,
      setAttribute( name, value ) {
        this.attributes[ name ] = String( value );
      },
      remove() {
        elements.delete( this.id );
      },
    } ),
    getElementById: id => elements.get( id ) || null,
    timers,
  };

  return documentRef;
};

test( 'renderTransitionSymbolMarkup escapes plain text symbols', () => {
  const markup = renderTransitionSymbolMarkup( '<b>SM</b> & Co' );

  assert.match( markup, /&lt;b&gt;SM&lt;\/b&gt; &amp; Co/ );
} );

test( 'renderTransitionSymbolMarkup preserves inline svg symbols', () => {
  const markup = renderTransitionSymbolMarkup( '<svg viewBox="0 0 1 1"></svg>' );

  assert.equal( markup, '<svg viewBox="0 0 1 1"></svg>' );
} );

test( 'normalizeMotionPreviewState falls back to supported motion options', () => {
  assert.deepEqual(
    normalizeMotionPreviewState( {
      pageTransitionStyle: 'unknown',
      logoLoadingStyle: 'unknown',
      transitionSymbol: 12,
    } ),
    {
      pageTransitionStyle: 'border_iris',
      logoLoadingStyle: 'progress_bar',
      transitionSymbol: '',
    }
  );
} );

test( 'playSiteEditorMotionPreview appends a transient preview overlay', () => {
  const documentRef = createPreviewDocument();

  assert.equal(
    playSiteEditorMotionPreview(
      documentRef,
      {
        pageTransitionStyle: 'slide_wipe',
        logoLoadingStyle: 'cycling_images',
        transitionSymbol: 'SM',
      },
      { duration: 50 }
    ),
    true
  );

  const root = documentRef.getElementById( 'sm-site-editor-motion-preview-root' );
  assert.ok( root );
  assert.equal( root.attributes['aria-hidden'], 'true' );
  assert.match( root.className, /sm-site-editor-motion-preview--slide_wipe/ );
  assert.match( root.innerHTML, /SM/ );
  assert.equal( documentRef.timers[0].duration, 50 );

  documentRef.timers[0].callback();
  assert.equal( documentRef.getElementById( 'sm-site-editor-motion-preview-root' ), null );
} );

test( 'playSiteEditorMotionPreview keeps the default replay visible long enough to inspect', () => {
  const documentRef = createPreviewDocument();

  playSiteEditorMotionPreview( documentRef );

  assert.equal( documentRef.timers[0].duration, 2400 );
} );
