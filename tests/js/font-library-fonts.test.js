import test from 'node:test';
import assert from 'node:assert/strict';

import {
	FONT_FAMILY_SETTING_PATHS,
	registerFontFamilyOwnership,
	withoutBlockFontFamilies,
} from '../../src/_js/utils/font-family-ownership.js';
import { loadFontFaces, variantMatchesFace } from '../../src/_js/utils/load-font-faces.js';
import { determineFontType } from '../../src/_js/customizer/fonts/utils/determine-font-type.js';

const INSTALLED = [ { fontFamily: 'Inter' } ];

// -----------------------------------------------------------------------------
// Block-level font family pickers stay out of the way (ownership).
// -----------------------------------------------------------------------------

test( 'blocks get no font family presets from any origin', () => {
	for ( const path of FONT_FAMILY_SETTING_PATHS ) {
		assert.deepEqual( withoutBlockFontFamilies( undefined, path, 'client-1', 'core/paragraph' ), [], path );
	}

	assert.ok( FONT_FAMILY_SETTING_PATHS.includes( 'typography.fontFamilies.custom' ), 'Font Library fonts live in the custom (user) origin.' );
} );

test( 'other typography settings stay untouched for blocks', () => {
	for ( const path of [ 'typography.fontSizes.theme', 'typography.lineHeight', 'typography.fontWeight', 'typography.letterSpacing' ] ) {
		assert.equal( withoutBlockFontFamilies( undefined, path, 'client-1', 'core/heading' ), undefined, path );
	}
} );

test( 'global (non-block) font family reads stay untouched', () => {
	assert.equal( withoutBlockFontFamilies( undefined, 'typography.fontFamilies.custom', undefined, undefined ), undefined );
	assert.equal( withoutBlockFontFamilies( INSTALLED, 'typography.fontFamilies.custom', null, '' ), INSTALLED );
} );

test( 'the ownership filter hooks the public blockEditor.useSetting.before filter', () => {
	const calls = [];
	assert.equal( registerFontFamilyOwnership( { addFilter: ( ...args ) => calls.push( args ) } ), true );
	assert.equal( calls.length, 1 );
	assert.equal( calls[ 0 ][ 0 ], 'blockEditor.useSetting.before' );
	assert.equal( calls[ 0 ][ 2 ], withoutBlockFontFamilies );
	assert.equal( registerFontFamilyOwnership( undefined ), false );
} );

// -----------------------------------------------------------------------------
// Font type resolution.
// -----------------------------------------------------------------------------

const withFonts = ( fonts, fn ) => {
	const previous = globalThis.styleManager;
	globalThis.styleManager = { fonts: { third_party_fonts: {}, cloud_fonts: {}, theme_fonts: {}, google_fonts: {}, system_fonts: {}, ...fonts } };
	try {
		fn();
	} finally {
		globalThis.styleManager = previous;
	}
};

test( 'a Font Library font wins over a same-named Google font, so it loads locally', () => {
	withFonts( {
		font_library_fonts: { Inter: { family: 'Inter', font_faces: [] } },
		google_fonts: { Inter: { family: 'Inter' } },
	}, () => {
		assert.equal( determineFontType( 'Inter' ), 'font_library_font' );
	} );
} );

test( 'without Font Library data the resolution is unchanged', () => {
	withFonts( { google_fonts: { Inter: { family: 'Inter' } } }, () => {
		assert.equal( determineFontType( 'Inter' ), 'google_font' );
	} );
} );

// -----------------------------------------------------------------------------
// Loading faces in a document, once.
// -----------------------------------------------------------------------------

const fakeDocument = ( existing = [] ) => {
	const faces = [ ...existing ];
	class FakeFontFace {
		constructor( family, src, descriptors ) {
			Object.assign( this, { family, src, weight: descriptors.weight, style: descriptors.style, display: descriptors.display } );
		}

		load() {
			return Promise.resolve( this );
		}
	}

	return {
		fonts: { forEach: fn => faces.forEach( fn ), add: face => faces.push( face ) },
		defaultView: { FontFace: FakeFontFace },
		faces,
	};
};

const inter = {
	family: 'Inter',
	font_faces: [
		{ fontFamily: 'Inter', fontStyle: 'normal', fontWeight: '400', src: [ 'https://example.test/inter-400.woff2' ] },
		{ fontFamily: 'Inter', fontStyle: 'italic', fontWeight: '700', src: 'https://example.test/inter-700i.woff2' },
	],
};

test( 'library faces load into the document through the Font Loading API', () => {
	const doc = fakeDocument();

	assert.equal( loadFontFaces( inter, doc ), 2 );
	assert.equal( doc.faces[ 0 ].family, 'Inter' );
	assert.equal( doc.faces[ 0 ].src, 'url("https://example.test/inter-400.woff2")' );
	assert.equal( doc.faces[ 1 ].style, 'italic' );
} );

test( 'faces the document already has (e.g. printed by WordPress) are not loaded again', () => {
	const doc = fakeDocument( [ { family: '"Inter"', weight: '400', style: 'normal' } ] );

	assert.equal( loadFontFaces( inter, doc ), 1 );
	assert.equal( loadFontFaces( inter, doc ), 0, 'A second call adds nothing.' );
	assert.equal( doc.faces.length, 2 );
} );

test( 'fonts without face data load nothing', () => {
	const doc = fakeDocument();

	assert.equal( loadFontFaces( { family: 'Google Sans' }, doc ), 0 );
	assert.equal( loadFontFaces( undefined, doc ), 0 );
	assert.equal( doc.faces.length, 0 );
} );

test( 'variants match fixed and variable weights and the style', () => {
	assert.equal( variantMatchesFace( '400', '400', 'normal' ), true );
	assert.equal( variantMatchesFace( '700italic', '700', 'italic' ), true );
	assert.equal( variantMatchesFace( '700', '700', 'italic' ), false );
	assert.equal( variantMatchesFace( '600', '100 900', 'normal' ), true );
	assert.equal( variantMatchesFace( '300', '400 900', 'normal' ), false );
} );

test( 'only the wanted variants load when a variant list is given', () => {
	const doc = fakeDocument();

	assert.equal( loadFontFaces( inter, doc, [ '700italic' ] ), 1 );
	assert.equal( doc.faces[ 0 ].weight, '700' );
} );
