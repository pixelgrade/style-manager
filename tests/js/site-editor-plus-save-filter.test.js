import test from 'node:test';
import assert from 'node:assert/strict';

import {
	clearNativeSaveEdits,
	filterLockedPlusChangedValues,
	getChangedSettingsValues,
	getBaselineSettingsValues,
} from '../../src/_js/site-editor/plus-save-filter.js';

const lockedPlus = {
	locked: true,
	gatedSettingIds: [
		'sm_color_grades_number',
		'sm_color_promotion_white',
		'sm_text_color_switch_master',
	],
};

test( 'locked Plus trial-only palette output is not mirrored into native save edits', () => {
	assert.deepEqual(
		filterLockedPlusChangedValues(
			{
				sm_advanced_palette_output: '[{"options":{"sm_color_grades_number":8}}]',
			},
			lockedPlus
		),
		{}
	);
} );

test( 'locked Plus keeps free palette source and generated output together', () => {
	const changed = {
		sm_advanced_palette_source: '[{"sources":[{"value":"#123456"}]}]',
		sm_advanced_palette_output: '[{"source":["#123456"]}]',
	};

	assert.deepEqual(
		filterLockedPlusChangedValues( changed, lockedPlus ),
		changed
	);
} );

test( 'locked Plus strips generated palette output when premium tuning changed too', () => {
	assert.deepEqual(
		filterLockedPlusChangedValues(
			{
				sm_advanced_palette_source: '[{"sources":[{"value":"#123456"}]}]',
				sm_color_grades_number: 8,
				sm_advanced_palette_output: '[{"source":["#123456"],"options":{"sm_color_grades_number":8}}]',
			},
			lockedPlus
		),
		{
			sm_advanced_palette_source: '[{"sources":[{"value":"#123456"}]}]',
		}
	);
} );

test( 'unlocked Plus does not filter palette output or premium tuning', () => {
	const changed = {
		sm_color_grades_number: 8,
		sm_advanced_palette_output: '[{"options":{"sm_color_grades_number":8}}]',
	};

	assert.deepEqual(
		filterLockedPlusChangedValues( changed, { ...lockedPlus, locked: false } ),
		changed
	);
} );

test( 'baseline settings values are rebuilt from localized setting records', () => {
	assert.deepEqual(
		getBaselineSettingsValues( {
			sm_color_grades_number: { value: '7', transport: 'refresh' },
			sm_page_transitions_enable: { value: false },
			legacy_shape: 'kept-as-is',
		} ),
		{
			sm_color_grades_number: '7',
			sm_page_transitions_enable: false,
			legacy_shape: 'kept-as-is',
		}
	);
} );

test( 'native save edits are cleared with core-data clear action when available', () => {
	const calls = [];
	const coreDispatch = {
		clearEntityRecordEdits: ( ...args ) => calls.push( [ 'clear', args ] ),
		editEntityRecord: ( ...args ) => calls.push( [ 'edit', args ] ),
	};

	clearNativeSaveEdits( coreDispatch, 'kind', 'name', 'record-id', { sm_font_sizing: 'smaller' } );

	assert.deepEqual( calls, [
		[ 'clear', [ 'kind', 'name', 'record-id' ] ],
	] );
} );

test( 'theme font defaults materialized by the JS engine do not count as changes', () => {
	const baseline = {
		'anima_options[site_title_font]': {
			value: {
				font_family: 'Space Grotesk',
				font_size: { value: false, unit: false },
			},
		},
	};
	const dirtyValues = {
		'anima_options[site_title_font]': {
			font_family: 'Space Grotesk',
			font_size: { value: null, unit: false },
			font_variant: '700',
			letter_spacing: { value: 0, unit: 'em' },
			text_transform: 'none',
			line_height: { value: null, unit: false },
		},
	};

	assert.deepEqual( getChangedSettingsValues( dirtyValues, baseline ), {} );
} );
