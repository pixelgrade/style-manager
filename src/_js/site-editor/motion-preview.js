const PREVIEW_ROOT_ID = 'sm-site-editor-motion-preview-root';
const DEFAULT_DURATION = 2400;

const escapeHtml = value => String( value )
  .replace( /&/g, '&amp;' )
  .replace( /</g, '&lt;' )
  .replace( />/g, '&gt;' )
  .replace( /"/g, '&quot;' )
  .replace( /'/g, '&#039;' );

export const normalizeMotionPreviewState = ( values = {} ) => {
  const pageTransitionStyle = [ 'border_iris', 'slide_wipe' ].includes( values.pageTransitionStyle )
    ? values.pageTransitionStyle
    : 'border_iris';
  const logoLoadingStyle = [ 'progress_bar', 'cycling_images' ].includes( values.logoLoadingStyle )
    ? values.logoLoadingStyle
    : 'progress_bar';

  return {
    pageTransitionStyle,
    logoLoadingStyle,
    transitionSymbol: 'string' === typeof values.transitionSymbol ? values.transitionSymbol : '',
  };
};

export const renderTransitionSymbolMarkup = ( symbol, fallbackSymbol = '' ) => {
  const normalizedSymbol = 'string' === typeof symbol ? symbol.trim() : '';

  if ( /^<svg[\s>]/i.test( normalizedSymbol ) && /<\/svg>$/i.test( normalizedSymbol ) ) {
    return normalizedSymbol;
  }

  return `<svg viewBox="0 0 220 220" aria-hidden="true"><text x="50%" y="50%" text-anchor="middle" dominant-baseline="central" font-size="150" font-weight="700">${ escapeHtml( normalizedSymbol || fallbackSymbol || '' ) }</text></svg>`;
};

const getLoadingContentMarkup = ( state, fallbackSymbol ) => {
  if ( 'cycling_images' === state.logoLoadingStyle ) {
    return `<div class="sm-site-editor-motion-preview__symbol c-loader__logo">${ renderTransitionSymbolMarkup( state.transitionSymbol, fallbackSymbol ) }</div>`;
  }

  return '<div class="sm-site-editor-motion-preview__progress" aria-hidden="true"><span></span></div>';
};

const getOverlayMarkup = ( state, fallbackSymbol ) => `
  <style>
    #${ PREVIEW_ROOT_ID } {
      position: fixed;
      inset: 0;
      z-index: 100000;
      pointer-events: none;
      display: grid;
      place-items: center;
      overflow: hidden;
      background: var(--sm-current-accent-color, #1d1d1d);
      color: var(--sm-current-bg-color, #fff);
      animation: smSiteEditorMotionPreviewFade ${ DEFAULT_DURATION }ms ease-in-out forwards;
    }
    #${ PREVIEW_ROOT_ID }.sm-site-editor-motion-preview--slide_wipe {
      transform-origin: left center;
      animation-name: smSiteEditorMotionPreviewWipe;
    }
    #${ PREVIEW_ROOT_ID } .sm-site-editor-motion-preview__symbol {
      position: static;
      transform: none;
      width: min(26vw, 180px);
      color: currentColor;
    }
    #${ PREVIEW_ROOT_ID } .sm-site-editor-motion-preview__symbol svg {
      display: block;
      width: 100%;
      height: auto;
      overflow: visible;
      fill: currentColor;
    }
    #${ PREVIEW_ROOT_ID } .sm-site-editor-motion-preview__progress {
      width: min(42vw, 360px);
      height: 8px;
      overflow: hidden;
      border-radius: 999px;
      background: color-mix(in srgb, currentColor 25%, transparent);
    }
    #${ PREVIEW_ROOT_ID } .sm-site-editor-motion-preview__progress span {
      display: block;
      width: 100%;
      height: 100%;
      transform-origin: left center;
      background: currentColor;
      animation: smSiteEditorMotionPreviewProgress ${ DEFAULT_DURATION }ms ease-in-out forwards;
    }
    @keyframes smSiteEditorMotionPreviewFade {
      0% { opacity: 0; transform: scale(.96); }
      18%, 72% { opacity: 1; transform: scale(1); }
      100% { opacity: 0; transform: scale(1.04); }
    }
    @keyframes smSiteEditorMotionPreviewWipe {
      0% { opacity: 1; transform: scaleX(0); }
      20%, 72% { opacity: 1; transform: scaleX(1); }
      100% { opacity: 0; transform: scaleX(1); }
    }
    @keyframes smSiteEditorMotionPreviewProgress {
      0% { transform: scaleX(0); }
      75%, 100% { transform: scaleX(1); }
    }
  </style>
  ${ getLoadingContentMarkup( state, fallbackSymbol ) }
`;

export const clearSiteEditorMotionPreview = previewDocument => {
  if ( ! previewDocument ) {
    return;
  }

  const previewWindow = previewDocument.defaultView;
  if ( previewWindow && previewWindow.__smSiteEditorMotionPreviewTimer ) {
    previewWindow.clearTimeout( previewWindow.__smSiteEditorMotionPreviewTimer );
    previewWindow.__smSiteEditorMotionPreviewTimer = null;
  }

  const existingRoot = previewDocument.getElementById( PREVIEW_ROOT_ID );
  if ( existingRoot ) {
    existingRoot.remove();
  }
};

export const playSiteEditorMotionPreview = ( previewDocument, values = {}, config = {} ) => {
  clearSiteEditorMotionPreview( previewDocument );

  if ( ! previewDocument || ! previewDocument.body ) {
    return false;
  }

  const previewWindow = previewDocument.defaultView;
  if ( ! previewWindow ) {
    return false;
  }

  const state = normalizeMotionPreviewState( values );
  const root = previewDocument.createElement( 'div' );
  root.id = PREVIEW_ROOT_ID;
  root.className = `sm-site-editor-motion-preview sm-site-editor-motion-preview--${ state.pageTransitionStyle }`;
  root.setAttribute( 'aria-hidden', 'true' );
  root.innerHTML = getOverlayMarkup( state, config.fallbackSymbol || '' );

  previewDocument.body.appendChild( root );
  previewWindow.__smSiteEditorMotionPreviewTimer = previewWindow.setTimeout(
    () => clearSiteEditorMotionPreview( previewDocument ),
    config.duration || DEFAULT_DURATION
  );

  return true;
};
