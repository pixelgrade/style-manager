export const COLOR_TARGETS_GROUP = 'color-targets';
export const TARGET_REVEAL_CLASS = 'sm-se-target-reveal';
export const TARGET_PULSE_CLASS = 'sm-se-target-pulse';

// A crosshair "locate" glyph (reads as "pinpoint on the page"), inlined so it
// needs no icon font and inherits the button's currentColor.
const LOCATE_ICON_SVG = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="7"></circle><line x1="12" y1="1.5" x2="12" y2="4.5"></line><line x1="12" y1="19.5" x2="12" y2="22.5"></line><line x1="1.5" y1="12" x2="4.5" y2="12"></line><line x1="19.5" y1="12" x2="22.5" y2="12"></line><circle cx="12" cy="12" r="1.6" fill="currentColor" stroke="none"></circle></svg>';

const HIGHLIGHT_STYLE_ID = 'sm-se-target-feedback-style';
const LIVE_SITE_PREVIEW_IFRAME_SELECTOR = '.sm-live-site-overlay__iframe, iframe[title="Live site preview"]';
const EDITOR_CANVAS_IFRAME_SELECTOR = 'iframe[name="editor-canvas"]';

const WRAPPER_SELECTOR = [
  '.editor-styles-wrapper',
  '.block-editor-writing-flow',
  '.block-editor-block-list__layout',
  '.is-root-container',
].join( ',' );

const NON_TARGET_NODE_NAMES = new Set( [
  'HTML',
  'HEAD',
  'BODY',
  'SCRIPT',
  'STYLE',
  'TEMPLATE',
] );

const EDITOR_BLOCK_SCOPE = '.editor-styles-wrapper .block-editor-block-list__block';
const FRONTEND_PAGE_TITLE_SELECTORS = [
  '.page-title',
  '.entry-title',
  '.article-title',
  '.wp-block-post-title',
];
const EDITOR_PAGE_TITLE_SELECTORS = FRONTEND_PAGE_TITLE_SELECTORS.map( selector => `${ EDITOR_BLOCK_SCOPE } ${ selector }` );

const debounce = ( callback, wait = 150 ) => {
  let timeoutId = null;

  return ( ...args ) => {
    window.clearTimeout( timeoutId );
    timeoutId = window.setTimeout( () => callback( ...args ), wait );
  };
};

export const getControlContainerId = controlId => `customize-control-${ controlId.replace( /\[/g, '-' ).replace( /\]/g, '' ) }`;

export const controlToSettingId = controlId => controlId.replace( /_control$/, '' );

const targetStatusIds = new Map();

export const getTargetStatusId = controlKey => {
  const key = String( controlKey || 'target' );

  if ( ! targetStatusIds.has( key ) ) {
    targetStatusIds.set( key, `sm-se-target-status-${ targetStatusIds.size + 1 }` );
  }

  return targetStatusIds.get( key );
};

export const buildControlSettingMap = payload => {
  const map = {};
  const sections = payload?.structure?.sections || [];

  sections.forEach( section => {
    ( section.controls || [] ).forEach( control => {
      if ( ! control?.id ) {
        return;
      }

      map[ getControlContainerId( control.id ) ] = controlToSettingId( control.id );
    } );
  } );

  return map;
};

const isBroadTargetSelector = selector => {
  const value = String( selector || '' ).trim();
  return '*' === value || value.endsWith( ' *' );
};

const scopeSelector = ( selector, targetMode ) => 'editor' === targetMode
  ? `${ EDITOR_BLOCK_SCOPE } ${ selector }`
  : selector;

const isPageTitleSetting = settingId => /(^|[\[_-])page[_-]?title([\]_-]|$)/.test( String( settingId || '' ) );

const getPageTitleSelectors = targetMode => 'editor' === targetMode
  ? EDITOR_PAGE_TITLE_SELECTORS
  : FRONTEND_PAGE_TITLE_SELECTORS;

const getSemanticSelectorsForProperty = ( property, targetMode = 'editor' ) => {
  if ( 'string' !== typeof property ) {
    return [];
  }

  const headingMatch = property.match( /^--theme-heading-([1-6])-color$/ );
  if ( headingMatch ) {
    return [ scopeSelector( `h${ headingMatch[ 1 ] }`, targetMode ) ];
  }

  if ( '--theme-display-color' === property ) {
    return [
      scopeSelector( '.is-style-display', targetMode ),
      scopeSelector( '.has-display-font-size', targetMode ),
    ];
  }

  if ( '--theme-super-display-color' === property ) {
    return [
      scopeSelector( '.is-style-super-display', targetMode ),
      scopeSelector( '.has-super-display-font-size', targetMode ),
    ];
  }

  if ( property.startsWith( '--sm-button-' ) || property.startsWith( '--theme-button-' ) ) {
    return [
      scopeSelector( '.wp-block-button__link', targetMode ),
      scopeSelector( '.wp-element-button', targetMode ),
    ];
  }

  return [];
};

export const getSettingSelectors = ( settingId, {
  settings = {},
  editorCssSelectors = {},
  useEditorSelectors = true,
  targetMode = useEditorSelectors ? 'editor' : 'live',
} = {} ) => {
  const config = settings[ settingId ];

  if ( ! config || ! Array.isArray( config.css ) ) {
    return [];
  }

  if ( isPageTitleSetting( settingId ) ) {
    return getPageTitleSelectors( targetMode );
  }

  const overrides = useEditorSelectors ? editorCssSelectors[ settingId ] || {} : {};
  const selectors = [];

  config.css.forEach( ( propertyConfig, index ) => {
    const selector = 'undefined' !== typeof overrides[ index ]
      ? overrides[ index ]
      : propertyConfig?.selector;
    const semanticSelectors = isBroadTargetSelector( selector )
      ? getSemanticSelectorsForProperty( propertyConfig?.property, targetMode )
      : [];

    if ( semanticSelectors.length ) {
      selectors.push( ...semanticSelectors );
      return;
    }

    if ( 'string' === typeof selector && selector.trim() ) {
      selectors.push( selector.trim() );
    }
  } );

  return [ ...new Set( selectors ) ];
};

const getElementStyle = ( element, getComputedStyle ) => {
  if ( getComputedStyle ) {
    return getComputedStyle( element );
  }

  const view = element?.ownerDocument?.defaultView;
  return view?.getComputedStyle ? view.getComputedStyle( element ) : {};
};

export const isVisibleTargetElement = ( element, getComputedStyle ) => {
  if ( ! element || ! element.nodeName || NON_TARGET_NODE_NAMES.has( element.nodeName ) ) {
    return false;
  }

  if ( element.hidden || ( element.closest && element.closest( '[hidden]' ) ) ) {
    return false;
  }

  if (
    'true' === element.getAttribute?.( 'aria-hidden' )
    || ( element.closest && element.closest( '[aria-hidden="true"]' ) )
  ) {
    return false;
  }

  if (
    ( element.matches && element.matches( WRAPPER_SELECTOR ) )
    || ( element.closest && element.closest( WRAPPER_SELECTOR ) === element )
  ) {
    return false;
  }

  const style = getElementStyle( element, getComputedStyle );
  if (
    'none' === style.display
    || 'hidden' === style.visibility
    || 'collapse' === style.visibility
    || '0' === String( style.opacity )
  ) {
    return false;
  }

  const rect = element.getBoundingClientRect ? element.getBoundingClientRect() : null;
  if ( ! rect || rect.width <= 0 || rect.height <= 0 ) {
    return false;
  }

  return ! element.getClientRects || element.getClientRects().length > 0;
};

export const getVisibleTargetElements = ( {
  document,
  selectors = [],
  getComputedStyle,
} = {} ) => {
  if ( ! document || ! selectors.length ) {
    return [];
  }

  const elements = [];
  const seen = new Set();

  selectors.forEach( selector => {
    let matches = [];

    try {
      matches = Array.from( document.querySelectorAll( selector ) );
    } catch ( e ) {
      return;
    }

    matches.forEach( element => {
      if ( seen.has( element ) || ! isVisibleTargetElement( element, getComputedStyle ) ) {
        return;
      }

      seen.add( element );
      elements.push( element );
    } );
  } );

  return elements;
};

export const formatVisibleCount = count => {
  const normalized = Number( count ) || 0;

  if ( normalized <= 0 ) {
    return 'not on this page';
  }

  return `${ normalized } visible`;
};

const getFrameDocument = iframe => {
  if ( ! iframe ) {
    return null;
  }

  try {
    return iframe.contentDocument;
  } catch ( e ) {
    return null;
  }
};

export const getTargetFrameContext = ( { document: rootDocument = globalThis.document } = {} ) => {
  if ( ! rootDocument ) {
    return {
      document: null,
      frame: null,
      mode: 'editor',
    };
  }

  const liveFrame = rootDocument.querySelector( LIVE_SITE_PREVIEW_IFRAME_SELECTOR );
  const liveDocument = getFrameDocument( liveFrame );
  if ( liveDocument?.body ) {
    return {
      document: liveDocument,
      frame: liveFrame,
      mode: 'live',
    };
  }

  const editorFrame = rootDocument.querySelector( EDITOR_CANVAS_IFRAME_SELECTOR );
  return {
    document: getFrameDocument( editorFrame ),
    frame: editorFrame,
    mode: 'editor',
  };
};

const ensureCanvasHighlightStyle = canvasDocument => {
  if ( ! canvasDocument || ! canvasDocument.head || canvasDocument.getElementById( HIGHLIGHT_STYLE_ID ) ) {
    return;
  }

  const style = canvasDocument.createElement( 'style' );
  style.id = HIGHLIGHT_STYLE_ID;
  style.textContent = `
    .${ TARGET_REVEAL_CLASS },
    .${ TARGET_PULSE_CLASS } {
      outline: 2px solid rgba(30, 30, 30, .45) !important;
      outline-offset: 2px !important;
      box-shadow: 0 0 0 4px rgba(56, 88, 233, .16) !important;
    }

    .${ TARGET_PULSE_CLASS } {
      animation: sm-se-target-pulse 900ms ease-out 1;
    }

    @keyframes sm-se-target-pulse {
      0% {
        outline-color: var(--wp-admin-theme-color, #3858e9);
        box-shadow: 0 0 0 0 rgba(56, 88, 233, .45);
      }
      100% {
        outline-color: rgba(30, 30, 30, .45);
        box-shadow: 0 0 0 8px rgba(56, 88, 233, 0);
      }
    }
  `;
  canvasDocument.head.appendChild( style );
};

const getRowLabel = row => {
  return row.querySelector( '.components-toggle-control__label, .components-base-control__label, .customize-control-title' )
    ?.textContent
    ?.trim() || '';
};

const getRowMeta = row => {
  const field = row.querySelector( '.components-base-control__field' ) || row.querySelector( '.sm-native-control' ) || row;
  // ToggleControl wraps the switch + label in an HStack; the status rides on a
  // second line of that HStack (laid out as a grid) so it sits beneath the label.
  // The CSS only grids a direct-child HStack, so fall back to the field itself
  // rather than to some arbitrary nested HStack.
  const stack = field.querySelector( ':scope > .components-h-stack' ) || field;
  const input = row.querySelector( 'input, select, textarea' );

  // Status and locate are created independently so a React re-render that drops
  // one of them (e.g. on toggle) is healed on the next refresh.
  if ( ! row.querySelector( '.sm-se-target-status' ) ) {
    const status = document.createElement( 'span' );
    status.className = 'sm-se-target-status';
    status.setAttribute( 'aria-live', 'polite' );
    stack.appendChild( status );

    if ( input ) {
      const statusId = getTargetStatusId( row.id || row.dataset.smTargetSetting );
      status.id = statusId;
      const describedBy = input.getAttribute( 'aria-describedby' );
      input.setAttribute( 'aria-describedby', describedBy ? `${ describedBy } ${ statusId }` : statusId );
    }
  }

  let meta = row.querySelector( '.sm-se-target-meta' );
  if ( ! meta ) {
    // The meta cluster carries only the locate affordance, docked to the right.
    meta = document.createElement( 'span' );
    meta.className = 'sm-se-target-meta';

    const locate = document.createElement( 'button' );
    locate.type = 'button';
    locate.className = 'sm-se-target-locate';
    locate.innerHTML = LOCATE_ICON_SVG;
    meta.appendChild( locate );

    field.appendChild( meta );
  }

  return meta;
};

const updateRowStatus = ( row, count ) => {
  const meta = getRowMeta( row );
  const status = row.querySelector( '.sm-se-target-status' );
  const locate = meta.querySelector( '.sm-se-target-locate' );
  const label = getRowLabel( row );

  status.textContent = formatVisibleCount( count );
  row.classList.toggle( 'sm-se-target-row--has-visible-targets', count > 0 );

  const locateHint = label ? `Locate ${ label } in preview` : 'Locate target in preview';
  locate.hidden = count <= 0;
  locate.disabled = count <= 0;
  locate.setAttribute( 'aria-label', locateHint );
  locate.setAttribute( 'title', locateHint );
};

const isElementInActivePanel = element => {
  const tabPanel = element.closest( '.sm-se-tab-panel' );
  return ! tabPanel || tabPanel.classList.contains( 'is-active' );
};

const getTargetRows = group => Array.from( group.querySelectorAll( ':scope > .sm-se-group-panel__content > li.sm-se-target-row' ) )
  .filter( row => ! row.classList.contains( 'sm-se-control-inactive' ) && ! row.classList.contains( 'sm-se-control-dependent-hidden' ) );

export const initializeColorTargetFeedback = ( root, payload, {
  customize = window.wp?.customize,
  getTargetContext = getTargetFrameContext,
  pulseDelay = 180,
  pulseDuration = 950,
} = {} ) => {
  const settings = window.styleManager?.config?.settings || {};
  const editorCssSelectors = payload?.editorCssSelectors || {};
  const controlSettingMap = buildControlSettingMap( payload );
  const groups = Array.from( root.querySelectorAll( `[data-sm-group="${ COLOR_TARGETS_GROUP }"]` ) );

  if ( ! groups.length ) {
    return {
      refresh: () => {},
      destroy: () => {},
    };
  }

  let revealedElements = [];
  let canvasObserver = null;
  let currentFrame = null;
  const rowsBySettingId = new Map();
  const pulseTimeouts = new WeakMap();

  const getTarget = () => getTargetContext();
  const getSelectors = ( row, targetMode = 'editor' ) => getSettingSelectors( row.dataset.smTargetSetting, {
    settings,
    editorCssSelectors,
    targetMode,
    useEditorSelectors: 'editor' === targetMode,
  } );

  const getElementsForRow = row => {
    const target = getTarget();
    if ( target.document ) {
      ensureCanvasHighlightStyle( target.document );
    }

    return getVisibleTargetElements( {
      document: target.document,
      selectors: getSelectors( row, target.mode ),
    } );
  };

  const clearReveal = () => {
    revealedElements.forEach( element => element.classList?.remove( TARGET_REVEAL_CLASS ) );
    revealedElements = [];
  };

  const revealRow = row => {
    clearReveal();

    const elements = getElementsForRow( row );
    elements.forEach( element => element.classList?.add( TARGET_REVEAL_CLASS ) );
    revealedElements = elements;
  };

  const pulseRow = row => {
    if ( pulseTimeouts.has( row ) ) {
      window.clearTimeout( pulseTimeouts.get( row ) );
    }

    pulseTimeouts.set( row, window.setTimeout( () => {
      pulseTimeouts.delete( row );

      const elements = getElementsForRow( row );
      elements.forEach( element => {
        element.classList?.remove( TARGET_PULSE_CLASS );
        // Restart the animation for repeated toggles on the same row.
        void element.offsetWidth;
        element.classList?.add( TARGET_PULSE_CLASS );
      } );

      window.setTimeout( () => {
        elements.forEach( element => element.classList?.remove( TARGET_PULSE_CLASS ) );
      }, pulseDuration );
    }, pulseDelay ) );
  };

  const refresh = () => {
    groups.forEach( group => {
      if ( group.hidden || ! isElementInActivePanel( group ) ) {
        return;
      }

      getTargetRows( group ).forEach( row => {
        updateRowStatus( row, getElementsForRow( row ).length );
      } );
    } );
  };

  const debouncedRefresh = debounce( refresh, 150 );

  groups.forEach( group => {
    const rows = Array.from( group.querySelectorAll( ':scope > .sm-se-group-panel__content > li' ) );

    rows.forEach( row => {
      const settingId = controlSettingMap[ row.id ];
      const selectors = getSettingSelectors( settingId, { settings, editorCssSelectors } );

      if ( ! settingId || ! selectors.length ) {
        return;
      }

      row.dataset.smTargetSetting = settingId;
      row.classList.add( 'sm-se-target-row' );
      rowsBySettingId.set( settingId, row );
      updateRowStatus( row, 0 );
    } );
  } );

  root.addEventListener( 'mouseover', event => {
    const row = event.target.closest?.( '.sm-se-target-row' );
    if ( row && root.contains( row ) && ! row.contains( event.relatedTarget ) ) {
      revealRow( row );
    }
  } );

  root.addEventListener( 'mouseout', event => {
    const row = event.target.closest?.( '.sm-se-target-row' );
    if ( row && root.contains( row ) && ! row.contains( event.relatedTarget ) ) {
      clearReveal();
    }
  } );

  root.addEventListener( 'focusin', event => {
    const row = event.target.closest?.( '.sm-se-target-row' );
    if ( row && root.contains( row ) ) {
      revealRow( row );
    }
  } );

  root.addEventListener( 'focusout', event => {
    const row = event.target.closest?.( '.sm-se-target-row' );
    if ( row && root.contains( row ) && ! row.contains( event.relatedTarget ) ) {
      clearReveal();
    }
  } );

  root.addEventListener( 'sm:site-editor-group-toggled', event => {
    if ( event.detail?.group === COLOR_TARGETS_GROUP && event.detail?.expanded ) {
      window.setTimeout( refresh, 0 );
    }
  } );

  root.addEventListener( 'sm:site-editor-tab-activated', event => {
    if ( 'sm_color_usage_section' === event.detail?.id ) {
      window.setTimeout( refresh, 0 );
    }
  } );

  root.addEventListener( 'click', event => {
    const locate = event.target.closest?.( '.sm-se-target-locate' );
    if ( locate ) {
      event.preventDefault();
      const row = locate.closest( '.sm-se-target-row' );
      const first = row ? getElementsForRow( row )[ 0 ] : null;

      if ( first ) {
        first.scrollIntoView?.( { behavior: 'smooth', block: 'center', inline: 'nearest' } );
        pulseRow( row );
      }
    }
  } );

  root.addEventListener( 'change', event => {
    const row = event.target.closest?.( '.sm-se-target-row' );
    if ( row && root.contains( row ) ) {
      pulseRow( row );
    }
  }, true );

  const bindCanvasMutationObserver = () => {
    const target = getTarget();

    if ( target.frame && target.frame !== currentFrame ) {
      currentFrame = target.frame;
      target.frame.addEventListener( 'load', () => {
        bindCanvasMutationObserver();
        refresh();
      } );
    }

    if ( canvasObserver ) {
      canvasObserver.disconnect();
      canvasObserver = null;
    }

    if ( window.MutationObserver && target.document?.body ) {
      canvasObserver = new MutationObserver( debouncedRefresh );
      canvasObserver.observe( target.document.body, {
        childList: true,
        subtree: true,
        attributes: true,
        attributeFilter: [ 'class', 'style', 'hidden', 'aria-hidden' ],
      } );
    }
  };

  bindCanvasMutationObserver();

  const bodyObserver = window.MutationObserver
    ? new MutationObserver( debounce( () => {
      bindCanvasMutationObserver();
      refresh();
    }, 250 ) )
    : null;

  if ( bodyObserver ) {
    bodyObserver.observe( document.body, { childList: true, subtree: true } );
  }

  const onSettingChange = settingId => {
    const row = rowsBySettingId.get( settingId );
    if ( row ) {
      pulseRow( row );
    }
  };

  if ( customize?.bind ) {
    customize.bind( 'sm:setting-change', onSettingChange );
  }

  refresh();

  return {
    refresh,
    destroy: () => {
      clearReveal();
      if ( canvasObserver ) {
        canvasObserver.disconnect();
      }
      if ( bodyObserver ) {
        bodyObserver.disconnect();
      }
      if ( customize?.unbind ) {
        customize.unbind( 'sm:setting-change', onSettingChange );
      }
    },
  };
};
