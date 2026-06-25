import test from 'node:test';
import assert from 'node:assert/strict';

import {
  buildControlSettingMap,
  formatVisibleCount,
  getControlContainerId,
  getSettingSelectors,
  getTargetFrameContext,
  getTargetStatusId,
  getVisibleTargetElements,
} from '../../src/_js/site-editor/target-feedback.js';

const createElement = ( {
  display = 'block',
  visibility = 'visible',
  opacity = '1',
  width = 20,
  height = 10,
  nodeName = 'DIV',
  hidden = false,
  ariaHidden = false,
  wrapper = false,
} = {} ) => ( {
  hidden,
  nodeName,
  getAttribute: name => 'aria-hidden' === name && ariaHidden ? 'true' : null,
  getBoundingClientRect: () => ( { width, height } ),
  getClientRects: () => width > 0 && height > 0 ? [ { width, height } ] : [],
  matches: selector => selector.includes( '.editor-styles-wrapper' ) && wrapper,
  closest: selector => {
    if ( '[hidden]' === selector && hidden ) {
      return {};
    }

    if ( '[aria-hidden="true"]' === selector && ariaHidden ) {
      return {};
    }

    if ( selector.includes( '.editor-styles-wrapper' ) && wrapper ) {
      return this;
    }

    return null;
  },
  __style: { display, visibility, opacity },
} );

test( 'getTargetFrameContext prefers the active Style Manager live preview over the editor canvas', () => {
  const liveDocument = { body: {} };
  const editorDocument = { body: {} };
  const liveFrame = { contentDocument: liveDocument };
  const editorFrame = { contentDocument: editorDocument };
  const documentRef = {
    querySelector: selector => {
      if ( '.sm-live-site-overlay__iframe, iframe[title="Live site preview"]' === selector ) {
        return liveFrame;
      }

      if ( 'iframe[name="editor-canvas"]' === selector ) {
        return editorFrame;
      }

      return null;
    },
  };

  assert.deepEqual(
    getTargetFrameContext( { document: documentRef } ),
    {
      document: liveDocument,
      frame: liveFrame,
      mode: 'live',
    }
  );
} );

test( 'buildControlSettingMap keeps bracketed Customizer setting ids addressable by rendered row id', () => {
  const payload = {
    structure: {
      sections: [
        {
          controls: [
            { id: 'mies-lt_options[sm_color_heading_5]_control' },
            { id: 'sm_plain_setting_control' },
          ],
        },
      ],
    },
  };

  assert.equal(
    getControlContainerId( 'mies-lt_options[sm_color_heading_5]_control' ),
    'customize-control-mies-lt_options-sm_color_heading_5_control'
  );

  assert.deepEqual(
    buildControlSettingMap( payload ),
    {
      'customize-control-mies-lt_options-sm_color_heading_5_control': 'mies-lt_options[sm_color_heading_5]',
      'customize-control-sm_plain_setting_control': 'sm_plain_setting',
    }
  );
} );

test( 'getTargetStatusId avoids Customizer control id substrings', () => {
  assert.equal(
    getTargetStatusId( 'customize-control-anima_options-heading_links_color_control' ),
    'sm-se-target-status-1'
  );

  assert.equal(
    getTargetStatusId( 'customize-control-anima_options-heading_links_color_control' ),
    'sm-se-target-status-1'
  );

  assert.equal(
    getTargetStatusId( 'customize-control-anima_options-menu_active_item_color_control' ),
    'sm-se-target-status-2'
  );
} );

test( 'getSettingSelectors prefers editor canvas overrides and falls back per css entry', () => {
  const settings = {
    sm_heading_5: {
      css: [
        { selector: '.entry-content h5' },
        { selector: '.entry-content .eyebrow' },
        { property: 'color' },
      ],
    },
  };

  assert.deepEqual(
    getSettingSelectors( 'sm_heading_5', {
      settings,
      editorCssSelectors: {
        sm_heading_5: {
          0: '.editor-styles-wrapper h5',
        },
      },
    } ),
    [ '.editor-styles-wrapper h5', '.entry-content .eyebrow' ]
  );
} );

test( 'getSettingSelectors narrows broad variable-scope rules to semantic editor targets', () => {
  const settings = {
    heading_5: {
      css: [
        { selector: '*', property: '--theme-heading-5-color' },
      ],
    },
    buttons: {
      css: [
        { selector: '*', property: '--sm-button-background-color' },
      ],
    },
  };

  assert.deepEqual(
    getSettingSelectors( 'heading_5', {
      settings,
      editorCssSelectors: {
        heading_5: {
          0: '.editor-styles-wrapper .block-editor-block-list__block *',
        },
      },
    } ),
    [ '.editor-styles-wrapper .block-editor-block-list__block h5' ]
  );

  assert.deepEqual(
    getSettingSelectors( 'buttons', {
      settings,
      editorCssSelectors: {
        buttons: {
          0: '.editor-styles-wrapper .block-editor-block-list__block *',
        },
      },
    } ),
    [
      '.editor-styles-wrapper .block-editor-block-list__block .wp-block-button__link',
      '.editor-styles-wrapper .block-editor-block-list__block .wp-element-button',
    ]
  );
} );

test( 'getSettingSelectors keeps page title distinct from generic heading targets', () => {
  const settings = {
    page_title: {
      css: [
        { selector: 'h1' },
      ],
    },
    heading_1: {
      css: [
        { selector: '*', property: '--theme-heading-1-color' },
      ],
    },
  };

  assert.deepEqual(
    getSettingSelectors( 'page_title', {
      settings,
      useEditorSelectors: false,
    } ),
    [
      '.page-title',
      '.entry-title',
      '.article-title',
      '.wp-block-post-title',
    ]
  );

  assert.deepEqual(
    getSettingSelectors( 'heading_1', {
      settings,
      useEditorSelectors: false,
    } ),
    [ 'h1' ]
  );
} );

test( 'getVisibleTargetElements ignores invalid selectors, duplicates, hidden matches, and editor wrappers', () => {
  const heading = createElement( { nodeName: 'H5' } );
  const eyebrow = createElement();
  const hidden = createElement( { display: 'none' } );
  const ariaHidden = createElement( { ariaHidden: true } );
  const collapsed = createElement( { width: 0 } );
  const wrapper = createElement( { wrapper: true } );

  const documentRef = {
    querySelectorAll: selector => {
      if ( '.bad[' === selector ) {
        throw new Error( 'invalid selector' );
      }

      return {
        '.editor-styles-wrapper h5': [ heading, hidden, ariaHidden, heading ],
        '.entry-content .eyebrow': [ collapsed, eyebrow, wrapper ],
      }[ selector ] || [];
    },
  };

  assert.deepEqual(
    getVisibleTargetElements( {
      document: documentRef,
      selectors: [ '.bad[', '.editor-styles-wrapper h5', '.entry-content .eyebrow' ],
      getComputedStyle: element => element.__style,
    } ),
    [ heading, eyebrow ]
  );
} );

test( 'formatVisibleCount reports compact target status copy', () => {
  assert.equal( formatVisibleCount( 0 ), 'not on this page' );
  assert.equal( formatVisibleCount( 1 ), '1 visible' );
  assert.equal( formatVisibleCount( 2 ), '2 visible' );
} );
