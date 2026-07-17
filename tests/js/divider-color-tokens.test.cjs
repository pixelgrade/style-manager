const test = require( 'node:test' );
const assert = require( 'node:assert/strict' );
const fs = require( 'node:fs' );
const path = require( 'node:path' );

const SOURCE_PATH = path.join( __dirname, '../../src/_scss/sm-colors-custom-properties.scss' );

test( 'every palette variation exposes contextual divider color roles', () => {
	const source = fs.readFileSync( SOURCE_PATH, 'utf8' );
	const applyVariation = source.match( /@mixin apply-variation\(\$i\)\s*\{([\s\S]*?)\n\}/ )?.[ 1 ] ?? '';

	assert.match(
		applyVariation,
		/--sm-current-divider-color:\s*color-mix\(in srgb,\s*currentColor 20%,\s*transparent\)/
	);
	assert.match(
		applyVariation,
		/--sm-current-divider-strong-color:\s*color-mix\(in srgb,\s*currentColor 45%,\s*transparent\)/
	);
} );
