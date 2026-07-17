import test from 'node:test';
import assert from 'node:assert/strict';

import {
	clearNativeSaveEdits,
	filterLockedPlusChangedValues,
	getChangedSettingsValues,
	getBaselineSettingsValues,
	getGatedChangedValues,
	getSignalGatedChangedValues,
} from '../../src/_js/site-editor/plus-save-filter.js';

const lockedPlus = {
	locked: true,
	gatedSettingIds: [
		'sm_color_grades_number',
		'sm_color_promotion_white',
		'sm_text_color_switch_master',
	],
};

// A payload whose gated set spans the advanced typography layer: the per-category
// elevation/pitch controls and the derived theme font targets, alongside a
// genuine color refinement.
const lockedPlusFont = {
	locked: true,
	fontPalettes: {
		lockedIds: [ 'mnzord' ],
	},
	gatedSettingIds: [
		'sm_color_grades_number',
		'sm_font_primary',          // per-category master font (family)
		'sm_font_body',
		'sm_font_primary_elevation',
		'sm_font_primary_pitch',
		'sm_font_body_elevation',
		'sm_fonts_connected_fields_preset',
		'anima_options[lead_font]',
		'anima_options[heading_1_font]',
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

test( 'locked Plus strips a tier-locked font-palette selection from native save edits', () => {
	assert.deepEqual(
		filterLockedPlusChangedValues(
			{
				sm_font_palette: 'mnzord',
				sm_font_primary: '{"font_family":"Trueno"}',
				sm_advanced_palette_output: '[{"options":{"sm_color_grades_number":4}}]',
			},
			lockedPlusFont
		),
		{}
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

test( 'gated changed values: gated-only change returns the gated map', () => {
	assert.deepEqual(
		getGatedChangedValues( { sm_color_grades_number: 8 }, lockedPlus ),
		{ sm_color_grades_number: 8 }
	);
} );

test( 'gated changed values: mixed change returns only the genuinely gated subset', () => {
	assert.deepEqual(
		getGatedChangedValues(
			{
				sm_advanced_palette_source: '[{"sources":[{"value":"#123456"}]}]',
				sm_color_grades_number: 8,
				sm_advanced_palette_output: '[{"source":["#123456"],"options":{"sm_color_grades_number":8}}]',
			},
			lockedPlus
		),
		{
			// Only ids genuinely in gatedSettingIds count. The free palette source
			// and the generated output blob are both excluded — the output is not a
			// gated refinement, just suppressed churn.
			sm_color_grades_number: 8,
		}
	);
} );

test( 'a directly edited theme font target can signal the Plus save affordance', () => {
	const siteTitleSetting = 'anima_options[site_title_font]';
	const plusWithDirectShortcut = {
		...lockedPlusFont,
		gatedSettingIds: [ ...lockedPlusFont.gatedSettingIds, siteTitleSetting ],
		directGatedSettingValues: {
			[ siteTitleSetting ]: { font_family: 'Prata' },
		},
	};

	assert.deepEqual(
		getSignalGatedChangedValues(
			{ [ siteTitleSetting ]: { font_family: 'Prata' } },
			plusWithDirectShortcut
		),
		{ [ siteTitleSetting ]: { font_family: 'Prata' } }
	);
} );

test( 'a stale direct font marker does not turn a later derived cascade into a Plus signal', () => {
	const siteTitleSetting = 'anima_options[site_title_font]';
	const plusWithStaleShortcut = {
		...lockedPlusFont,
		gatedSettingIds: [ ...lockedPlusFont.gatedSettingIds, siteTitleSetting ],
		directGatedSettingValues: {
			[ siteTitleSetting ]: { font_family: 'Prata' },
		},
	};

	assert.deepEqual(
		getSignalGatedChangedValues(
			{
				sm_font_palette: 'system',
				[ siteTitleSetting ]: { font_family: 'SUSE Mono' },
			},
			plusWithStaleShortcut
		),
		{}
	);
} );

test( 'gated changed values: free-only change returns an empty map', () => {
	assert.deepEqual(
		getGatedChangedValues(
			{
				sm_advanced_palette_source: '[{"sources":[{"value":"#123456"}]}]',
				sm_advanced_palette_output: '[{"source":["#123456"]}]',
			},
			lockedPlus
		),
		{}
	);
} );

test( 'gated changed values: empty input returns an empty map', () => {
	assert.deepEqual( getGatedChangedValues( {}, lockedPlus ), {} );
} );

test( 'gated changed values: nothing is gated when Plus is unlocked', () => {
	assert.deepEqual(
		getGatedChangedValues(
			{
				sm_color_grades_number: 8,
				sm_advanced_palette_output: '[{"options":{"sm_color_grades_number":8}}]',
			},
			{ ...lockedPlus, locked: false }
		),
		{}
	);
} );

test( 'gated changed values: nothing is gated when there is no Plus payload', () => {
	assert.deepEqual(
		getGatedChangedValues( { sm_color_grades_number: 8 }, null ),
		{}
	);
} );

test( 'gated changed values: a lone generated palette output is NOT gated (no phantom Save · Plus)', () => {
	// Boot/round-trip churn can leave the generated palette blob dirty with no
	// real user change. It is stripped from the save, but it is NOT a gated
	// refinement — counting it would raise a phantom "Save · Plus".
	assert.deepEqual(
		getGatedChangedValues(
			{ sm_advanced_palette_output: '[{"options":{"sm_color_grades_number":8}}]' },
			lockedPlus
		),
		{}
	);
} );

test( 'gated and free changed values are disjoint; the orphan palette output is in neither', () => {
	const changed = {
		sm_advanced_palette_source: '[{"sources":[{"value":"#123456"}]}]',
		sm_color_grades_number: 8,
		sm_advanced_palette_output: '[{"source":["#123456"],"options":{"sm_color_grades_number":8}}]',
		sm_text_color_switch_master: true,
	};

	const free = filterLockedPlusChangedValues( changed, lockedPlus );
	const gated = getGatedChangedValues( changed, lockedPlus );

	// Disjoint…
	Object.keys( free ).forEach( id => {
		assert.equal( Object.prototype.hasOwnProperty.call( gated, id ), false );
	} );
	// …gated holds only genuinely gated ids…
	assert.deepEqual(
		Object.keys( gated ).sort(),
		[ 'sm_color_grades_number', 'sm_text_color_switch_master' ]
	);
	// …free holds only the free palette source…
	assert.deepEqual( Object.keys( free ), [ 'sm_advanced_palette_source' ] );
	// …and the generated output orphan is deliberately dropped from BOTH
	// (stripped from the save, but not a gated refinement either).
	assert.equal( Object.prototype.hasOwnProperty.call( free, 'sm_advanced_palette_output' ), false );
	assert.equal( Object.prototype.hasOwnProperty.call( gated, 'sm_advanced_palette_output' ), false );
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

test( 'signal gated values: an individual elevation/pitch tweak counts (no higher-level driver)', () => {
	const changed = {
		sm_font_primary_elevation: 3,                     // individually tuned premium control
		'anima_options[lead_font]': '{"font_size":18}',   // derived output — never counts
	};
	assert.deepEqual(
		getSignalGatedChangedValues( changed, lockedPlusFont ),
		{ sm_font_primary_elevation: 3 }
	);
} );

test( 'signal gated values: a font-sizing change drops the elevation/pitch cascade, keeps real refinements', () => {
	const changed = {
		sm_font_sizing: 'normal',                          // free higher-level driver
		sm_font_primary_elevation: 3,
		sm_font_body_elevation: 2,
		'anima_options[heading_1_font]': '{"font_size":40}',
		sm_color_grades_number: 8,                         // genuine premium refinement
	};
	assert.deepEqual(
		getSignalGatedChangedValues( changed, lockedPlusFont ),
		{ sm_color_grades_number: 8 }
	);
} );

test( 'signal gated values: a font-palette change drops the whole per-category font cascade', () => {
	const changed = {
		sm_font_palette: 'hiv3tt',                         // higher-level driver
		sm_font_primary: '{"font_family":"Hepta Slab"}',   // gated master family (palette-driven)
		sm_font_body: '{"font_family":"Inter"}',
		sm_font_primary_pitch: 1,
		'anima_options[lead_font]': '{"font_size":18}',
	};
	assert.deepEqual( getSignalGatedChangedValues( changed, lockedPlusFont ), {} );
} );

test( 'signal gated values: a font-palette change drops the connected-fields preset cascade', () => {
	const changed = {
		sm_font_palette: 'l82pvc',                         // higher-level driver
		sm_fonts_connected_fields_preset: 'preset-1',      // palette-driven connected field map
	};
	assert.deepEqual( getSignalGatedChangedValues( changed, lockedPlusFont ), {} );
} );

test( 'signal gated values: a tier-locked font-palette selection counts as the gated signal', () => {
	const changed = {
		sm_font_palette: 'mnzord',
		sm_font_primary: '{"font_family":"Trueno"}',
		sm_fonts_connected_fields_preset: 'preset-1-7-5',
		'anima_options[lead_font]': '{"font_family":"Trueno"}',
	};
	assert.deepEqual(
		getSignalGatedChangedValues( changed, lockedPlusFont ),
		{ sm_font_palette: 'mnzord' }
	);
} );

test( 'signal gated values: an individual master-font tweak still counts (no driver)', () => {
	const changed = { sm_font_primary: '{"font_family":"Hepta Slab"}' };
	assert.deepEqual(
		getSignalGatedChangedValues( changed, lockedPlusFont ),
		{ sm_font_primary: '{"font_family":"Hepta Slab"}' }
	);
} );

test( 'signal gated values: derived theme font targets never count on their own', () => {
	const changed = { 'anima_options[lead_font]': '{"font_size":18}' };
	assert.deepEqual( getSignalGatedChangedValues( changed, lockedPlusFont ), {} );
} );

test( 'signal gated values: color refinements are untouched by the typography heuristic', () => {
	assert.deepEqual(
		getSignalGatedChangedValues( { sm_color_grades_number: 8 }, lockedPlusFont ),
		{ sm_color_grades_number: 8 }
	);
} );

test( 'signal gated values: nothing when Plus is unlocked', () => {
	assert.deepEqual(
		getSignalGatedChangedValues( { sm_font_primary_elevation: 3 }, { ...lockedPlusFont, locked: false } ),
		{}
	);
} );
