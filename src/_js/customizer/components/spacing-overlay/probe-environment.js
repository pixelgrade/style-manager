import { readPreviewEnvironment } from './contract-geometry';

/**
 * Resolve the page's runtime layout inputs (font sizes, spacing, gutters, the
 * saved-inset signal) AT A GIVEN VIEWPORT WIDTH, so the Layout board can model
 * 1024/1280/1440/1920 whatever the editor canvas width is.
 *
 * Anima's tokens are fluid (vw-based) and some switch at media queries, so they
 * cannot be read at the canvas width and extrapolated. Instead the canvas'
 * stylesheets (theme, Nova Blocks, and Style Manager's live-updated inline
 * style tags — so unsaved changes count) are cloned into one hidden, script-free
 * iframe sized to the modelled width, and the tokens are resolved there.
 */

const LOAD_CAP_MS = 3000;

let probeFrame = null;

const getSourceDocument = () => {
  try {
    const canvas = document.querySelector( 'iframe[name="editor-canvas"]' );
    if ( canvas?.contentDocument?.documentElement ) {
      return canvas.contentDocument;
    }
    const preview = window.wp?.customize?.previewer?.preview?.iframe?.[ 0 ];
    if ( preview?.contentDocument?.documentElement ) {
      return preview.contentDocument;
    }
  } catch ( e ) {
    // cross-origin or not ready
  }
  return null;
};

const getProbeFrame = () => {
  if ( probeFrame && probeFrame.isConnected ) {
    return probeFrame;
  }
  probeFrame = document.createElement( 'iframe' );
  probeFrame.setAttribute( 'aria-hidden', 'true' );
  probeFrame.setAttribute( 'tabindex', '-1' );
  probeFrame.setAttribute( 'title', 'Layout board probe' );
  probeFrame.style.cssText = 'position:fixed;left:-100000px;top:0;height:600px;border:0;visibility:hidden;pointer-events:none;';
  document.body.appendChild( probeFrame );
  return probeFrame;
};

const copyAttributes = ( from, to ) => {
  Array.from( to.attributes ).forEach( attr => to.removeAttribute( attr.name ) );
  Array.from( from.attributes ).forEach( attr => to.setAttribute( attr.name, attr.value ) );
};

/**
 * @param {number} width The viewport width to model.
 * @return {Promise<Object|null>} The environment, or null when no source page.
 */
export const probeEnvironmentAt = width => {
  const source = getSourceDocument();
  if ( ! source ) {
    return Promise.resolve( null );
  }

  try {
    const frame = getProbeFrame();
    frame.style.width = `${ Math.round( width ) }px`;
    const doc = frame.contentDocument;
    if ( ! doc.documentElement || ! doc.head || ! doc.body ) {
      doc.open();
      doc.write( '<!DOCTYPE html><html><head></head><body></body></html>' );
      doc.close();
    }

    copyAttributes( source.documentElement, doc.documentElement );
    copyAttributes( source.body, doc.body );

    const pending = [];
    doc.head.textContent = '';
    source.querySelectorAll( 'link[rel="stylesheet"], style' ).forEach( node => {
      const clone = doc.importNode( node, true );
      if ( 'LINK' === clone.tagName ) {
        pending.push( new Promise( resolve => {
          clone.addEventListener( 'load', resolve, { once: true } );
          clone.addEventListener( 'error', resolve, { once: true } );
        } ) );
      }
      doc.head.appendChild( clone );
    } );

    return Promise.race( [
      Promise.all( pending ),
      new Promise( resolve => setTimeout( resolve, LOAD_CAP_MS ) ),
    ] ).then( () => {
      const env = readPreviewEnvironment( frame.contentWindow );
      return env ? { ...env, viewport: Math.round( width ), vw: Math.round( width ) } : null;
    } ).catch( () => null );
  } catch ( e ) {
    return Promise.resolve( null );
  }
};

export const disposeEnvironmentProbe = () => {
  if ( probeFrame ) {
    probeFrame.remove();
    probeFrame = null;
  }
};
