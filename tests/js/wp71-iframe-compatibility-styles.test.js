import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const testDirectory = path.dirname( fileURLToPath( import.meta.url ) );
const typographyPreviewStyles = path.resolve(
	testDirectory,
	'../../src/_js/customizer/components/typography-overlay/style.scss'
);

test( 'keeps Site Editor UI styles out of the WordPress iframe compatibility copier', () => {
	const source = fs.readFileSync( typographyPreviewStyles, 'utf8' );

	assert.doesNotMatch(
		source,
		/\.wp-block/,
		'Admin-only typography preview CSS must not look like legacy editor-canvas CSS.'
	);
} );
