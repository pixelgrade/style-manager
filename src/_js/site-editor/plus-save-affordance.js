/* global wp */
/**
 * Plus "Save · Plus" save-button affordance (Treatment 2).
 *
 * During a session-only Pixelgrade Plus live trial, the Fine-tune controls
 * update the live preview but their gated settings are never persisted (see
 * ./plus-save-filter.js — they are stripped before they ever reach a core-data
 * edit). That means a gated-only change leaves the Style Manager entity clean,
 * so the native Save button stays silent and the user gets no acknowledgement.
 *
 * This module gives those changes a voice WITHOUT ever persisting them:
 *
 *   - State C (gated-only, no free edits): decorate the native Save button in
 *     place as "✦ Save · Plus", make it interactive, and intercept its click
 *     to open a gentle upsell Popover. The click NEVER triggers a save.
 *   - State D (mixed gated + free edits): leave the native Save fully
 *     functional (it persists only the free entity) and add a small decorative
 *     sparkle hint, then surface a post-save Snackbar acknowledging that the
 *     gated refinements are previewing only.
 *   - State none (no gated edits): no decoration, fully native Save.
 *
 * The whole affordance is a no-op unless `plusPayload.locked` is true — a
 * licensed Plus user keeps 100% native behaviour. Nothing here ever lets a
 * gated setting reach `editEntityRecord`/a real save; the honesty guarantee
 * lives entirely in ./plus-save-filter.js, which this module only reads.
 *
 * Note: this build wires only a fixed set of `@wordpress/*` packages as webpack
 * externals (see webpack.config.js); `@wordpress/icons` is not among them, so
 * the sparkle is a small inline SVG styled to the editor chrome rather than an
 * imported icon component.
 */
import React from 'react';
import ReactDOM from 'react-dom';
import _ from 'lodash';

import { filterLockedPlusChangedValues, getSignalGatedChangedValues } from './plus-save-filter';

// The native Save button and its stable container in the unified editor header.
const SETTINGS_CONTAINER_SELECTOR = '.editor-header__settings';
const SAVE_BUTTON_SELECTOR = 'button.editor-post-publish-button__button';

const SM_ADORNMENT_SELECTOR = '.sm-save-plus__spark, .sm-save-plus__pill';

const OBSERVE_OPTIONS = {
  childList: true,
  subtree: true,
  attributes: true,
  attributeFilter: [ 'class', 'disabled', 'aria-disabled' ],
};

// A concave four-point sparkle, drawn to sit on the editor's icon grid.
const SVG_NS = 'http://www.w3.org/2000/svg';
const SPARKLE_PATH_D = 'M12 2c.5 4.5 3 7 7 7-4.5.5-7 3-7 7-.5-4.5-3-7-7-7 4.5-.5 7-3 7-7z';

const createSparkleSvg = ( className, size = 16 ) => {
  const svg = document.createElementNS( SVG_NS, 'svg' );
  svg.setAttribute( 'viewBox', '0 0 24 24' );
  svg.setAttribute( 'width', String( size ) );
  svg.setAttribute( 'height', String( size ) );
  svg.setAttribute( 'focusable', 'false' );
  svg.setAttribute( 'aria-hidden', 'true' );
  if ( className ) {
    svg.setAttribute( 'class', className );
  }
  const path = document.createElementNS( SVG_NS, 'path' );
  path.setAttribute( 'd', SPARKLE_PATH_D );
  path.setAttribute( 'fill', 'currentColor' );
  svg.appendChild( path );
  return svg;
};

const SparkleSvg = ( { className, size = 20 } ) => (
  <svg
    className={ className }
    viewBox="0 0 24 24"
    width={ size }
    height={ size }
    focusable="false"
    aria-hidden="true"
  >
    <path d={ SPARKLE_PATH_D } fill="currentColor" />
  </svg>
);

/**
 * The gentle, session-only-trial upsell shown when the user clicks the
 * decorated Save · Plus button (state C). Anchored to the Save button so
 * `@wordpress/components` handles focus management + Escape-to-close.
 */
const UpsellPopover = ( { anchor, plusPayload, onClose } ) => {
  const { Popover, Button } = wp.components;
  const { __, sprintf } = wp.i18n;

  if ( ! Popover ) {
    return null;
  }

  const upsellUrl = ( plusPayload && plusPayload.upsellUrl ) || 'https://pixelgrade.com/plus';
  const badge = ( plusPayload && plusPayload.badge ) || __( 'Plus', '__plugin_txtd' );
  const productName = ( plusPayload && plusPayload.productName ) || __( 'Pixelgrade Plus', '__plugin_txtd' );

  // Qualitative copy — the raw count of dirty gated settings is an engine
  // implementation detail (one slider can regenerate ~19 values), so a number
  // would badly overstate what the user actually changed.
  const title = __( 'These refinements preview only', '__plugin_txtd' );
  const body = sprintf(
    /* translators: %s: the Pixelgrade Plus product name. */
    __( 'They update the live preview during your trial, but keeping them needs %s.', '__plugin_txtd' ),
    productName
  );

  return (
    <Popover
      anchor={ anchor }
      placement="bottom-end"
      onClose={ onClose }
      focusOnMount="firstElement"
      expandOnMobile
      className="sm-save-plus-popover"
    >
      <div className="sm-save-plus-popover__inner">
        <div className="sm-save-plus-popover__head">
          <SparkleSvg className="sm-save-plus-popover__spark" size={ 18 } />
          <span className="sm-save-plus-popover__badge">{ badge }</span>
        </div>
        <h2 className="sm-save-plus-popover__title">{ title }</h2>
        <p className="sm-save-plus-popover__body">{ body }</p>
        <div className="sm-save-plus-popover__actions">
          <Button
            variant="primary"
            href={ upsellUrl }
            target="_blank"
            rel="noopener noreferrer"
            className="sm-save-plus-popover__cta"
          >
            { __( 'Get Plus', '__plugin_txtd' ) }
          </Button>
          <Button variant="tertiary" onClick={ onClose }>
            { __( 'Maybe later', '__plugin_txtd' ) }
          </Button>
        </div>
      </div>
    </Popover>
  );
};

/**
 * Wire the Save · Plus affordance for a locked Plus trial.
 *
 * @param {Object}   options
 * @param {Object}   options.engine      The Style Manager engine (needs `api.bind/unbind`).
 * @param {Object}   options.plusPayload The localized Plus payload.
 * @param {Function} options.getChanges  Returns the current changed-values map.
 * @param {Object}   options.entity      `{ kind, name, recordId }` of the SM core-data entity.
 *
 * @return {Function} A teardown function (no-op when the affordance is inert).
 */
export const initPlusSaveAffordance = ( { engine, plusPayload, getChanges, entity } = {} ) => {
  // Only ever arms during the trial. Licensed users keep fully native behaviour.
  if ( ! plusPayload || ! plusPayload.locked || ! engine || ! engine.api ) {
    return () => {};
  }

  const { __ } = wp.i18n;
  const C_ARIA_LABEL = __( 'Save · Plus — these refinements need Pixelgrade Plus', '__plugin_txtd' );
  const BADGE_TEXT = plusPayload.badge || __( 'Plus', '__plugin_txtd' );

  let currentState = { name: 'none', gatedCount: 0 };
  let containerEl = null;
  let observer = null;
  let popoverRoot = null;
  let pollTimer = null;
  let rafId = null;
  let settleTimer = null;

  // --- State evaluation -----------------------------------------------------

  const computeState = () => {
    const changed = ( 'function' === typeof getChanges ? getChanges() : null ) || {};
    const free = filterLockedPlusChangedValues( changed, plusPayload );
    const gated = getSignalGatedChangedValues( changed, plusPayload );
    const gatedCount = Object.keys( gated ).length;
    const freeCount = Object.keys( free ).length;

    if ( gatedCount && ! freeCount ) {
      return { name: 'C', gatedCount };
    }
    if ( gatedCount && freeCount ) {
      return { name: 'D', gatedCount };
    }
    return { name: 'none', gatedCount: 0 };
  };

  // --- Button decoration ----------------------------------------------------

  // State C force-enables the natively-disabled button (decorateGatedOnly drops
  // its `aria-disabled`). When we leave C, React won't re-render to put it back
  // if the entity never actually went dirty — so restore the attribute ourselves
  // to mirror core's own SaveButton logic: disabled only when NOTHING is dirty.
  // (A concurrent template/page edit keeps Save legitimately enabled.)
  const restoreNativeDisabledState = button => {
    let anyDirty = false;
    try {
      const dirty = wp.data.select( 'core' ).__experimentalGetDirtyEntityRecords();
      anyDirty = Array.isArray( dirty ) && dirty.length > 0;
    } catch ( e ) {
      // Selector unavailable on this build — leave React's attribute untouched.
      return;
    }
    if ( anyDirty ) {
      button.removeAttribute( 'aria-disabled' );
    } else {
      button.setAttribute( 'aria-disabled', 'true' );
    }
  };

  const clearButton = button => {
    button.classList.remove( 'sm-save-plus', 'sm-save-plus--hint' );
    button.querySelectorAll( SM_ADORNMENT_SELECTOR ).forEach( node => node.remove() );
    if ( button.dataset.smSavePlusLabeled ) {
      button.removeAttribute( 'aria-label' );
      delete button.dataset.smSavePlusLabeled;
    }
    restoreNativeDisabledState( button );
  };

  const ensureSpark = button => {
    if ( ! button.querySelector( '.sm-save-plus__spark' ) ) {
      button.insertBefore( createSparkleSvg( 'sm-save-plus__spark' ), button.firstChild );
    }
  };

  const decorateGatedOnly = button => {
    button.classList.add( 'sm-save-plus' );
    button.classList.remove( 'sm-save-plus--hint' );

    // Make the button interactive even though the entity isn't dirty. The
    // capture-phase interceptor below is what actually keeps the click from
    // ever reaching React's save handler — this only restores focusability and
    // the pointer affordance WordPress strips when the entity is clean.
    if ( button.hasAttribute( 'disabled' ) ) {
      button.removeAttribute( 'disabled' );
    }
    if ( button.hasAttribute( 'aria-disabled' ) ) {
      button.removeAttribute( 'aria-disabled' );
    }

    button.setAttribute( 'aria-label', C_ARIA_LABEL );
    button.dataset.smSavePlusLabeled = '1';

    ensureSpark( button );

    if ( ! button.querySelector( '.sm-save-plus__pill' ) ) {
      const pill = document.createElement( 'span' );
      pill.className = 'sm-save-plus__pill';
      pill.setAttribute( 'aria-hidden', 'true' );
      pill.textContent = BADGE_TEXT;
      button.appendChild( pill );
    }
  };

  const decorateHint = button => {
    button.classList.add( 'sm-save-plus--hint' );
    button.classList.remove( 'sm-save-plus' );

    // The mixed-state button stays natively interactive (free edits make the
    // entity dirty); only a quiet, decorative sparkle marks the Plus hint.
    const pill = button.querySelector( '.sm-save-plus__pill' );
    if ( pill ) {
      pill.remove();
    }
    if ( button.dataset.smSavePlusLabeled ) {
      button.removeAttribute( 'aria-label' );
      delete button.dataset.smSavePlusLabeled;
    }

    ensureSpark( button );
  };

  const decorateButton = button => {
    if ( 'C' === currentState.name ) {
      decorateGatedOnly( button );
    } else if ( 'D' === currentState.name ) {
      decorateHint( button );
    } else {
      clearButton( button );
    }
  };

  const reconcile = () => {
    if ( ! containerEl ) {
      return;
    }
    const button = containerEl.querySelector( SAVE_BUTTON_SELECTOR );
    if ( ! button ) {
      return;
    }

    // Mutate with the observer detached so our own DOM writes never re-trigger
    // it (React re-renders that reset the decoration still re-fire it).
    if ( observer ) {
      observer.disconnect();
    }
    try {
      decorateButton( button );
    } finally {
      if ( observer && containerEl ) {
        observer.observe( containerEl, OBSERVE_OPTIONS );
      }
    }
  };

  const scheduleReconcile = () => {
    if ( rafId ) {
      return;
    }
    rafId = window.requestAnimationFrame( () => {
      rafId = null;
      reconcile();
    } );
  };

  // --- Upsell popover (state C click) --------------------------------------

  const ensurePopoverRoot = () => {
    if ( popoverRoot ) {
      return popoverRoot;
    }
    popoverRoot = document.body.querySelector( ':scope > .sm-save-plus-root' );
    if ( ! popoverRoot ) {
      popoverRoot = document.createElement( 'div' );
      popoverRoot.className = 'sm-save-plus-root';
      document.body.appendChild( popoverRoot );
    }
    return popoverRoot;
  };

  const closePopover = () => {
    if ( popoverRoot ) {
      ReactDOM.render( null, popoverRoot );
    }
  };

  const openPopover = anchor => {
    const root = ensurePopoverRoot();
    ReactDOM.render(
      <UpsellPopover anchor={ anchor } plusPayload={ plusPayload } onClose={ closePopover } />,
      root
    );
  };

  const onCaptureClick = event => {
    if ( 'C' !== currentState.name ) {
      return;
    }
    const target = event.target;
    if ( ! target || 'function' !== typeof target.closest ) {
      return;
    }
    const button = target.closest( SAVE_BUTTON_SELECTOR );
    if ( ! button || ! containerEl || ! containerEl.contains( button ) ) {
      return;
    }

    // The gated-only click must NEVER reach React's save handler.
    event.preventDefault();
    event.stopPropagation();
    if ( 'function' === typeof event.stopImmediatePropagation ) {
      event.stopImmediatePropagation();
    }

    openPopover( button );
  };

  // --- Post-save snackbar (state D) ----------------------------------------

  const notifyGatedSnackbar = () => {
    const noticesDispatch = wp.data.dispatch( 'core/notices' );
    if ( ! noticesDispatch || 'function' !== typeof noticesDispatch.createNotice ) {
      return;
    }
    const upsellUrl = plusPayload.upsellUrl || 'https://pixelgrade.com/plus';
    // Qualitative, count-free copy — see UpsellPopover. Honest about what's
    // previewing vs. kept, without surfacing the engine's cascade count.
    const message = __(
      'Saved your palette. Your Fine-tune refinements are previewing — get Plus to keep them.',
      '__plugin_txtd'
    );
    noticesDispatch.createNotice( 'info', message, {
      type: 'snackbar',
      isDismissible: true,
      actions: [ { label: __( 'Get Plus', '__plugin_txtd' ), url: upsellUrl } ],
    } );
  };

  // --- Wiring ---------------------------------------------------------------

  const commitState = next => {
    const changed = next.name !== currentState.name;
    currentState = next;

    // Leaving state C closes any open upsell.
    if ( 'C' !== next.name ) {
      closePopover();
    }
    if ( changed ) {
      scheduleReconcile();
    }
  };

  const clearSettle = () => {
    if ( settleTimer ) {
      window.clearTimeout( settleTimer );
      settleTimer = null;
    }
  };

  const evaluate = () => {
    const next = computeState();

    // Entering state C is the only false-positive-prone transition: a FREE
    // change (e.g. font sizing) makes the engine recompute the design system,
    // which can momentarily look gated-only mid-cascade before the materialized
    // font values settle and normalize away. Require C to still hold after a
    // short settle window before decorating, so the button never flashes a
    // transient "Save · Plus". Every other transition (to none/D) commits now.
    if ( 'C' === next.name && 'C' !== currentState.name ) {
      clearSettle();
      settleTimer = window.setTimeout( () => {
        settleTimer = null;
        commitState( computeState() );
      }, 350 );
      return;
    }

    clearSettle();
    commitState( next );
  };

  const debouncedEvaluate = _.debounce( evaluate, 250 );

  const bindContainer = container => {
    containerEl = container;
    container.addEventListener( 'click', onCaptureClick, true );

    if ( window.MutationObserver ) {
      observer = new MutationObserver( scheduleReconcile );
      observer.observe( container, OBSERVE_OPTIONS );
    }

    reconcile();
  };

  const waitForContainer = ( attempts = 40 ) => {
    const container = document.querySelector( SETTINGS_CONTAINER_SELECTOR );
    if ( container ) {
      bindContainer( container );
      return;
    }
    if ( attempts > 0 ) {
      pollTimer = window.setTimeout( () => waitForContainer( attempts - 1 ), 250 );
    }
  };

  // Track whether a gated delta was present at the moment a real save started,
  // so the post-save snackbar reflects what was actually dropped (the engine is
  // marked clean by save time, so we cannot recompute it then).
  const { select, subscribe } = wp.data;
  let wasSaving = false;
  let gatedAtSaveStart = 0;
  const unsubscribe = subscribe( () => {
    const saving = select( 'core' ).isSavingEntityRecord( entity.kind, entity.name, entity.recordId );

    if ( saving && ! wasSaving ) {
      const gated = getSignalGatedChangedValues( ( 'function' === typeof getChanges ? getChanges() : null ) || {}, plusPayload );
      gatedAtSaveStart = Object.keys( gated ).length;
    } else if ( ! saving && wasSaving ) {
      const error = select( 'core' ).getLastEntitySaveError( entity.kind, entity.name, entity.recordId );
      if ( ! error && gatedAtSaveStart > 0 ) {
        notifyGatedSnackbar();
      }
      gatedAtSaveStart = 0;
    }

    wasSaving = saving;
  } );

  engine.api.bind( 'sm:setting-change', debouncedEvaluate );
  evaluate();
  waitForContainer();

  return () => {
    if ( pollTimer ) {
      window.clearTimeout( pollTimer );
      pollTimer = null;
    }
    if ( rafId ) {
      window.cancelAnimationFrame( rafId );
      rafId = null;
    }
    if ( settleTimer ) {
      window.clearTimeout( settleTimer );
      settleTimer = null;
    }
    if ( debouncedEvaluate.cancel ) {
      debouncedEvaluate.cancel();
    }
    if ( engine.api && 'function' === typeof engine.api.unbind ) {
      engine.api.unbind( 'sm:setting-change', debouncedEvaluate );
    }
    if ( 'function' === typeof unsubscribe ) {
      unsubscribe();
    }
    if ( observer ) {
      observer.disconnect();
      observer = null;
    }
    if ( containerEl ) {
      containerEl.removeEventListener( 'click', onCaptureClick, true );
      const button = containerEl.querySelector( SAVE_BUTTON_SELECTOR );
      if ( button ) {
        clearButton( button );
      }
      containerEl = null;
    }
    closePopover();
    if ( popoverRoot ) {
      ReactDOM.unmountComponentAtNode( popoverRoot );
      if ( popoverRoot.parentNode ) {
        popoverRoot.parentNode.removeChild( popoverRoot );
      }
      popoverRoot = null;
    }
  };
};
