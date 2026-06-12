/* global wp */
/**
 * Style Manager in the Site Editor.
 *
 * Registers a PluginSidebar that hosts the original Customizer-rendered
 * Style Manager controls, driven by the original JS engine running on a
 * standalone wp.customize-compatible store (see ./customize-api.js).
 */
import React from 'react';
import ReactDOM from 'react-dom';
import $ from 'jquery';
import _ from 'lodash';

import './style.scss';

import { createCustomizeApi, createContainerObject } from './customize-api';
import { initializePreview } from './preview';
import { mountNativeControls, getResettableSettings, PanelResetMenu, VoiceTunerPanel } from './native-controls';
import { ColorsOverlay, TypographyOverlay, SpacingOverlay } from '../customizer/components';
// Keep the original preview-tabs styles (tab pills, overlay shells).
import '../customizer/components/preview-tabs/style.scss';

// The original engine modules — the same code the Customizer runs.
import * as globalService from '../customizer/global-service';
import { handleColorSelectFields } from '../customizer/fields/color-select';
import { handleRangeFields } from '../customizer/fields/range';
import { handleTabs } from '../customizer/fields/tabs';
import { handlePresets } from '../customizer/fields/preset';
import { handleFoldingFields } from '../customizer/folding-fields';
import { initializePaletteBuilder } from '../customizer/colors/initialize-palette-builder';
import { applyColorationValueToFields } from '../customizer/colors/apply-coloration-value-to-fields';
import { initializeFonts } from '../customizer/fonts';
import { initializeFontPalettes } from '../customizer/font-palettes';
import { maybeLoadWebfontloaderScript } from '../utils';

import {
  getFontDetails,
  determineFontType,
  convertFontVariantToFVD,
} from '../customizer/fonts/utils';
import { maybeFillPalettesArray, getCSSFromPalettes } from '../customizer/utils';

// Same public API the customizer bundle exposes on sm.customizer — other code
// (control inline scripts, dark mode, preview callbacks) falls back to it.
export { getFontDetails, determineFontType, convertFontVariantToFVD } from '../customizer/fonts/utils';
export { maybeFillPalettesArray, getCSSFromPalettes } from '../customizer/utils';

const payload = window._styleManagerSiteEditor;

const ensureSmCustomizerAlias = () => {
  window.sm = window.sm || {};

  if ( ! window.sm.customizer ) {
    window.sm.customizer = {
      getFontDetails,
      determineFontType,
      convertFontVariantToFVD,
      maybeFillPalettesArray,
      getCSSFromPalettes,
    };
  }
};

/**
 * The engine singleton: built once, attached/detached as the sidebar opens
 * and closes, never re-initialized (jQuery bindings survive DOM detachment).
 */
let engine = null;

/**
 * The container <li> id WP renders for a control id (brackets become dashes).
 */
const getControlContainerId = controlId => `customize-control-${ controlId.replace( /\[/g, '-' ).replace( /\]/g, '' ) }`;

/**
 * Build a tab panel (description + controls list) for a section's data.
 */
const buildSectionPanel = section => {
  const panel = document.createElement( 'div' );
  panel.className = 'sm-se-tab-panel';
  panel.setAttribute( 'data-tab-section', section.id );

  if ( section.description ) {
    const descriptionEl = document.createElement( 'div' );
    descriptionEl.className = 'sm-se-page__description customize-control-description';
    descriptionEl.innerHTML = section.description;
    panel.appendChild( descriptionEl );
  }

  const contentEl = document.createElement( 'ul' );
  // Note: deliberately NOT using the `accordion-section-content` class here —
  // wp-admin's generic accordion styles (common.css) would hide it outside
  // the Customizer.
  contentEl.className = 'sm-se-page__content customize-pane-child';
  contentEl.id = `sub-accordion-section-${ section.id }`;
  contentEl.innerHTML = section.controls.map( control => control.html ).join( '' );
  panel.appendChild( contentEl );

  // Apply the server-evaluated active states (active_callback results) —
  // re-evaluated on changes by the active-states refresher.
  section.controls.forEach( control => {
    if ( false === control.active ) {
      const li = contentEl.querySelector( `#${ CSS.escape( getControlContainerId( control.id ) ) }` );
      if ( li ) {
        li.classList.add( 'sm-se-control-inactive' );
      }
    }
  } );

  // Inject group headers before specific controls (payload-configured).
  ( ( payload.sectionGroupHeaders || {} )[ section.id ] || [] ).forEach( header => {
    const li = contentEl.querySelector( `#${ CSS.escape( getControlContainerId( `${ header.before }_control` ) ) }` );
    if ( li ) {
      const heading = document.createElement( 'li' );
      heading.className = 'customize-control sm-se-group-title';
      heading.textContent = header.label;
      contentEl.insertBefore( heading, li );
    }
  } );

  // The voice tuner as a "find by voice" filter panel attached to the font
  // palette list (the floating accordion rows are hidden in this context).
  if ( 'sm_font_palettes_section' === section.id ) {
    const paletteLi = contentEl.querySelector( '#customize-control-sm_font_palette_control' );
    if ( paletteLi ) {
      const host = document.createElement( 'li' );
      host.className = 'customize-control sm-voice-panel-li';
      contentEl.insertBefore( host, paletteLi );
      ReactDOM.render( <VoiceTunerPanel />, host );
    }
  }

  // The section reset menu (core's Query Loop pattern): a 3-dot menu with
  // per-field reset and Reset all.
  const resettable = getResettableSettings( section );
  if ( resettable.length ) {
    const tools = document.createElement( 'div' );
    tools.className = 'sm-se-panel-tools';
    panel.insertBefore( tools, panel.firstChild );
    ReactDOM.render( <PanelResetMenu items={ resettable } groupLabel={ section.title } />, tools );
  }

  return panel;
};

/**
 * Build one section: a menu row (Nova Blocks-style drill-down trigger) plus a
 * full-panel page with a back header holding the section's controls.
 *
 * Granular child sections (payload.sectionTabs) render as tabs inside the
 * parent page — the high-level -> low-level pattern Nova Blocks uses —
 * instead of standalone "Theme Options" entries.
 */
const buildSectionElement = ( api, section, menuEl, pagesEl, updateView, sectionsById ) => {
  // The drill-down trigger row in the menu.
  const rowEl = document.createElement( 'button' );
  rowEl.type = 'button';
  rowEl.className = 'sm-se-row';
  rowEl.dataset.section = section.id;
  rowEl.innerHTML = `<span class="sm-se-row__icon" aria-hidden="true"></span><span class="sm-se-row__label">${ section.title }</span><span class="sm-se-row__chevron" aria-hidden="true"></span>`;
  menuEl.appendChild( rowEl );

  // The section page.
  const pageEl = document.createElement( 'div' );
  pageEl.className = 'sm-se-page sm-se-section';
  pageEl.setAttribute( 'data-section-id', section.id );

  const headerEl = document.createElement( 'div' );
  headerEl.className = 'sm-se-page__header';
  headerEl.innerHTML = `
    <button type="button" class="sm-se-page__back" aria-label="Back"></button>
    <span class="sm-se-page__title">${ section.title }</span>
  `;
  pageEl.appendChild( headerEl );

  // Sections that own a preview (a system board, or the Live Site flow for
  // Motion) get a Preview affordance right in the page header. The button
  // toggles: while its preview is open it reads "Close Preview".
  if ( SECTION_PREVIEW_MODES[ section.id ] ) {
    const previewEntry = SECTION_PREVIEW_MODES[ section.id ];
    const previewButton = document.createElement( 'button' );
    previewButton.type = 'button';
    previewButton.className = 'sm-se-page__preview';

    const renderPreviewButton = currentMode => {
      const isOpen = currentMode === previewEntry.mode;
      previewButton.textContent = isOpen
        ? wp.i18n.__( 'Close Preview', '__plugin_txtd' )
        : wp.i18n.__( 'Preview', '__plugin_txtd' );
      previewButton.classList.toggle( 'is-open', isOpen );
    };
    renderPreviewButton( getPreviewMode() );
    previewModeListeners.add( renderPreviewButton );

    previewButton.addEventListener( 'click', () => {
      if ( getPreviewMode() === previewEntry.mode ) {
        setPreviewMode( null );
      } else {
        setPreviewMode( previewEntry.mode, previewEntry.context || null );
      }
    } );
    headerEl.appendChild( previewButton );
  }

  const tabsConfig = ( payload.sectionTabs || {} )[ section.id ];
  const tabEntries = ( tabsConfig || [ { id: section.id, label: '' } ] )
    .map( tab => ( { ...tab, data: tab.id === section.id ? section : sectionsById[ tab.id ] } ) )
    .filter( tab => tab.data );

  let activateTab = () => {};

  if ( tabEntries.length > 1 ) {
    const tabBar = document.createElement( 'div' );
    tabBar.className = 'sm-se-tabs';
    pageEl.appendChild( tabBar );

    tabEntries.forEach( tab => {
      const button = document.createElement( 'button' );
      button.type = 'button';
      button.className = 'sm-se-tabs__tab';
      button.setAttribute( 'data-tab-section', tab.id );
      button.textContent = tab.label;
      button.addEventListener( 'click', () => activateTab( tab.id ) );
      tabBar.appendChild( button );
    } );
  }

  const panels = tabEntries.map( tab => {
    const panel = buildSectionPanel( tab.data );
    pageEl.appendChild( panel );
    return { id: tab.id, panel };
  } );

  activateTab = id => {
    panels.forEach( ( { id: panelId, panel } ) => {
      panel.classList.toggle( 'is-active', panelId === id );
    } );
    pageEl.querySelectorAll( '.sm-se-tabs__tab' ).forEach( button => {
      button.classList.toggle( 'is-active', button.getAttribute( 'data-tab-section' ) === id );
    } );
  };
  activateTab( section.id );

  pagesEl.appendChild( pageEl );

  const scrollTop = () => {
    const body = document.querySelector( '.sm-site-editor-sidebar__body' );
    if ( body ) {
      body.scrollTop = 0;
    }
  };

  // Register a section object so the engine's section-based features work
  // (shortcuts focus, expanded bindings, voice tuner placement, etc.).
  const sectionObject = createContainerObject( section.id, {
    container: $( pageEl ),
    expanded: false,
    onFocus: () => {
      activateTab( section.id );
      scrollTop();
    },
  } );

  sectionObject.expanded.bind( isExpanded => {
    pageEl.classList.toggle( 'open', !! isExpanded );

    // One section page at a time — same model as the Customizer's slide-in
    // sections and the Site Editor's Global Styles drill-down.
    api.section.each( other => {
      if ( other === sectionObject || ! other.expanded.get() ) {
        return;
      }
      if ( isExpanded ? other.parentSection !== sectionObject : other.parentSection === sectionObject ) {
        other.expanded.set( false );
      }
    } );

    updateView();
  } );

  rowEl.addEventListener( 'click', () => {
    sectionObject.focus();
  } );

  headerEl.querySelector( '.sm-se-page__back' ).addEventListener( 'click', () => {
    // Shortcuts within the page push the Customizer's "return to origin"
    // stack; in the tabs model the origin is this same page, so popping it
    // would re-open what we are closing. Back always means the menu here.
    globalService.setBackArray( [] );
    sectionObject.expanded.set( false );
  } );

  api.section.add( section.id, sectionObject );

  // Child sections register their own objects: focusing them (shortcuts,
  // deep links) opens the parent page on their tab.
  tabEntries.forEach( ( { id, data } ) => {
    if ( id === section.id ) {
      return;
    }

    const childObject = createContainerObject( id, {
      container: $( pageEl.querySelector( `[data-tab-section="${ id }"].sm-se-tab-panel` ) ),
      expanded: false,
      onFocus: () => {
        sectionObject.expanded.set( true );
        activateTab( id );
        scrollTop();
      },
    } );
    childObject.parentSection = sectionObject;
    api.section.add( id, childObject );
  } );
};

const buildRoot = api => {
  const { panels, sections } = payload.structure;

  const root = document.createElement( 'div' );
  // The original Customizer CSS anchors on these IDs/classes; the real
  // Customizer app never coexists with the Site Editor, so they are safe here.
  root.id = 'customize-controls';
  root.className = 'sm-site-editor';

  const themeControls = document.createElement( 'div' );
  themeControls.id = 'customize-theme-controls';
  root.appendChild( themeControls );

  const menuEl = document.createElement( 'div' );
  menuEl.className = 'sm-se-menu';
  themeControls.appendChild( menuEl );

  const pagesEl = document.createElement( 'div' );
  pagesEl.className = 'sm-se-pages';
  themeControls.appendChild( pagesEl );

  // Show either the menu or the open section page (children live inside
  // their parent's page and don't count on their own).
  const updateView = () => {
    let anyOpen = false;
    api.section.each( sectionObject => {
      if ( ! sectionObject.parentSection && sectionObject.expanded.get() ) {
        anyOpen = true;
      }
    } );
    root.classList.toggle( 'is-section-open', anyOpen );
  };

  // Group sections by panel, keep panel order by priority, sections by priority.
  const orderedSections = [ ...sections ].sort( ( a, b ) => a.priority - b.priority );
  const groups = [];
  const groupsByPanel = {};

  orderedSections.forEach( section => {
    const panelId = section.panel || '';
    if ( ! groupsByPanel[ panelId ] ) {
      const panel = panels[ panelId ];
      groupsByPanel[ panelId ] = {
        id: panelId,
        title: panel ? panel.title : '',
        priority: panel ? panel.priority : 9999,
        sections: [],
      };
      groups.push( groupsByPanel[ panelId ] );

      if ( panel ) {
        api.panel.add( panelId, createContainerObject( panelId, { expanded: true } ) );
      }
    }
    groupsByPanel[ panelId ].sections.push( section );
  } );

  groups.sort( ( a, b ) => a.priority - b.priority );

  // Sections consumed as tabs inside a parent don't get their own menu row.
  const sectionsById = {};
  orderedSections.forEach( section => {
    sectionsById[ section.id ] = section;
  } );
  const childIds = new Set();
  Object.entries( payload.sectionTabs || {} ).forEach( ( [ parentId, tabs ] ) => {
    tabs.forEach( tab => {
      if ( tab.id !== parentId ) {
        childIds.add( tab.id );
      }
    } );
  } );

  groups.forEach( group => {
    const ownSections = group.sections.filter( section => ! childIds.has( section.id ) );

    if ( group.title && ownSections.length ) {
      const heading = document.createElement( 'div' );
      heading.className = 'sm-se-panel-title';
      heading.textContent = group.title;
      menuEl.appendChild( heading );
    }

    ownSections.forEach( section => {
      buildSectionElement( api, section, menuEl, pagesEl, updateView, sectionsById );
    } );
  } );

  // An always-visible entry point for the Live Site preview at the end of
  // the root menu — the View-menu item alone proved too hidden. A short
  // explainer plus a secondary button; the button toggles while open.
  const liveBlock = document.createElement( 'div' );
  liveBlock.className = 'sm-se-live-preview';
  liveBlock.innerHTML = `
    <p class="sm-se-live-preview__desc">${ wp.i18n.__( 'See your changes on the live site without leaving the editor.', '__plugin_txtd' ) }</p>
    <button type="button" class="sm-se-live-preview__button"></button>
  `;
  const liveButton = liveBlock.querySelector( '.sm-se-live-preview__button' );

  const renderLiveButton = currentMode => {
    const isOpen = 'site' === currentMode;
    liveButton.textContent = isOpen
      ? wp.i18n.__( 'Close live site preview', '__plugin_txtd' )
      : wp.i18n.__( 'Preview live site', '__plugin_txtd' );
    liveButton.classList.toggle( 'is-open', isOpen );
  };
  renderLiveButton( getPreviewMode() );
  previewModeListeners.add( renderLiveButton );

  liveButton.addEventListener( 'click', () => {
    setPreviewMode( 'site' === getPreviewMode() ? null : 'site' );
  } );
  menuEl.appendChild( liveBlock );

  return root;
};

/**
 * innerHTML does not execute <script> elements (e.g. the font palette
 * preview loader inside the preset control markup) — recreate them so they run.
 */
const executeInlineScripts = root => {
  Array.from( root.querySelectorAll( 'script' ) ).forEach( oldScript => {
    const newScript = document.createElement( 'script' );
    Array.from( oldScript.attributes ).forEach( attr => newScript.setAttribute( attr.name, attr.value ) );
    newScript.text = oldScript.text;
    oldScript.parentNode.replaceChild( newScript, oldScript );
  } );
};

const ensureEngine = () => {
  if ( engine ) {
    return engine;
  }

  ensureSmCustomizerAlias();

  const api = createCustomizeApi( payload.customizeSettings );

  // The whole point: the original engine modules talk to wp.customize.
  window.wp = window.wp || {};
  if ( ! window.wp.customize ) {
    window.wp.customize = api;
  }

  const root = buildRoot( api );

  engine = { api, root, booted: false, preview: null };

  return engine;
};

const bootEngine = eng => {
  if ( eng.booted ) {
    return;
  }
  eng.booted = true;

  const { api, root } = eng;

  executeInlineScripts( root );

  // The role customize-controls plays in the pane: link form elements to settings.
  api.linkElements( root );

  // Mirror the boot sequence of src/_js/customizer/index.js, minus the
  // Customizer chrome (reset buttons, feedback modal, search, resizer).
  globalService.loadSettings();

  const settings = globalService.getSettings();
  globalService.bindConnectedFields( Object.keys( settings ) );

  api.trigger( 'ready' );

  handleRangeFields();
  handleColorSelectFields();
  handleTabs();
  handlePresets();

  setTimeout( () => {
    handleFoldingFields();
  }, 100 );

  $( root ).find( '.style-manager_select2' ).select2();

  // Colors (same pieces initializeColors() wires in the Customizer, minus the
  // preview-iframe-only palettes overlay).
  initializePaletteBuilder( 'sm_advanced_palette_source', 'sm_advanced_palette_output' );
  api( 'sm_coloration_level', setting => {
    setting.bind( applyColorationValueToFields );
  } );

  initializeFonts();
  initializeFontPalettes();

  maybeLoadWebfontloaderScript();

  // Re-skin range/toggle/radio/select controls with native editor components
  // (see native-controls.js; the engine wiring stays on the hidden originals).
  mountNativeControls( eng, payload );

  eng.preview = initializePreview( api, payload );

  // Native Save (core-data entity) + server-evaluated control visibility.
  setupEntityIntegration( eng );
  initializeActiveStatesRefresher( eng );
  initializeControlDependencies( eng );
};

/**
 * The Customizer's preview tabs (Live Site / Typography / Colors) adapted to
 * the Site Editor: the tab bar sits in the editor top bar area and the
 * overlays (the original ColorsOverlay / TypographyOverlay components) cover
 * the canvas region. Mounted on document.body — the editor header and canvas
 * containers clip fixed descendants.
 */
/**
 * The frontend, previewed with the unsaved values through a draft changeset
 * (?customize_changeset_uuid=...) — core's own preview mechanics, the same
 * thing the Customizer preview does. This is where PHP-rendered options
 * (auto-styled titles, site frame, motion) can actually be seen live.
 */
const PREVIEW_CHANNEL = 'preview-0';

const LiveSiteOverlay = ( { show, hint } ) => {
  const { useState, useEffect, useRef } = wp.element;
  const [ url, setUrl ] = useState( null );
  const iframeRef = useRef( null );
  const uuidRef = useRef( null );
  const counterRef = useRef( 0 );
  const scrollRef = useRef( 0 );
  const currentPageRef = useRef( payload.homeUrl );

  // Send a message to the in-iframe customize-preview (core Messenger format).
  const send = ( id, data ) => {
    const frame = iframeRef.current;
    if ( frame && frame.contentWindow ) {
      frame.contentWindow.postMessage(
        JSON.stringify( { id, data, channel: PREVIEW_CHANNEL } ),
        window.location.origin
      );
    }
  };

  const buildUrl = base => {
    const u = new URL( base, payload.homeUrl );
    if ( uuidRef.current ) {
      u.searchParams.set( 'customize_changeset_uuid', uuidRef.current );
    }
    // Boots the frontend as a real customize-preview: SM's own preview
    // bundle loads inside and applies live (postMessage) settings instantly.
    u.searchParams.set( 'customize_messenger_channel', PREVIEW_CHANNEL );
    counterRef.current++;
    u.searchParams.set( 'sm-preview', String( counterRef.current ) );
    return u.toString();
  };

  // Sync the changeset, then (re)load the preview — used on open and for
  // refresh-transport settings, the same flow the Customizer uses.
  const refresh = () => {
    const changed = engine ? getChangedValues( engine ) : {};

    // Always go through the changeset (even when empty): the frontend only
    // boots as a customize-preview when a changeset uuid is present.
    wp.apiFetch( {
      path: payload.rest.previewChangesetPath,
      method: 'POST',
      data: { settings: changed, uuid: uuidRef.current },
    } ).then( response => {
      if ( response && response.uuid ) {
        uuidRef.current = response.uuid;
      }
      setUrl( buildUrl( currentPageRef.current ) );
    } ).catch( () => {
      setUrl( buildUrl( currentPageRef.current ) );
    } );
  };
  const debouncedRefresh = useRef( _.debounce( refresh, 1000 ) ).current;

  useEffect( () => {
    if ( ! show || ! engine ) {
      return undefined;
    }

    refresh();

    // Live vs refresh transport, exactly like the Customizer: postMessage
    // settings stream into the preview instantly; refresh settings update
    // the changeset and reload the iframe (scroll preserved).
    const onSettingChange = ( id, value ) => {
      const config = payload.customizeSettings.settings[ id ];
      if ( config && 'postMessage' === config.transport ) {
        send( 'setting', [ id, value ] );
      } else {
        debouncedRefresh();
      }
    };
    engine.api.bind( 'sm:setting-change', onSettingChange );

    const onMessage = event => {
      if ( event.origin !== window.location.origin || 'string' !== typeof event.data ) {
        return;
      }
      let message;
      try {
        message = JSON.parse( event.data );
      } catch ( e ) {
        return;
      }
      if ( ! message || message.channel !== PREVIEW_CHANNEL ) {
        return;
      }

      if ( 'ready' === message.id ) {
        send( 'active' );
        // Push the current unsaved live values on top of the changeset state.
        const changed = engine ? getChangedValues( engine ) : {};
        Object.entries( changed ).forEach( ( [ id, value ] ) => {
          const config = payload.customizeSettings.settings[ id ];
          if ( config && 'postMessage' === config.transport ) {
            send( 'setting', [ id, value ] );
          }
        } );
        if ( scrollRef.current ) {
          send( 'scroll', scrollRef.current );
        }
      } else if ( 'scroll' === message.id ) {
        scrollRef.current = message.data;
      } else if ( 'url' === message.id && 'string' === typeof message.data ) {
        // The preview intercepts navigation and delegates it to us.
        currentPageRef.current = message.data;
        scrollRef.current = 0;
        setUrl( buildUrl( message.data ) );
      }
    };
    window.addEventListener( 'message', onMessage );

    return () => {
      engine.api.unbind( 'sm:setting-change', onSettingChange );
      window.removeEventListener( 'message', onMessage );
    };
  }, [ show ] );

  if ( ! show ) {
    return null;
  }

  const { __ } = wp.i18n;
  // Replay: re-sync the changeset and bump the URL counter — the iframe
  // reloads through the same mechanics, replaying the loading transition
  // and the intro animations with the current (unsaved) motion settings.
  const replay = () => refresh();

  return (
    <div className="sm-live-site-overlay">
      { 'motion' === hint && (
        <div className="sm-live-site-overlay__hint">
          <span>
            { __( 'Navigate between pages to experience page transitions.', '__plugin_txtd' ) }
          </span>
          <button type="button" onClick={ replay }>
            { __( 'Replay intro', '__plugin_txtd' ) }
          </button>
        </div>
      ) }
      { url && (
        <iframe
          ref={ iframeRef }
          className="sm-live-site-overlay__iframe"
          src={ url }
          title="Live site preview"
        />
      ) }
    </div>
  );
};

/**
 * Preview mode store: null (editor) | 'site' | 'colors' | 'typography'.
 *
 * The triggers live in different React trees (the core View menu, the
 * body-mounted overlay host) and in vanilla-DOM section pages, so the mode
 * lives in a tiny external store everyone can reach.
 */
let previewMode = null;
let previewContext = null;
const previewModeListeners = new Set();

export const getPreviewMode = () => previewMode;

export const getPreviewContext = () => previewContext;

export const setPreviewMode = ( mode, context = null ) => {
  previewMode = mode || null;
  previewContext = previewMode ? context : null;
  previewModeListeners.forEach( listener => listener( previewMode ) );
};

const usePreviewMode = () => {
  const { useState, useEffect } = wp.element;
  const [ mode, setMode ] = useState( previewMode );

  useEffect( () => {
    previewModeListeners.add( setMode );
    return () => previewModeListeners.delete( setMode );
  }, [] );

  return mode;
};

/**
 * Sections that own a preview: their pages get a "Preview" affordance in the
 * page header (see buildSectionElement). Most open a system board; Motion is
 * intrinsically experiential, so its preview IS the Live Site flow with a
 * motion-specific hint (principle 6 in the section-preview principles doc).
 */
const SECTION_PREVIEW_MODES = {
  sm_color_palettes_section: { mode: 'colors' },
  sm_font_palettes_section: { mode: 'typography' },
  sm_spacing_section: { mode: 'spacing' },
  sm_motion_section: { mode: 'site', context: 'motion' },
};

/**
 * The overlay host, mounted on document.body (the editor header and canvas
 * containers clip fixed descendants). Renders only the active overlay plus a
 * "Back to editor" close affordance; nothing renders while in editor mode.
 */
const SiteEditorPreviewOverlays = () => {
  const { useEffect } = wp.element;
  const { __ } = wp.i18n;
  const mode = usePreviewMode();

  useEffect( () => {
    if ( ! mode ) {
      return undefined;
    }

    const onKeyDown = event => {
      if ( 'Escape' === event.key ) {
        setPreviewMode( null );
      }
    };
    document.addEventListener( 'keydown', onKeyDown );
    return () => document.removeEventListener( 'keydown', onKeyDown );
  }, [ mode ] );

  if ( ! mode ) {
    return null;
  }

  return (
    <div className="sm-preview sm-preview--visible">
      <button
        type="button"
        className="sm-preview__close"
        onClick={ () => setPreviewMode( null ) }
      >
        <span aria-hidden="true">✕</span> { __( 'Back to editor', '__plugin_txtd' ) }
      </button>
      <div className="sm-preview__content">
        { /* Mount only the active overlay: the hidden ones would render their
             full trees on editor boot and re-render on every palette change. */ }
        { 'site' === mode && <LiveSiteOverlay show hint={ getPreviewContext() } /> }
        { 'colors' === mode && <ColorsOverlay show /> }
        { 'typography' === mode && <TypographyOverlay show /> }
        { 'spacing' === mode && <SpacingOverlay show /> }
      </div>
    </div>
  );
};

const mountPreviewOverlays = () => {
  let rootEl = document.body.querySelector( ':scope > .sm-preview-tabs-root' );
  if ( ! rootEl ) {
    rootEl = document.createElement( 'div' );
    rootEl.className = 'sm-preview-tabs-root sm-preview-tabs-root--floating';
    document.body.appendChild( rootEl );
  }

  ReactDOM.render( <SiteEditorPreviewOverlays />, rootEl );

  return rootEl;
};

const unmountPreviewOverlays = rootEl => {
  setPreviewMode( null );
  if ( rootEl ) {
    ReactDOM.unmountComponentAtNode( rootEl );
    rootEl.remove();
  }
};

/**
 * Deep equality where the "no value" spellings count as the same thing:
 * PHP-saved font configs store `false`, the JS engine recomputes `null` or
 * `NaN` (parseFloat of false) for the same empty subfields.
 */
const isNoValue = v => false === v || null === v || undefined === v || ( 'number' === typeof v && isNaN( v ) );
// Checkboxes produce booleans where PHP stored '1' / '' / '0'.
const isTruthyBool = v => true === v || '1' === v;
const isFalsyBool = v => false === v || '' === v || '0' === v;

const isEquivalentValue = ( a, b ) => _.isEqualWith( a, b, ( x, y ) => {
  if ( isNoValue( x ) && isNoValue( y ) ) {
    return true;
  }
  if ( ( 'boolean' === typeof x || 'boolean' === typeof y ) && (
    ( isTruthyBool( x ) && isTruthyBool( y ) ) || ( isFalsyBool( x ) && isFalsyBool( y ) )
  ) ) {
    return true;
  }
  // Native range/number inputs emit numbers where PHP stored numeric strings.
  if ( ( 'number' === typeof x || 'number' === typeof y )
    && '' !== x && '' !== y && null !== x && null !== y
    && isFinite( Number( x ) ) && isFinite( Number( y ) )
    && Number( x ) === Number( y ) ) {
    return true;
  }
  return undefined;
} );

/**
 * The values that actually differ from what was loaded (or last saved).
 * A->B->A round-trips and boot-time churn must never publish anything.
 */
const getChangedValues = eng => {
  const baseline = payload.customizeSettings.settings;
  const changed = {};

  Object.entries( eng.api.dirtyValues() ).forEach( ( [ id, value ] ) => {
    if ( baseline[ id ] && isEquivalentValue( baseline[ id ].value, value ) ) {
      return;
    }
    changed[ id ] = value;
  } );

  return changed;
};

/**
 * --- Native Save integration ---
 *
 * Style Manager settings are registered as a core-data entity so the Site
 * Editor's own Save button and multi-entity save panel handle them, exactly
 * like templates and pages. Saving PUTs to our endpoint, which still
 * publishes a real Customizer changeset underneath.
 */
const ENTITY_KIND = 'pixelgrade/style-manager';
const ENTITY_NAME = 'settings';
const ENTITY_RECORD_ID = 'style-manager';

const onEntitySaved = eng => {
  const record = wp.data.select( 'core' ).getEntityRecord( ENTITY_KIND, ENTITY_NAME, ENTITY_RECORD_ID );

  if ( ! record || ! record.settings ) {
    return;
  }

  // The server's post-save values are the new comparison baseline.
  const baseline = payload.customizeSettings.settings;
  Object.entries( record.settings ).forEach( ( [ id, value ] ) => {
    if ( baseline[ id ] ) {
      baseline[ id ].value = value;
    }
  } );

  eng.api.markClean();
  eng.api.trigger( 'saved', record );

  if ( eng.preview && record.css ) {
    eng.preview.applySavedCSS( record.css );
  }
};

const setupEntityIntegration = eng => {
  const { dispatch, select, subscribe } = wp.data;
  const { __ } = wp.i18n;

  dispatch( 'core' ).addEntities( [
    {
      label: __( 'Style Manager', '__plugin_txtd' ),
      kind: ENTITY_KIND,
      name: ENTITY_NAME,
      baseURL: payload.rest.settingsPath,
      key: 'id',
      // The save panel resolves the record row label through this.
      getTitle: record => ( record && record.title ) || __( 'Design system settings', '__plugin_txtd' ),
    },
  ] );

  // Seed the persisted record from the localized baseline — no GET round-trip.
  const baselineSettings = {};
  Object.entries( payload.customizeSettings.settings ).forEach( ( [ id, data ] ) => {
    baselineSettings[ id ] = data.value;
  } );
  dispatch( 'core' ).receiveEntityRecords( ENTITY_KIND, ENTITY_NAME, [
    {
      id: ENTITY_RECORD_ID,
      title: __( 'Design system settings', '__plugin_txtd' ),
      settings: baselineSettings,
    },
  ] );

  // Mirror engine changes into entity edits: this is what enables the native
  // Save button and lists Style Manager in the save panel.
  const pushEdits = _.debounce( () => {
    const changed = getChangedValues( eng );

    if ( Object.keys( changed ).length ) {
      dispatch( 'core' ).editEntityRecord( ENTITY_KIND, ENTITY_NAME, ENTITY_RECORD_ID, { settings: changed } );
      return;
    }

    // Everything reverted: point the edit back at the persisted value so
    // core-data drops it and the Save button goes back to sleep.
    const record = select( 'core' ).getEntityRecord( ENTITY_KIND, ENTITY_NAME, ENTITY_RECORD_ID );
    if ( record ) {
      dispatch( 'core' ).editEntityRecord( ENTITY_KIND, ENTITY_NAME, ENTITY_RECORD_ID, { settings: record.settings } );
    }
  }, 250 );

  eng.api.bind( 'sm:setting-change', pushEdits );

  // React to the native Save finishing our record.
  let wasSaving = false;
  subscribe( () => {
    const saving = select( 'core' ).isSavingEntityRecord( ENTITY_KIND, ENTITY_NAME, ENTITY_RECORD_ID );

    if ( wasSaving && ! saving ) {
      const error = select( 'core' ).getLastEntitySaveError( ENTITY_KIND, ENTITY_NAME, ENTITY_RECORD_ID );
      if ( ! error ) {
        onEntitySaved( eng );
      }
    }

    wasSaving = saving;
  } );
};

/**
 * --- Active states refresher ---
 *
 * Control visibility driven by PHP active_callbacks (e.g. Site Frame's
 * Palette hidden while Style is None) is re-evaluated server-side with the
 * client's unsaved values previewed — the Customizer does the equivalent on
 * every preview refresh.
 */
const initializeActiveStatesRefresher = eng => {
  let inFlight = false;
  let pendingAgain = false;

  // The server returns only allowlisted option-driven classes; we strip all
  // classes carrying those prefixes from the canvas body and apply the fresh
  // set, so the canvas reflects both the saved and the previewed state.
  let latestBodyClasses = null;
  let latestPrefixes = [];

  const applyActiveStates = states => {
    Object.entries( states ).forEach( ( [ controlId, isActive ] ) => {
      const li = eng.root.querySelector( `#${ CSS.escape( getControlContainerId( controlId ) ) }` );
      if ( li ) {
        li.classList.toggle( 'sm-se-control-inactive', ! isActive );
      }
    } );
  };

  const applyBodyClasses = () => {
    if ( ! latestBodyClasses ) {
      return;
    }

    const iframe = document.querySelector( 'iframe[name="editor-canvas"]' );
    const body = iframe && iframe.contentDocument ? iframe.contentDocument.body : null;
    if ( ! body ) {
      return;
    }

    Array.from( body.classList ).forEach( cls => {
      if ( latestPrefixes.some( prefix => cls.startsWith( prefix ) ) && ! latestBodyClasses.includes( cls ) ) {
        body.classList.remove( cls );
      }
    } );
    latestBodyClasses.forEach( cls => body.classList.add( cls ) );
  };

  const refresh = () => {
    if ( inFlight ) {
      pendingAgain = true;
      return;
    }
    inFlight = true;

    wp.apiFetch( {
      path: payload.rest.activeStatesPath,
      method: 'POST',
      data: { settings: getChangedValues( eng ) },
    } ).then( response => {
      if ( response && response.activeStates ) {
        applyActiveStates( response.activeStates );
      }
      if ( response && Array.isArray( response.bodyClasses ) ) {
        latestBodyClasses = response.bodyClasses;
        latestPrefixes = Array.isArray( response.bodyClassPrefixes ) ? response.bodyClassPrefixes : [];
        applyBodyClasses();
      }
    } ).catch( () => {
      // Visibility refresh is best-effort; never break the editing flow.
    } ).finally( () => {
      inFlight = false;
      if ( pendingAgain ) {
        pendingAgain = false;
        refresh();
      }
    } );
  };

  eng.api.bind( 'sm:setting-change', _.debounce( refresh, 400 ) );

  // Fetch the saved-state classes right away (the editor canvas does not run
  // the theme's body_class filter on its own), and re-apply them whenever the
  // canvas iframe gets torn down and recreated.
  refresh();
  if ( window.MutationObserver ) {
    const observer = new MutationObserver( _.debounce( () => {
      const iframe = document.querySelector( 'iframe[name="editor-canvas"]' );
      if ( iframe && ! iframe.hasAttribute( 'data-sm-body-classes-bound' ) ) {
        iframe.setAttribute( 'data-sm-body-classes-bound', '1' );
        iframe.addEventListener( 'load', applyBodyClasses );
      }
      applyBodyClasses();
    }, 300 ) );
    observer.observe( document.body, { childList: true, subtree: true } );
  }
};

/**
 * Toggle-driven control visibility (e.g. Page Transition Style only shows
 * while Enable Page Transitions is on) — the Customizer gets this behavior
 * from theme-shipped JS; here it is declarative via the payload map.
 */
const initializeControlDependencies = eng => {
  const isTruthy = v => true === v || 1 === v || '1' === v || 'true' === v;

  Object.entries( payload.controlDependencies || {} ).forEach( ( [ enableId, dependents ] ) => {
    eng.api( enableId, setting => {
      const apply = value => {
        const hidden = ! isTruthy( value );
        dependents.forEach( depId => {
          const li = eng.root.querySelector( `#${ CSS.escape( getControlContainerId( `${ depId }_control` ) ) }` );
          if ( li ) {
            li.classList.toggle( 'sm-se-control-dependent-hidden', hidden );
          }
        } );
      };
      apply( setting() );
      setting.bind( apply );
    } );
  } );
};

const registerSidebar = () => {
  const { registerPlugin } = wp.plugins;
  const { PluginSidebar, PluginSidebarMoreMenuItem, PluginPreviewMenuItem } = wp.editor;
  const { useEffect, useRef, createElement, Fragment } = wp.element;
  const { __ } = wp.i18n;

  const StyleManagerSidebarContent = () => {
    const containerRef = useRef( null );

    useEffect( () => {
      const eng = ensureEngine();

      containerRef.current.appendChild( eng.root );
      bootEngine( eng );

      const previewOverlaysRoot = mountPreviewOverlays();

      return () => {
        unmountPreviewOverlays( previewOverlaysRoot );
        if ( eng.root.parentNode ) {
          eng.root.parentNode.removeChild( eng.root );
        }
      };
    }, [] );

    return (
      <div className="sm-site-editor-sidebar">
        <div ref={ containerRef } className="sm-site-editor-sidebar__body" />
      </div>
    );
  };

  registerPlugin( 'pixelgrade-style-manager', {
    icon: 'admin-customizer',
    render: () => (
      <Fragment>
        <PluginSidebarMoreMenuItem target="pixelgrade-style-manager" icon="admin-customizer">
          { __( 'Style Manager', '__plugin_txtd' ) }
        </PluginSidebarMoreMenuItem>
        { /* "Live site" is a view mode, so it lives in the core View menu
             next to the device previews. The section-scoped previews
             (Colors / Typography) open from their own section pages. */ }
        { PluginPreviewMenuItem && (
          <PluginPreviewMenuItem
            onClick={ () => setPreviewMode( 'site' ) }
          >
            { window.styleManager?.l10n?.colorPalettes?.previewTabLiveSiteLabel || __( 'Live site', '__plugin_txtd' ) }
          </PluginPreviewMenuItem>
        ) }
        <PluginSidebar
          name="pixelgrade-style-manager"
          title={ __( 'Style Manager', '__plugin_txtd' ) }
          icon="admin-customizer"
          className="sm-site-editor-sidebar-area"
        >
          <StyleManagerSidebarContent />
        </PluginSidebar>
      </Fragment>
    ),
  } );
};

/**
 * Deep links: site-editor.php?canvas=edit&sm-sidebar=1[&sm-section=<id>]
 * opens the Style Manager sidebar (and focuses a section). Used by the
 * Styles-route handoff card.
 */
const handleDeepLink = () => {
  const params = new URLSearchParams( window.location.search );
  const targetSection = params.get( 'sm-section' );

  if ( ! params.get( 'sm-sidebar' ) && ! targetSection ) {
    return;
  }

  // Give the editor a moment to settle before opening the sidebar, then keep
  // retrying until the engine has booted (slow loads mount the sidebar late).
  const openSidebar = () => {
    wp.data.dispatch( 'core/interface' ).enableComplementaryArea( 'core', 'pixelgrade-style-manager/pixelgrade-style-manager' );
  };

  const tryFocus = attempts => {
    if ( engine && engine.booted && engine.api.section( targetSection ) ) {
      engine.api.section( targetSection, section => section.focus() );
      return;
    }
    if ( attempts > 0 ) {
      openSidebar();
      setTimeout( () => tryFocus( attempts - 1 ), 500 );
    }
  };

  setTimeout( () => {
    openSidebar();
    if ( targetSection ) {
      setTimeout( () => tryFocus( 30 ), 500 );
    }
  }, 1000 );
};

if ( payload && window.styleManager && wp && wp.plugins && wp.editor && wp.editor.PluginSidebar ) {
  registerSidebar();
  wp.domReady( handleDeepLink );
} else if ( payload ) {
  console.warn( 'Style Manager: the Site Editor PluginSidebar API is not available; the Style Manager sidebar was not registered.' );
}
