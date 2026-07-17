import test from 'node:test';
import assert from 'node:assert/strict';

import {
	applyFontFamilySelection,
	findFontControl,
	resolveThemeSettingId,
} from '../../src/_js/site-editor/font-setting-adapter.js';

test( 'theme setting resolution prefers an exact id and supports wrapped option ids', () => {
	assert.equal(
		resolveThemeSettingId( {
			site_title_font: { value: { font_family: 'Exact' } },
			'anima_options[site_title_font]': { value: { font_family: 'Wrapped' } },
		}, 'site_title_font' ),
		'site_title_font'
	);

	assert.equal(
		resolveThemeSettingId( {
			'anima_options[site_title_font]': { value: { font_family: 'Wrapped' } },
			'anima_options[navigation_font]': { value: { font_family: 'Other' } },
		}, 'site_title_font' ),
		'anima_options[site_title_font]'
	);

	assert.equal( resolveThemeSettingId( {}, 'site_title_font' ), '' );
} );

test( 'font control lookup follows the matching value holder to its own control row', () => {
	const expectedControl = { id: 'site-title-control' };
	const valueHolder = {
		getAttribute: name => 'data-customize-setting-link' === name ? 'anima_options[site_title_font]' : null,
		closest: selector => 'li.customize-control' === selector ? expectedControl : null,
	};
	const otherValueHolder = {
		getAttribute: () => 'anima_options[navigation_font]',
		closest: () => ( { id: 'navigation-control' } ),
	};
	const root = {
		querySelectorAll: selector => '[data-customize-setting-link]' === selector
			? [ otherValueHolder, valueHolder ]
			: [],
	};

	assert.equal( findFontControl( root, 'anima_options[site_title_font]' ), expectedControl );
	assert.equal( findFontControl( root, 'anima_options[missing_font]' ), null );
} );

test( 'font family selection drives the existing hidden control pipeline', () => {
	const select = { value: 'Old Family' };
	const control = {
		querySelector: selector => 'select.style-manager_font_family' === selector ? select : null,
	};
	const valueHolder = {
		getAttribute: () => 'anima_options[site_title_font]',
		closest: () => control,
	};
	const root = {
		querySelectorAll: () => [ valueHolder ],
	};
	const calls = [];

	assert.equal( applyFontFamilySelection( {
		root,
		settingId: 'anima_options[site_title_font]',
		family: 'Prata',
		ensureOption: ( target, family ) => calls.push( [ 'ensure', target, family ] ),
		dispatchChange: ( target, family ) => {
			target.value = family;
			calls.push( [ 'change', target, family ] );
		},
	} ), true );

	assert.equal( select.value, 'Prata' );
	assert.deepEqual( calls, [
		[ 'ensure', select, 'Prata' ],
		[ 'change', select, 'Prata' ],
	] );

	assert.equal( applyFontFamilySelection( {
		root,
		settingId: 'anima_options[missing_font]',
		family: 'Prata',
		ensureOption: () => assert.fail( 'missing controls must not be mutated' ),
		dispatchChange: () => assert.fail( 'missing controls must not dispatch changes' ),
	} ), false );
} );
