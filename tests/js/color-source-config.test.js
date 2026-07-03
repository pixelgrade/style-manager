import test from 'node:test';
import assert from 'node:assert/strict';

import { getColorsFromInputValue } from '../../src/_js/customizer/utils/get-colors-from-input-value.js';

test( 'color source parsing strips transient picker state', () => {
	const parsed = getColorsFromInputValue(
		JSON.stringify( [
			{
				uid: 'color_group_1',
				sources: [
					{
						uid: 'color_11',
						showPicker: true,
						label: 'Color',
						value: '#ddaa61',
					},
				],
			},
		] )
	);

	assert.deepEqual(
		parsed,
		[
			{
				uid: 'color_group_1',
				sources: [
					{
						uid: 'color_11',
						label: 'Color',
						value: '#ddaa61',
					},
				],
			},
		],
		'picker visibility must stay out of persisted palette source data'
	);
} );
