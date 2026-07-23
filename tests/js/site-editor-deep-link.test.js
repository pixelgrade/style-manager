import test from 'node:test';
import assert from 'node:assert/strict';

import { parseSiteEditorDeepLink } from '../../src/_js/site-editor/deep-link.js';

test( 'site editor deep links can request a section preview', () => {
	assert.deepEqual(
		parseSiteEditorDeepLink( '?sm-section=sm_color_palettes_section&sm-preview=1' ),
		{
			shouldOpenSidebar: true,
			targetSection: 'sm_color_palettes_section',
			previewEntry: { mode: 'colors' },
		},
		'color section links should request the color preview overlay'
	);

	assert.deepEqual(
		parseSiteEditorDeepLink( '?sm-section=sm_font_palettes_section&sm-preview=1' ),
		{
			shouldOpenSidebar: true,
			targetSection: 'sm_font_palettes_section',
			previewEntry: { mode: 'typography' },
		},
		'typography section links should request the typography preview overlay'
	);

	assert.deepEqual(
		parseSiteEditorDeepLink( '?sm-section=sm_layout_section&sm-preview=1' ),
		{
			shouldOpenSidebar: true,
			targetSection: 'sm_layout_section',
			previewEntry: { mode: 'spacing' },
		},
		'layout section links should request the page-anatomy preview overlay'
	);

	assert.deepEqual(
		parseSiteEditorDeepLink( '?sm-section=sm_spacing_section&sm-preview=1' ),
		{
			shouldOpenSidebar: true,
			targetSection: 'sm_layout_section',
			previewEntry: { mode: 'spacing' },
		},
		'legacy spacing section links should alias to the merged Layout section'
	);
} );

test( 'site editor deep links keep preview closed unless explicitly requested', () => {
	assert.deepEqual(
		parseSiteEditorDeepLink( '?sm-sidebar=1' ),
		{
			shouldOpenSidebar: true,
			targetSection: '',
			previewEntry: null,
		},
		'the generic sidebar link should open Style Manager without a section preview'
	);

	assert.deepEqual(
		parseSiteEditorDeepLink( '?sm-section=sm_color_palettes_section' ),
		{
			shouldOpenSidebar: true,
			targetSection: 'sm_color_palettes_section',
			previewEntry: null,
		},
		'a section link without sm-preview should only focus the section'
	);

	assert.deepEqual(
		parseSiteEditorDeepLink( '?sm-section=unknown_section&sm-preview=1' ),
		{
			shouldOpenSidebar: true,
			targetSection: 'unknown_section',
			previewEntry: null,
		},
		'unknown sections should not activate a preview mode'
	);
} );
