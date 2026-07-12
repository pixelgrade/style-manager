import test from 'node:test';
import assert from 'node:assert/strict';

import { createValue } from '../../src/_js/site-editor/customize-api.js';

test( 'Site Editor customize values expose the core-compatible callbacks interface', () => {
	const setting = createValue( 'sm_font_primary', { font_family: 'Haskoy' } );
	const calls = [];
	const current = setting.get();

	setting.bind( function( to, from ) {
		calls.push( { context: this, to, from } );
	} );

	assert.equal( typeof setting.callbacks.fireWith, 'function' );

	setting.callbacks.fireWith( setting, [ current, current ] );

	assert.equal( calls.length, 1 );
	assert.equal( calls[ 0 ].context, setting );
	assert.deepEqual( calls[ 0 ].to, current );
	assert.deepEqual( calls[ 0 ].from, current );
} );
