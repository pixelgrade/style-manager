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
import { SECTION_PREVIEW_MODES, parseSiteEditorDeepLink } from './deep-link';
import { initDocsLinks } from './docs-links';
import { initializePreview } from './preview';
import { mountNativeControls, getResettableSettings, PanelResetMenu, VoiceTunerPanel } from './native-controls';
import { mountTryAndPlay } from './try-and-play';
import { plusUpsellUrl } from './upsell-url';
import {
  clearNativeSaveEdits,
  filterLockedPlusChangedValues,
  getBaselineSettingsValues,
  getChangedSettingsValues,
  isTruthyBool,
} from './plus-save-filter';
import { initPlusSaveAffordance } from './plus-save-affordance';
import { initializeColorTargetFeedback } from './target-feedback';
import { playSiteEditorMotionPreview } from './motion-preview';
import { registerSiteTitleFontShortcut } from './site-title-font-shortcut';
import {
  getPreviewContext,
  getPreviewMode,
  getPreviewState,
  isPreviewEntryOpen,
  setPreviewMode,
  subscribePreviewMode,
} from './preview-mode';
import {
  clearOverlayRender,
  getExitMs,
  getIntroSettleMs,
  getSwitchSettleMs,
  resolveOverlayRender,
  schedulePreviewAutoOpen,
  settleOverlayRender,
} from './preview-motion';
import { ColorsOverlay, TypographyOverlay, SpacingOverlay, SiteFrameOverlay, FancyTitlesOverlay, ContextualColorsOverlay } from '../customizer/components';
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
import { setNativeFontUI } from '../customizer/fonts/native-ui';
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

// The Site Editor always skins the font fields with native editor components:
// flip the engine into native-font mode before anything touches the selects.
setNativeFontUI( true );

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
let parkedEngineHost = null;

/**
 * The container <li> id WP renders for a control id (brackets become dashes).
 */
const getControlContainerId = controlId => `customize-control-${ controlId.replace( /\[/g, '-' ).replace( /\]/g, '' ) }`;

const decodeHtmlText = text => {
  const decoder = document.createElement( 'textarea' );
  decoder.innerHTML = text || '';
  return decoder.value;
};

/**
 * The Pixelgrade Plus gating payload, if any.
 *
 * Free users get a fully interactive live trial of the gated controls; only saving the premium
 * palette-structure settings is gated (server-side). The metadata here drives gentle inline trial
 * affordances — never a wall, never disabled controls.
 */
const plusPayload = payload.plus || null;

const hydrateLockedFontPaletteIds = root => {
  if ( ! plusPayload || ! plusPayload.locked || ! root ) {
    return;
  }

  const lockedIds = Array.from( root.querySelectorAll( '.is-plus-locked input[type="radio"]' ) )
    .map( input => input.value )
    .filter( Boolean );

  if ( ! lockedIds.length ) {
    return;
  }

  plusPayload.fontPalettes = {
    ...( plusPayload.fontPalettes || {} ),
    lockedIds,
  };
};

/**
 * Whether the Plus advanced controls are presented as a locked live trial.
 */
const plusIsLocked = () => !! ( plusPayload && plusPayload.locked );

/**
 * Resolve the Try & Play trial options for a locked Plus section from the `plus` payload.
 *
 * Controls stay interactive; the overlay (and, post-reveal, the slim banner) just frames the
 * experience: you're trying it live, saving the palette structure is part of Pixelgrade Plus.
 * Returns null when this section isn't a locked, copy-bearing Plus surface.
 */
const getPlusTrialOptions = sectionId => {
  if ( ! plusIsLocked() || ! plusPayload ) {
    return null;
  }

  // The overlay line: a dedicated short invite, falling back to the section's note.
  const overlayText = ( plusPayload.overlayNotes || {} )[ sectionId ]
    || ( plusPayload.notes || {} )[ sectionId ];
  if ( ! overlayText ) {
    return null;
  }

  return {
    id: sectionId,
    locked: true,
    overlayText,
    buttonLabel: plusPayload.buttonLabel || 'Try and play',
    bannerText: plusPayload.bannerText || '',
    badge: plusPayload.badge || 'Plus',
    learnMore: plusPayload.upsellUrl
      ? { label: plusPayload.learnMore || 'Learn more', url: plusUpsellUrl( plusPayload, { content: sectionId } ) }
      : null,
  };
};

/**
 * Add a subtle Plus pill + gentle tooltip to a locked Plus control's container, keeping it
 * interactive. No-op when unlocked, when there's no copy, or when the control isn't present in this
 * context (e.g. the colorize-elements affordance, which doesn't render as a control in the Site
 * Editor — its per-element targets render inline instead).
 */
const decoratePlusControl = ( contentEl, control ) => {
  if ( ! plusIsLocked() || ! plusPayload || ! control || ! control.id ) {
    return;
  }

  const li = contentEl.querySelector( `#${ CSS.escape( getControlContainerId( control.id ) ) }` );
  if ( ! li || li.querySelector( '.sm-se-plus-pill' ) ) {
    return;
  }

  // Resolve copy by either the literal control id or its bare setting id.
  const settingId = control.id.replace( /_control$/, '' ).replace( /^.*\[([^\]]+)\]$/, '$1' );
  const note = ( plusPayload.notes || {} )[ control.id ] || ( plusPayload.notes || {} )[ settingId ];

  const pill = document.createElement( 'span' );
  pill.className = 'sm-se-plus-pill';
  pill.textContent = plusPayload.badge || 'Plus';
  if ( note ) {
    pill.title = decodeHtmlText( note );
  }

  li.classList.add( 'sm-se-has-plus-pill' );

  const title = li.querySelector( '.customize-control-title' );
  if ( title ) {
    title.appendChild( pill );
  } else {
    li.insertBefore( pill, li.firstChild );
  }
};

const getGroupItems = groupStartEl => {
  const items = [];
  let item = groupStartEl.nextElementSibling;

  while ( item && ! item.classList.contains( 'sm-se-group-start' ) ) {
    items.push( item );
    item = item.nextElementSibling;
  }

  return items;
};

let groupPanelIndex = 0;

const setupCollapsibleGroup = ( groupStartEl, marker ) => {
  if ( ! marker.collapsible ) {
    return;
  }

  const titleEl = groupStartEl.querySelector( '.sm-se-section-head__title' );
  if ( ! titleEl ) {
    return;
  }

  const label = titleEl.textContent.trim() || decodeHtmlText( marker.label );
  const contentId = `sm-se-group-panel-content-${ ++groupPanelIndex }`;
  const items = getGroupItems( groupStartEl );
  const title = document.createElement( 'h2' );
  const toggle = document.createElement( 'button' );
  const icon = document.createElement( 'span' );
  const labelEl = document.createElement( 'span' );
  const content = document.createElement( 'ul' );
  const previousItem = groupStartEl.previousElementSibling;

  if ( previousItem ) {
    previousItem.classList.add( 'sm-se-before-group-panel' );
  }

  groupStartEl.className = 'customize-control sm-se-group-panel sm-se-group-start components-panel__body';
  if ( marker.group ) {
    groupStartEl.dataset.smGroup = marker.group;
  }
  title.className = 'components-panel__body-title sm-se-group-panel__title';
  title.style.setProperty( 'width', '100%', 'important' );
  title.style.setProperty( 'margin', '0', 'important' );
  toggle.type = 'button';
  toggle.className = 'components-button components-panel__body-toggle sm-se-group-panel__toggle';
  toggle.style.setProperty( 'width', '100%', 'important' );
  toggle.style.setProperty( 'margin-inline', '0', 'important' );
  toggle.setAttribute( 'aria-controls', contentId );
  icon.className = 'components-panel__arrow sm-se-group-panel__arrow';
  icon.setAttribute( 'aria-hidden', 'true' );
  icon.innerHTML = '<svg viewBox="0 0 24 24" width="24" height="24" focusable="false" aria-hidden="true"><path d="M12.5 15.7 7.8 11l1.1-1.1 3.6 3.6 3.6-3.6 1.1 1.1z"></path></svg>';
  labelEl.className = 'sm-se-group-panel__label';
  labelEl.textContent = label;
  toggle.appendChild( labelEl );
  toggle.appendChild( icon );
  title.appendChild( toggle );

  content.id = contentId;
  content.className = 'sm-se-group-panel__content';
  items.forEach( item => {
    item.hidden = false;
    content.appendChild( item );
  } );

  groupStartEl.replaceChildren( title, content );

  const setExpanded = isExpanded => {
    toggle.setAttribute( 'aria-expanded', isExpanded ? 'true' : 'false' );
    groupStartEl.classList.toggle( 'is-opened', isExpanded );
    content.hidden = ! isExpanded;
    groupStartEl.dispatchEvent( new CustomEvent( 'sm:site-editor-group-toggled', {
      bubbles: true,
      detail: {
        group: marker.group || '',
        expanded: isExpanded,
      },
    } ) );
  };

  toggle.addEventListener( 'click', event => {
    event.preventDefault();
    setExpanded( 'true' !== toggle.getAttribute( 'aria-expanded' ) );
  } );

  setExpanded( !! marker.defaultOpen );
};

/**
 * Mark up a section's group structure, per the payload group markers. Each
 * marker starts a group; the groups are separated by a divider (not boxed) and
 * each keeps a section title. A `label` marker injects a title row (for groups
 * with no html intro of their own, e.g. Collections); otherwise the marker's
 * html intro IS the section title — it is flagged `sm-se-group-head` so the
 * native-control merge leaves it standing as a header instead of folding it
 * into a following toggle (native-controls.js). A `preview` marker rides the
 * section title's line, docked right. A `collapsible` marker turns the group
 * into a core PanelBody-style section and moves its items into the panel body.
 *
 * Runs while building the panel, before the merge; both resolve controls by id.
 */
const markGroupSections = ( contentEl, markers ) => {
  const collapsibleGroups = [];

  markers.forEach( marker => {
    const anchorLi = contentEl.querySelector( `#${ CSS.escape( getControlContainerId( `${ marker.before }_control` ) ) }` );
    if ( ! anchorLi ) {
      return;
    }

    let groupStartEl;
    let titleEl;

    if ( marker.label ) {
      const heading = document.createElement( 'li' );
      heading.className = 'customize-control sm-se-group-title';
      // Labels arrive esc_html'd from PHP (e.g. "Contrast &amp; balance").
      heading.textContent = decodeHtmlText( marker.label );
      contentEl.insertBefore( heading, anchorLi );
      groupStartEl = heading;
      titleEl = heading;
    } else {
      // The anchor is an html intro that titles the section — keep it from
      // being folded into a following toggle so it stays a section header.
      anchorLi.classList.add( 'sm-se-group-head' );
      groupStartEl = anchorLi;
      titleEl = anchorLi.querySelector( '.customize-control-title' ) || anchorLi;
    }

    groupStartEl.classList.add( 'sm-se-group-start' );

    if ( ! titleEl ) {
      return;
    }

    // Lay the title row out as a flexing title + a right-aligned actions area
    // (the Preview button and, relocated by native-controls, a master on/off
    // switch). The title text is wrapped so it can flex and wrap cleanly beside
    // the actions, and so readers of the title (the switch's accessible name)
    // don't pick up the actions' text.
    titleEl.classList.add( 'sm-se-section-head__row' );

    const titleSpan = document.createElement( 'span' );
    titleSpan.className = 'sm-se-section-head__title';
    while ( titleEl.firstChild ) {
      titleSpan.appendChild( titleEl.firstChild );
    }
    titleEl.appendChild( titleSpan );

    const actions = document.createElement( 'span' );
    actions.className = 'sm-se-section-head__actions';
    titleEl.appendChild( actions );

    // A header that leads two or more sibling toggles (e.g. "Color promotion"
    // → Pure white + Pure black) titles a group; it is NOT a single feature's
    // master switch, so it stays a plain header and each toggle keeps its label.
    const nextLi = anchorLi.nextElementSibling;
    const secondLi = nextLi && nextLi.nextElementSibling;
    const headsToggleGroup = !! nextLi
      && nextLi.classList.contains( 'customize-control-sm_toggle' )
      && !! secondLi
      && secondLi.classList.contains( 'customize-control-sm_toggle' );

    // A section gated by a master toggle (a single following sm_toggle, or a
    // mapped setting like Site Frame's style via marker.toggle) has its switch
    // relocated to this row; its Preview shows only while the feature is on.
    const hasMasterToggle = ! marker.label && ! headsToggleGroup && (
      !! marker.toggle
      || ( !! nextLi && nextLi.classList.contains( 'customize-control-sm_toggle' ) )
    );

    if ( marker.preview && marker.preview.mode ) {
      const previewBtn = createPreviewToggleButton( marker.preview, 'sm-se-group__preview' );
      if ( hasMasterToggle ) {
        // Hidden until native-controls binds it to the toggle (avoids a flash).
        previewBtn.classList.add( 'sm-se-group__preview--conditional' );
        previewBtn.style.display = 'none';
      }
      actions.appendChild( previewBtn );
    }

    if ( marker.collapsible ) {
      collapsibleGroups.push( { groupStartEl, marker } );
    }
  } );

  collapsibleGroups.forEach( ( { groupStartEl, marker } ) => {
    setupCollapsibleGroup( groupStartEl, marker );
  } );
};

/**
 * Build a tab panel (description + controls list) for a section's data.
 */
const buildSectionPanel = section => {
  const panel = document.createElement( 'div' );
  panel.className = 'sm-se-tab-panel';
  panel.setAttribute( 'data-tab-section', section.id );

  // A locked Plus section becomes a live trial. Resolve its Try & Play options here; the overlay
  // is mounted over the controls list below (it needs the built controls to exist first). Saving
  // the premium settings is gated server-side regardless.
  const trialOptions = ( section.locked && section.gate ) ? getPlusTrialOptions( section.id ) : null;

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

    // A locked Plus control keeps working — it just gets a subtle Plus pill + gentle tooltip.
    if ( control.locked && control.gate ) {
      decoratePlusControl( contentEl, control );
    }
  } );

  // Mark the controls into titled, divider-separated group sections.
  markGroupSections( contentEl, ( payload.sectionGroupHeaders || {} )[ section.id ] || [] );

  // A locked Plus section: drape a soft Try & Play overlay over the controls list (visible but not
  // interactive underneath) until the user opts to try them live, then reveal + leave a slim
  // persistent trial banner. Per-session dismissal is keyed by the section id. We wrap the controls
  // <ul> in a positioned host so the scrim covers ONLY the controls (the description/tools stay
  // clear) and so the slim banner — a <div> — is a valid sibling, not a child of the <ul>.
  if ( trialOptions ) {
    const trialHost = document.createElement( 'div' );
    trialHost.className = 'sm-se-trial-host';
    contentEl.parentNode.insertBefore( trialHost, contentEl );
    trialHost.appendChild( contentEl );
    // A gated tab inherits its PARENT section's live Preview (Fine-tune/Usage →
    // the color preview; the Typography tabs → typography). Auto-open it when a
    // locked user lands on the tab so the trial's changes are actually visible.
    const tabsMap = payload.sectionTabs || {};
    const parentId = Object.keys( tabsMap ).find(
      pid => ( tabsMap[ pid ] || [] ).some( t => t.id === section.id )
    );
    const previewEntry = SECTION_PREVIEW_MODES[ section.id ]
      || ( parentId ? SECTION_PREVIEW_MODES[ parentId ] : null );
    mountTryAndPlay( trialHost, {
      ...trialOptions,
      controlsEl: contentEl,
      onActivate: previewEntry
        // Debounced so the revealed controls paint first and the board's
        // entrance reads as a response, not a flash over the navigation.
        ? () => schedulePreviewAutoOpen( () => setPreviewMode( previewEntry.mode, previewEntry.context || null ) )
        : null,
    } );
  }

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

    // The pro (premium-licensed) font palettes are a gated-but-functional group,
    // exactly like the Fine-tune/Usage tabs: wrap them in a Try & Play host so a
    // free user can reveal them and take any for a spin live (selection applies
    // client-side), while saving a premium pick stays part of Plus (enforced
    // server-side). The reveal leaves the same slim trial banner + the diagonal
    // "sandbox" texture the tabs use. Present only when locked — PHP tags the
    // pro cards `is-plus-locked` exactly when the site isn't entitled.
    const paletteList = contentEl.querySelector( '.js-font-palette, .customize-control-font-palette' );
    const lockedCards = paletteList ? Array.from( paletteList.querySelectorAll( '.is-plus-locked' ) ) : [];
    if ( paletteList && lockedCards.length ) {
      const fpCopy = ( plusPayload && plusPayload.fontPalettes ) || {};
      // Two nested elements, mirroring the tabs' trial host: the OUTER host
      // carries the scrim + button (stays interactive), the INNER wrapper holds
      // the cards and is the one made `inert` while covered. Passing the host
      // itself as controlsEl would make the scrim's own button inert — visible
      // but unclickable — so the cards get their own wrapper.
      const proHost = document.createElement( 'div' );
      proHost.className = 'sm-plus-palette-group';
      const proCards = document.createElement( 'div' );
      proCards.className = 'sm-plus-palette-cards';
      proHost.appendChild( proCards );
      // Insert the host where the first pro card is, then move the whole pro
      // group into the inner wrapper. The cards stay descendants of
      // `.js-font-palette`, so the selection handler still binds them; the voice
      // tuner only sorts the free direct-child cards and pins this group last.
      paletteList.insertBefore( proHost, lockedCards[ 0 ] );
      lockedCards.forEach( card => proCards.appendChild( card ) );

      mountTryAndPlay( proHost, {
        id: 'sm_font_palettes_plus',
        locked: true,
        overlayText: fpCopy.overlayText || '',
        buttonLabel: fpCopy.buttonLabel || ( plusPayload && plusPayload.buttonLabel ) || 'Try and play',
        bannerText: fpCopy.bannerText || ( plusPayload && plusPayload.bannerText ) || '',
        badge: ( plusPayload && plusPayload.badge ) || 'Plus',
        learnMore: ( plusPayload && plusPayload.upsellUrl )
          ? { label: ( plusPayload && plusPayload.learnMore ) || 'Learn more', url: plusUpsellUrl( plusPayload, { content: 'sm_font_palettes_plus' } ) }
          : null,
        controlsEl: proCards,
      } );
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
    headerEl.appendChild(
      createPreviewToggleButton( SECTION_PREVIEW_MODES[ section.id ], 'sm-se-page__preview' )
    );
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
    pageEl.dispatchEvent( new CustomEvent( 'sm:site-editor-tab-activated', {
      bubbles: true,
      detail: { id },
    } ) );
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

  const renderLiveButton = state => {
    const isOpen = 'site' === state.mode;
    liveButton.textContent = isOpen
      ? wp.i18n.__( 'Close live site preview', '__plugin_txtd' )
      : wp.i18n.__( 'Preview live site', '__plugin_txtd' );
    liveButton.classList.toggle( 'is-open', isOpen );
  };
  subscribePreviewMode( renderLiveButton );

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
  hydrateLockedFontPaletteIds( root );

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
  eng.targetFeedback = initializeColorTargetFeedback( root, payload, { customize: api } );

  // Native Save (core-data entity) + server-evaluated control visibility.
  setupEntityIntegration( eng );

  // "Save · Plus" affordance: gives gated-only / mixed Plus-trial changes a
  // voice on the native Save button without ever persisting them. Inert unless
  // the trial is locked (licensed users keep 100% native behaviour).
  initPlusSaveAffordance( {
    engine: eng,
    plusPayload,
    getChanges: () => getChangedValues( eng ),
    entity: { kind: ENTITY_KIND, name: ENTITY_NAME, recordId: ENTITY_RECORD_ID },
  } );

  initializeActiveStatesRefresher( eng );
  initializeControlDependencies( eng );
};

/**
 * Keep the initialized legacy controls mounted while the Style Manager
 * sidebar is closed. The Site Title inspector can therefore use the exact
 * same setting/change pipeline without forcing the sidebar open or creating a
 * second engine instance.
 */
const getParkedEngineHost = () => {
  if ( parkedEngineHost && parkedEngineHost.isConnected ) {
    return parkedEngineHost;
  }

  parkedEngineHost = document.createElement( 'div' );
  parkedEngineHost.className = 'sm-site-editor-engine-host';
  parkedEngineHost.hidden = true;
  parkedEngineHost.setAttribute( 'aria-hidden', 'true' );
  document.body.appendChild( parkedEngineHost );

  return parkedEngineHost;
};

const parkEngine = eng => {
  if ( eng?.root ) {
    getParkedEngineHost().appendChild( eng.root );
  }
};

const ensureEngineReady = () => {
  const eng = ensureEngine();

  if ( ! eng.root.parentNode ) {
    parkEngine( eng );
  }

  bootEngine( eng );

  return eng;
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
    // Marks this as Style Manager's Site Editor preview for diagnostics and
    // future integrations. Theme AJAX transitions remain disabled because the
    // iframe is still a Customizer preview context.
    u.searchParams.set( 'sm-live-preview', '1' );
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

        // The frontend is still a Customizer preview, so core intercepts links
        // and asks the parent pane to load the next URL with preview markers.
        // Theme AJAX transitions remain disabled in this context; always rebuild.
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

  // Each motion behavior gets the replay that matches its safe preview path:
  // intro animations play on page load (reload through the changeset
  // mechanics), page transitions render a controlled in-iframe overlay because
  // Anima intentionally disables its Barba engine inside Customizer previews.
  const replayIntro = () => refresh();

  const playTransition = () => {
    const doc = iframeRef.current?.contentDocument;
    if ( doc ) {
      const api = window.wp.customize;
      const transitionSymbol = api( 'sm_transition_symbol' ) ? api( 'sm_transition_symbol' )() : '';
      const fallbackSymbol = ( doc.title || payload.siteTitle || '' ).trim().replace( /[^a-z0-9]/ig, '' ).charAt( 0 );

      if ( playSiteEditorMotionPreview( doc, {
        pageTransitionStyle: api( 'sm_page_transition_style' ) ? api( 'sm_page_transition_style' )() : 'border_iris',
        logoLoadingStyle: api( 'sm_logo_loading_style' ) ? api( 'sm_logo_loading_style' )() : 'progress_bar',
        transitionSymbol,
      }, { fallbackSymbol } ) ) {
        return;
      }
    }

    // No preview document available yet — fall back to reloading home.
    currentPageRef.current = payload.homeUrl;
    refresh();
  };

  return (
    <div className="sm-live-site-overlay">
      { 'motion' === hint && (
        <MotionHint
          onReplayIntro={ replayIntro }
          onPlayTransition={ playTransition }
        />
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
 * The motion guidance pill: each replay affordance appears only while its
 * feature toggle is on, and the copy adapts when everything is off.
 */
const MotionHint = ( { onReplayIntro, onPlayTransition } ) => {
  const { useState, useEffect } = wp.element;
  const { __ } = wp.i18n;

  const useSettingValue = settingID => {
    const api = window.wp.customize;
    const setting = api ? api( settingID ) : null;
    const [ value, setValue ] = useState( setting ? setting() : null );

    useEffect( () => {
      if ( ! setting ) {
        return undefined;
      }
      setting.bind( setValue );
      return () => setting.unbind( setValue );
    }, [] );

    return value;
  };

  const transitionsOn = isTruthyBool( useSettingValue( 'sm_page_transitions_enable' ) );
  const introOn = isTruthyBool( useSettingValue( 'sm_intro_animations_enable' ) );

  return (
    <div className="sm-live-site-overlay__hint">
      <span>
        { ! transitionsOn && ! introOn
          ? __( 'Turn on Page Transitions or Intro Animations and watch them here.', '__plugin_txtd' )
          : transitionsOn
            ? __( 'Navigate between pages to experience page transitions.', '__plugin_txtd' )
            : __( 'Intro animations play as the page loads.', '__plugin_txtd' ) }
      </span>
      { transitionsOn && (
        <button type="button" onClick={ onPlayTransition }>
          { __( 'Play transition', '__plugin_txtd' ) }
        </button>
      ) }
      { introOn && (
        <button type="button" onClick={ onReplayIntro }>
          { __( 'Replay intro', '__plugin_txtd' ) }
        </button>
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
export { getPreviewContext, getPreviewMode, setPreviewMode } from './preview-mode';

/**
 * A Preview toggle button bound to the shared preview store: it reads
 * "Preview" / "Close Preview" and tracks the open state for one mode.
 * Used by both the section page header and the per-group headers, so a
 * section can expose several previews (the Tweak Board's Site Frame and
 * Fancy Titles groups) rather than the one-per-section header button.
 *
 * `previewEntry` is `{ mode, context? }`.
 */
const createPreviewToggleButton = ( previewEntry, className ) => {
  const button = document.createElement( 'button' );
  button.type = 'button';
  button.className = className;

  const renderState = state => {
    const isOpen = isPreviewEntryOpen( previewEntry, state );
    button.textContent = isOpen
      ? wp.i18n.__( 'Close Preview', '__plugin_txtd' )
      : wp.i18n.__( 'Preview', '__plugin_txtd' );
    button.classList.toggle( 'is-open', isOpen );
  };
  subscribePreviewMode( renderState );

  button.addEventListener( 'click', () => {
    if ( isPreviewEntryOpen( previewEntry ) ) {
      setPreviewMode( null );
    } else {
      setPreviewMode( previewEntry.mode, previewEntry.context || null );
    }
  } );

  return button;
};

const usePreviewMode = () => {
  const { useState, useEffect } = wp.element;
  const [ state, setState ] = useState( getPreviewState() );

  useEffect( () => {
    return subscribePreviewMode( setState );
  }, [] );

  return state;
};

/**
 * Sections that own a preview: their pages get a "Preview" affordance in the
 * page header (see buildSectionElement). Most open a system board; Motion is
 * intrinsically experiential, so its preview IS the Live Site flow with a
 * motion-specific hint (principle 6 in the section-preview principles doc).
 */
/**
 * The board components memoized once: their props are stable primitives, so
 * the host's phase-only re-renders bail out at the memo boundary. Without
 * this, closing re-renders the whole board tree just to swap a class on the
 * root — a stall that eats the exit animation's frames.
 */
const MemoLiveSiteOverlay = wp.element.memo( LiveSiteOverlay );
const MemoColorsOverlay = wp.element.memo( ColorsOverlay );
const MemoTypographyOverlay = wp.element.memo( TypographyOverlay );
const MemoSpacingOverlay = wp.element.memo( SpacingOverlay );
const MemoSiteFrameOverlay = wp.element.memo( SiteFrameOverlay );
const MemoFancyTitlesOverlay = wp.element.memo( FancyTitlesOverlay );
const MemoContextualColorsOverlay = wp.element.memo( ContextualColorsOverlay );

/**
 * The canvas recede classes live on the canvas element itself, not <body> —
 * see the effect in SiteEditorPreviewOverlays. `phase` of 'entering'/'open'
 * recedes, 'exiting' eases back, null clears both classes.
 */
const setCanvasRecedeState = phase => {
  const canvas = document.querySelector( '.editor-visual-editor' );
  if ( ! canvas ) {
    return;
  }
  canvas.classList.toggle( 'sm-se-canvas-receded', !! phase && 'exiting' !== phase );
  canvas.classList.toggle( 'sm-se-canvas-restoring', 'exiting' === phase );
};

/**
 * The overlay host, mounted on document.body (the editor header and canvas
 * containers clip fixed descendants). Renders only the active overlay plus a
 * "Back to editor" close affordance; nothing renders while in editor mode.
 */
const SiteEditorPreviewOverlays = () => {
  const { useEffect, useState } = wp.element;
  const { __ } = wp.i18n;
  const store = usePreviewMode();
  // What actually renders lags the store on close (deferred unmount): the
  // board keeps rendering with `is-exiting` until the exit animation ends.
  const [ rendered, setRendered ] = useState( () => resolveOverlayRender( clearOverlayRender(), getPreviewState() ) );

  useEffect( () => {
    setRendered( prev => resolveOverlayRender( prev, store ) );
  }, [ store.mode, store.context ] );

  // Phase timers: release the intro/switch animations once everything is at
  // rest, and drop the node once the exit animation has finished. The mode
  // dep matters for switching -> switching (a second switch before the first
  // settles): the phase string is unchanged but the timer must restart.
  useEffect( () => {
    if ( 'entering' === rendered.phase || 'switching' === rendered.phase ) {
      const timer = setTimeout(
        () => setRendered( settleOverlayRender ),
        'switching' === rendered.phase ? getSwitchSettleMs() : getIntroSettleMs()
      );
      return () => clearTimeout( timer );
    }
    if ( 'exiting' === rendered.phase ) {
      const timer = setTimeout( () => setRendered( clearOverlayRender() ), getExitMs() );
      return () => clearTimeout( timer );
    }
    return undefined;
  }, [ rendered.phase, rendered.mode ] );

  // The editor canvas recedes underneath while a board is up — a depth cue
  // that the board sits above the page. The classes go on the canvas element
  // itself, NOT <body>: a body-level toggle forces a style recalc across the
  // editor's whole (large) DOM, which alone can eat the 220ms exit window.
  useEffect( () => {
    setCanvasRecedeState( rendered.mode ? rendered.phase : null );
    return () => setCanvasRecedeState( null );
  }, [ rendered.mode, rendered.phase ] );

  useEffect( () => {
    if ( ! store.mode ) {
      return undefined;
    }

    const onKeyDown = event => {
      if ( 'Escape' === event.key ) {
        setPreviewMode( null );
      }
    };
    document.addEventListener( 'keydown', onKeyDown );
    return () => document.removeEventListener( 'keydown', onKeyDown );
  }, [ store.mode ] );

  if ( ! rendered.mode ) {
    return null;
  }

  const { mode, context, phase } = rendered;

  return (
    <div
      className={ `sm-preview sm-preview--visible${ phase ? ` is-${ phase }` : '' }` }
      aria-hidden={ 'exiting' === phase ? 'true' : undefined }
    >
      <button
        type="button"
        className="sm-preview__close"
        onClick={ () => setPreviewMode( null ) }
      >
        <span aria-hidden="true">✕</span> { __( 'Back to editor', '__plugin_txtd' ) }
      </button>
      <div className="sm-preview__content">
        { /* Mount only the active overlay: the hidden ones would render their
             full trees on editor boot and re-render on every palette change.
             Memoized (MEMO_OVERLAYS) so the host's phase-only re-renders
             (entering -> open -> exiting) bail out instead of re-rendering
             the board's expensive tree during the animation. */ }
        { 'site' === mode && <MemoLiveSiteOverlay show hint={ context } /> }
        { 'colors' === mode && <MemoColorsOverlay show /> }
        { 'typography' === mode && <MemoTypographyOverlay show /> }
        { 'spacing' === mode && <MemoSpacingOverlay show /> }
        { 'site-frame' === mode && <MemoSiteFrameOverlay show /> }
        { 'fancy-titles' === mode && <MemoFancyTitlesOverlay show /> }
        { 'contextual-colors' === mode && <MemoContextualColorsOverlay show /> }
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
 * The values that actually differ from what was loaded (or last saved).
 * A->B->A round-trips and boot-time churn must never publish anything.
 */
const getChangedValues = eng => getChangedSettingsValues(
  eng.api.dirtyValues(),
  payload.customizeSettings.settings
);

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
  dispatch( 'core' ).receiveEntityRecords( ENTITY_KIND, ENTITY_NAME, [
    {
      id: ENTITY_RECORD_ID,
      title: __( 'Design system settings', '__plugin_txtd' ),
      settings: getBaselineSettingsValues( payload.customizeSettings.settings ),
    },
  ] );

  // Mirror engine changes into entity edits: this is what enables the native
  // Save button and lists Style Manager in the save panel.
  const pushEdits = _.debounce( () => {
    const changed = filterLockedPlusChangedValues( getChangedValues( eng ), plusPayload );
    const coreDispatch = dispatch( 'core' );

    if ( Object.keys( changed ).length ) {
      coreDispatch.editEntityRecord( ENTITY_KIND, ENTITY_NAME, ENTITY_RECORD_ID, { settings: changed } );
      return;
    }

    // Everything reverted: clear core-data's edit record so the Save button
    // goes back to sleep, including nested font object round-trips.
    clearNativeSaveEdits(
      coreDispatch,
      ENTITY_KIND,
      ENTITY_NAME,
      ENTITY_RECORD_ID,
      getBaselineSettingsValues( payload.customizeSettings.settings )
    );
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
      const eng = ensureEngineReady();

      containerRef.current.appendChild( eng.root );

      const previewOverlaysRoot = mountPreviewOverlays();

      return () => {
        unmountPreviewOverlays( previewOverlaysRoot );
        parkEngine( eng );
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
 * Deep links: site-editor.php?canvas=edit&sm-sidebar=1[&sm-section=<id>][&sm-preview=1]
 * opens the Style Manager sidebar, focuses a section, and optionally opens
 * the section preview. Used by the Styles-route handoff card.
 */
const handleDeepLink = () => {
  const { shouldOpenSidebar, targetSection, previewEntry } = parseSiteEditorDeepLink( window.location.search );

  if ( ! shouldOpenSidebar ) {
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
      if ( previewEntry ) {
        // Debounced so the focused section paints before the board enters.
        schedulePreviewAutoOpen( () => setPreviewMode( previewEntry.mode, previewEntry.context || null ) );
      }
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
  registerSiteTitleFontShortcut( { ensureEngineReady, payload } );
  initDocsLinks();
  wp.domReady( handleDeepLink );
} else if ( payload ) {
  console.warn( 'Style Manager: the Site Editor PluginSidebar API is not available; the Style Manager sidebar was not registered.' );
}
