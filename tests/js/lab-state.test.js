import {
  buildAdminUrl,
  buildShowcaseUrl,
  getSignalVariations,
  normalizeLabState,
  parseLabState,
  serializeLabState,
} from '../../src/_js/lab/state.js';

export const runLabStateTests = async ( assert ) => {
  {
    assert.deepEqual( normalizeLabState( {} ), {
      palette: '1',
      variation: 1,
      signal: 0,
      parentVariation: 1,
      dark: false,
      shifted: false,
      contextual: '',
    }, 'empty input should normalize to live-site defaults' );
  }

  {
    assert.deepEqual( normalizeLabState( {
      palette: 'brand-blue',
      variation: '99',
      signal: '7',
      parentVariation: '8',
      dark: '1',
      shifted: 'true',
      contextual: '#ffcc00',
    } ), {
      palette: 'brand-blue',
      variation: 12,
      signal: 3,
      parentVariation: 9,
      dark: true,
      shifted: true,
      contextual: '#ffcc00',
    }, 'numeric controls should be clamped and booleans coerced' );
  }

  {
    assert.equal(
      serializeLabState( {
        palette: 'brand-blue',
        variation: 5,
        signal: 2,
        parentVariation: 5,
        dark: true,
        shifted: false,
        contextual: '',
      } ),
      'palette=brand-blue&variation=5&dark=1',
      'dead signal controls should not be serialized into new query strings'
    );
  }

  {
    assert.deepEqual(
      parseLabState( '?palette=contextual-lab&variation=3&signal=1&parentVariation=9&dark=1&shifted=1&contextual=%23abcdef' ),
      {
        palette: 'contextual-lab',
        variation: 3,
        signal: 1,
        parentVariation: 9,
        dark: true,
        shifted: true,
        contextual: '#abcdef',
      },
      'URL state should parse from query params'
    );
  }

  {
    assert.equal(
      buildShowcaseUrl(
        'https://example.test/',
        'nonce123',
        {
          palette: 'contextual-lab',
          variation: 4,
          signal: 2,
          parentVariation: 5,
          dark: true,
          shifted: true,
          contextual: '#112233',
        }
      ),
      'https://example.test/?sm_lab_showcase=1&_wpnonce=nonce123&palette=contextual-lab&variation=4&dark=1&shifted=1&contextual=%23112233',
      'showcase URLs should include route flag, nonce, and normalized lab state'
    );
  }

  {
    assert.deepEqual(
      getSignalVariations( 4, [ 2, 5, 8, 11 ] ),
      [
        { signal: 0, variation: 4 },
        { signal: 1, variation: 2 },
        { signal: 2, variation: 8 },
        { signal: 3, variation: 11 },
      ],
      'signal variation readouts should use Nova preset anchors instead of arithmetic offsets'
    );
  }

  {
    assert.deepEqual(
      getSignalVariations( 8, [ 2, 5, 8, 11 ] ),
      [
        { signal: 0, variation: 8 },
        { signal: 1, variation: 5 },
        { signal: 2, variation: 11 },
        { signal: 3, variation: 2 },
      ],
      'signal presets should be sorted around the current inherited variation'
    );
  }

  {
    assert.equal(
      buildAdminUrl( 'https://example.test/wp-admin/tools.php?page=sm-lab', { palette: '2', variation: 2 } ),
      'https://example.test/wp-admin/tools.php?page=sm-lab&palette=2&variation=2',
      'admin URLs should preserve existing page params'
    );
  }

  {
    assert.equal(
      buildAdminUrl(
        'https://example.test/wp-admin/tools.php?page=sm-lab&palette=2&variation=8&signal=3&parentVariation=9&dark=1&shifted=1&contextual=%23abcdef',
        { palette: '1', variation: 1 }
      ),
      'https://example.test/wp-admin/tools.php?page=sm-lab&palette=1&variation=1',
      'admin URLs should drop stale lab params when the state returns to defaults'
    );
  }
};
