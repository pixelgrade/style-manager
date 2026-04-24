import {
  getInitialHoverVariation,
  getUserPalettes,
  isSourceVariation,
  normalizeHexColor,
  normalizePreviewIndex,
} from '../../src/_js/color-system-preview/utils.js';

export const runColorSystemPreviewTests = async ( assert ) => {
  {
    const palettes = [
      { id: '_internal' },
      { id: '1' },
      { id: 2 },
    ];

    assert.deepEqual(
      getUserPalettes( palettes ).map( ( palette ) => palette.id ),
      [ '1', 2 ],
      'internal underscore-prefixed palettes should be hidden'
    );
  }

  {
    assert.equal( normalizePreviewIndex( 0, 1 ), 0, 'variation 1 should keep the first grade at index 0' );
    assert.equal( normalizePreviewIndex( 0, 12 ), 11, 'variation 12 should wrap the first grade to index 11' );
    assert.equal( normalizePreviewIndex( 11, 2 ), 0, 'variation offsets should wrap after index 11' );
    assert.equal( normalizePreviewIndex( 3, 'bad' ), 3, 'invalid site variation should fall back to 1' );
  }

  {
    assert.equal( normalizeHexColor( ' #AABBCC ' ), '#aabbcc', 'hex colors should be trimmed and normalized' );
    assert.equal( normalizeHexColor( 'rgb(0,0,0)' ), '', 'non-hex colors should not match source detection' );
    assert.equal( getInitialHoverVariation( 6 ), 7, 'hover variation should remain the one-based source index' );
  }

  {
    const variations = [
      { bg: '#ffffff' },
      { bg: '#00aa00' },
      { bg: '#00AA00' },
    ];

    assert.equal(
      isSourceVariation( { variations, workingIndex: 1, source: [ '#00aa00' ] } ),
      true,
      'the first matching background should receive the source badge'
    );
    assert.equal(
      isSourceVariation( { variations, workingIndex: 2, source: [ '#00aa00' ] } ),
      false,
      'duplicate matching backgrounds after the first should not receive another badge'
    );
    assert.equal(
      isSourceVariation( { variations, workingIndex: 0, source: [ '#00aa00' ] } ),
      false,
      'non-source backgrounds should not receive the source badge'
    );
  }
};
