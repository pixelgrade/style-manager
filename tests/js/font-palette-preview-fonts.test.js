import test from 'node:test';
import assert from 'node:assert/strict';

import * as fontPaletteUtils from '../../src/_js/customizer/font-palettes/utils.js';

test( 'font palette previews fall back to available Google variants', () => {
	const buildConfig = fontPaletteUtils.buildFontPalettePreviewWebFontConfig;

	assert.equal(
		typeof buildConfig,
		'function',
		'the font-palette preview config builder should be exported'
	);

	if ( 'function' !== typeof buildConfig ) {
		return;
	}

	const config = buildConfig(
		{
			Gabriela: [ '500' ],
			Cormorant: [ '500' ],
			Jaro: [ '700' ],
			Trueno: [ 'regular' ],
			'Theme Face': [ '700' ],
		},
		{
			google_fonts: {
				Gabriela: { variants: [ '400' ] },
				Cormorant: { variants: [ '400', '500', '600' ] },
				Jaro: { variants: [ '400' ] },
			},
			cloud_fonts: {
				Trueno: { src: 'https://fonts.example.test/trueno.css' },
				'Theme Face': {},
			},
			theme_fonts: {
				'Theme Face': { src: 'https://theme.example.test/fonts.css' },
			},
		}
	);

	assert.deepEqual(
		config,
		{
			classes: false,
			events: false,
			google: {
				families: [ 'Gabriela:400', 'Cormorant:500', 'Jaro:400' ],
			},
			custom: {
				families: [ 'Trueno:n4', 'Theme Face:n7' ],
				urls: [
					'https://fonts.example.test/trueno.css',
					'https://theme.example.test/fonts.css',
				],
			},
		},
		'unsupported Google variants should load an available face without changing supported or custom previews'
	);
} );
