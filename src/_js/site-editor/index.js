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
import { ColorsOverlay, TypographyOverlay } from '../customizer/components';
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
 * Build one section: a menu row (Nova Blocks-style drill-down trigger) plus a
 * full-panel page with a back header holding the section's controls.
 */
const buildSectionElement = ( api, section, menuEl, pagesEl, updateView ) => {
  // The drill-down trigger row in the menu.
  const rowEl = document.createElement( 'button' );
  rowEl.type = 'button';
  rowEl.className = 'sm-se-row';
  rowEl.innerHTML = `<span class="sm-se-row__label">${ section.title }</span><span class="sm-se-row__chevron" aria-hidden="true"></span>`;
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

  if ( section.description ) {
    const descriptionEl = document.createElement( 'div' );
    descriptionEl.className = 'sm-se-page__description customize-control-description';
    descriptionEl.innerHTML = section.description;
    pageEl.appendChild( descriptionEl );
  }

  const contentEl = document.createElement( 'ul' );
  // Note: deliberately NOT using the `accordion-section-content` class here —
  // wp-admin's generic accordion styles (common.css) would hide it outside
  // the Customizer.
  contentEl.className = 'sm-se-page__content customize-pane-child';
  contentEl.id = `sub-accordion-section-${ section.id }`;
  contentEl.innerHTML = section.controls.map( control => control.html ).join( '' );
  pageEl.appendChild( contentEl );

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

  pagesEl.appendChild( pageEl );

  // Register a section object so the engine's section-based features work
  // (shortcuts focus, expanded bindings, voice tuner placement, etc.).
  const sectionObject = createContainerObject( section.id, {
    container: $( pageEl ),
    expanded: false,
    onFocus: () => {
      const body = document.querySelector( '.sm-site-editor-sidebar__body' );
      if ( body ) {
        body.scrollTop = 0;
      }
    },
  } );

  sectionObject.expanded.bind( isExpanded => {
    pageEl.classList.toggle( 'open', !! isExpanded );

    // One section page at a time — same model as the Customizer's slide-in
    // sections and the Site Editor's Global Styles drill-down.
    if ( isExpanded ) {
      api.section.each( other => {
        if ( other !== sectionObject && other.expanded.get() ) {
          other.expanded.set( false );
        }
      } );
    }

    updateView();
  } );

  rowEl.addEventListener( 'click', () => {
    sectionObject.focus();
  } );

  headerEl.querySelector( '.sm-se-page__back' ).addEventListener( 'click', () => {
    sectionObject.expanded.set( false );
  } );

  api.section.add( section.id, sectionObject );
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

  // Show either the menu or the open section page.
  const updateView = () => {
    let anyOpen = false;
    api.section.each( sectionObject => {
      if ( sectionObject.expanded.get() ) {
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

  groups.forEach( group => {
    if ( group.title && group.sections.length ) {
      const heading = document.createElement( 'div' );
      heading.className = 'sm-se-panel-title';
      heading.textContent = group.title;
      menuEl.appendChild( heading );
    }

    group.sections.forEach( section => {
      buildSectionElement( api, section, menuEl, pagesEl, updateView );
    } );
  } );

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

  eng.preview = initializePreview( api, payload );

  // Native Save (core-data entity) + server-evaluated control visibility.
  setupEntityIntegration( eng );
  initializeActiveStatesRefresher( eng );
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
const LiveSiteOverlay = ( { show } ) => {
  const { useState, useEffect, useRef } = wp.element;
  const [ url, setUrl ] = useState( null );
  const uuidRef = useRef( null );
  const counterRef = useRef( 0 );

  const refresh = () => {
    const changed = engine ? getChangedValues( engine ) : {};
    counterRef.current++;

    if ( ! Object.keys( changed ).length ) {
      const plain = new URL( payload.homeUrl );
      plain.searchParams.set( 'sm-preview', String( counterRef.current ) );
      setUrl( plain.toString() );
      return;
    }

    wp.apiFetch( {
      path: payload.rest.previewChangesetPath,
      method: 'POST',
      data: { settings: changed, uuid: uuidRef.current },
    } ).then( response => {
      if ( response && response.previewUrl ) {
        uuidRef.current = response.uuid;
        const previewUrl = new URL( response.previewUrl );
        previewUrl.searchParams.set( 'sm-preview', String( counterRef.current ) );
        setUrl( previewUrl.toString() );
      }
    } ).catch( () => {
      setUrl( payload.homeUrl );
    } );
  };

  useEffect( () => {
    if ( ! show || ! engine ) {
      return undefined;
    }

    refresh();

    const handler = _.debounce( refresh, 1500 );
    engine.api.bind( 'sm:setting-change', handler );

    return () => {
      engine.api.unbind( 'sm:setting-change', handler );
    };
  }, [ show ] );

  if ( ! show ) {
    return null;
  }

  return (
    <div className="sm-live-site-overlay">
      { url && (
        <iframe
          className="sm-live-site-overlay__iframe"
          src={ url }
          title="Live site preview"
        />
      ) }
    </div>
  );
};

const SiteEditorPreviewTabs = () => {
  const { useState } = wp.element;
  const { __ } = wp.i18n;
  const [ active, setActive ] = useState( 'editor' );

  const l10n = window.styleManager.l10n.colorPalettes;
  const tabs = [
    { id: 'editor', label: __( 'Editor', '__plugin_txtd' ) },
    { id: 'site', label: l10n.previewTabLiveSiteLabel },
    { id: 'typography', label: l10n.previewTabTypographyLabel },
    { id: 'colors', label: l10n.previewTabColorSystemLabel },
  ];

  return (
    <div className="sm-preview sm-preview--visible">
      <div className="sm-preview__header">
        <div className="sm-preview__tabs">
          { tabs.map( tab => (
            <div
              key={ tab.id }
              className={ `sm-preview__tab ${ active === tab.id ? 'sm-preview__tab--active' : '' }` }
              onClick={ () => setActive( tab.id ) }
            >
              { tab.label }
            </div>
          ) ) }
        </div>
      </div>
      <div className="sm-preview__content">
        <LiveSiteOverlay show={ active === 'site' } />
        <ColorsOverlay show={ active === 'colors' } />
        <TypographyOverlay show={ active === 'typography' } />
      </div>
    </div>
  );
};

const mountPreviewTabs = () => {
  let rootEl = document.body.querySelector( ':scope > .sm-preview-tabs-root' );
  if ( ! rootEl ) {
    rootEl = document.createElement( 'div' );
    rootEl.className = 'sm-preview-tabs-root sm-preview-tabs-root--floating';
    document.body.appendChild( rootEl );
  }

  ReactDOM.render( <SiteEditorPreviewTabs />, rootEl );

  return rootEl;
};

const unmountPreviewTabs = rootEl => {
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

const registerSidebar = () => {
  const { registerPlugin } = wp.plugins;
  const { PluginSidebar, PluginSidebarMoreMenuItem } = wp.editor;
  const { useEffect, useRef, createElement, Fragment } = wp.element;
  const { __ } = wp.i18n;

  const StyleManagerSidebarContent = () => {
    const containerRef = useRef( null );

    useEffect( () => {
      const eng = ensureEngine();

      containerRef.current.appendChild( eng.root );
      bootEngine( eng );

      const previewTabsRoot = mountPreviewTabs();

      return () => {
        unmountPreviewTabs( previewTabsRoot );
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
