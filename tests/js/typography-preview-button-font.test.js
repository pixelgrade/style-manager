import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';

const source = fs.readFileSync(
	new URL( '../../src/_js/customizer/components/typography-overlay/style.scss', import.meta.url ),
	'utf8'
);

test( 'Typography Preview buttons inherit the live connected font over theme button rules', () => {
	const declarations = source.match(
		/\.sm-typography-preview\s+\[class~="wp-block-button"\]\[class\]\s+\[class~="wp-block-button__link"\]\[class\]\s*\{([\s\S]*?)\n\}/
	)?.[ 1 ] ?? '';

	assert.notEqual(
		declarations,
		'',
		'expected a preview-scoped button-label selector strong enough to outrank theme component typography'
	);

	[
		'font-family',
		'font-size',
		'font-weight',
		'font-style',
		'line-height',
		'letter-spacing',
		'text-transform',
	].forEach( property => {
		assert.match(
			declarations,
			new RegExp( `${ property }:\\s*inherit` ),
			`expected ${ property } to follow the live connected button font`
		);
	} );
} );
