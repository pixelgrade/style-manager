import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';

const createClassList = () => {
	const classes = new Set();

	return {
		add: className => classes.add( className ),
		remove: className => classes.delete( className ),
		contains: className => classes.has( className ),
		toggle: ( className, force ) => {
			const shouldAdd = 'undefined' === typeof force ? ! classes.has( className ) : !! force;
			if ( shouldAdd ) {
				classes.add( className );
			} else {
				classes.delete( className );
			}
			return shouldAdd;
		},
		toString: () => Array.from( classes ).join( ' ' ),
	};
};

const createSetting = initialValue => {
	const listeners = [];
	const setting = () => setting.currentValue;

	setting.currentValue = initialValue;
	setting.bind = callback => listeners.push( callback );
	setting.emit = newValue => {
		setting.currentValue = newValue;
		listeners.forEach( callback => callback( newValue ) );
	};

	return setting;
};

const createIframe = () => {
	const attributes = new Map();

	return {
		contentDocument: {
				body: {
					classList: createClassList(),
					appendChild: () => {},
				},
			documentElement: {
				classList: createClassList(),
			},
			createElement: () => ( {
				setAttribute: () => {},
			} ),
			getElementById: () => null,
		},
		hasAttribute: name => attributes.has( name ),
		setAttribute: ( name, value ) => attributes.set( name, String( value ) ),
		addEventListener: () => {},
	};
};

const loadInitializePreview = () => {
	const source = fs.readFileSync(
		new URL( '../../src/_js/site-editor/preview.js', import.meta.url ),
		'utf8'
	);
	const executable = source
		.replace( /^import .+;\n/gm, '' )
		.replace(
			'/* global WebFont */',
			`const _ = {
				debounce: callback => callback,
				includes: ( collection, value ) => Array.isArray( collection ) && collection.includes( value ),
				isEmpty: value => ! value || ( Array.isArray( value ) && 0 === value.length ),
			};
			const getSettingCSS = () => '';
			const getCSSFromPalettes = () => '';
			const maybeFillPalettesArray = () => {};
			const getFontDetails = () => null;
			const determineFontType = () => '';
			const convertFontVariantToFVD = value => value;
			const standardizeToArray = value => Array.isArray( value ) ? value : [ value ];
			`
		)
		.replace( 'export const initializePreview', 'const initializePreview' );

	return vm.runInNewContext(
		`${ executable }\n( { initializePreview } );`,
		{
			document: globalThis.document,
			window: globalThis.window,
			WebFont: undefined,
		}
	).initializePreview;
};

test( 'Site Editor dark mode preview mirrors setting changes to the canvas iframe', async () => {
	const previousWindow = globalThis.window;
	const previousDocument = globalThis.document;
	const previousStyleManager = globalThis.styleManager;

	const iframe = createIframe();
	const setting = createSetting( 'off' );
	const documentRef = {
		body: {
			classList: createClassList(),
		},
		querySelector: selector => 'iframe[name="editor-canvas"]' === selector ? iframe : null,
		querySelectorAll: () => [],
	};

	globalThis.window = {
		styleManager: {
			config: {
				settings: {},
			},
		},
		matchMedia: () => ( { matches: false } ),
	};
	globalThis.document = documentRef;
	globalThis.styleManager = globalThis.window.styleManager;

	try {
		const initializePreview = loadInitializePreview();

		initializePreview(
			( settingID, callback ) => {
				if ( 'sm_dark_mode_advanced' !== settingID ) {
					return undefined;
				}

				if ( callback ) {
					callback( setting );
				}

				return setting;
			},
			{}
		);

		setting.emit( 'on' );

		assert.equal(
			documentRef.body.classList.contains( 'dark-mode-advanced' ),
			true,
			'parent editor body should enter dark mode'
		);
			assert.equal(
				iframe.contentDocument.documentElement.classList.contains( 'is-dark' ),
				true,
				'editor canvas iframe root should enter dark mode'
			);
			assert.equal(
				iframe.contentDocument.body.classList.contains( 'is-dark' ),
				true,
				'editor canvas iframe body should enter dark mode'
			);

			setting.emit( 'off' );

		assert.equal(
			documentRef.body.classList.contains( 'dark-mode-advanced' ),
			false,
			'parent editor body should leave dark mode'
		);
			assert.equal(
				iframe.contentDocument.documentElement.classList.contains( 'is-dark' ),
				false,
				'editor canvas iframe root should leave dark mode'
			);
			assert.equal(
				iframe.contentDocument.body.classList.contains( 'is-dark' ),
				false,
				'editor canvas iframe body should leave dark mode'
			);
		} finally {
		globalThis.window = previousWindow;
		globalThis.document = previousDocument;
		globalThis.styleManager = previousStyleManager;
	}
} );
