import test from 'node:test';
import assert from 'node:assert/strict';

import { getStaffPicksCollections } from '../../src/_js/site-editor/font-staff-picks.js';

const installGlobals = staffPicks => {
	globalThis.wp = {
		i18n: {
			__: value => value,
		},
	};
	globalThis.window = {
		styleManager: {
			fonts: staffPicks ? { staff_picks: staffPicks } : {},
		},
	};
};

test( 'bundled staff picks lead with a focused Wordmarks collection', () => {
	installGlobals();

	const collections = getStaffPicksCollections();
	const wordmarks = collections[ 0 ];

	assert.equal( wordmarks.key, 'wordmarks' );
	assert.equal( wordmarks.label, 'Staff Picks · Wordmarks' );
	assert.ok( wordmarks.families.includes( 'Reforma1969' ), 'includes a Pixelgrade Cloud display family' );
	assert.ok( wordmarks.families.includes( 'Prata' ), 'includes a Google display family' );
	assert.ok( wordmarks.families.length < 30, 'keeps logo browsing deliberately curated' );
} );

test( 'Wordmarks includes the expanded display-face picks', () => {
	installGlobals();

	const wordmarks = getStaffPicksCollections().find( collection => 'wordmarks' === collection.key );
	const additions = [
		'MuseoModerno',
		'Basteleur',
		'Psychedelic Cowboy',
		'FORTA',
		'Jaro',
	];

	additions.forEach( family => {
		assert.ok( wordmarks.families.includes( family ), `includes ${ family }` );
	} );
	assert.equal( new Set( wordmarks.families ).size, wordmarks.families.length, 'keeps every family unique' );
} );

test( 'a cloud payload remains authoritative for the Wordmarks collection', () => {
	installGlobals( {
		wordmarks: [ 'Cloud Wordmark One', 'Cloud Wordmark Two' ],
		headings: [],
		body: [],
		handwriting: [],
	} );

	assert.deepEqual( getStaffPicksCollections(), [
		{
			key: 'wordmarks',
			label: 'Staff Picks · Wordmarks',
			families: [ 'Cloud Wordmark One', 'Cloud Wordmark Two' ],
		},
	] );
} );
