import {
  FAMILY_STATUS,
  getFamilyStatusKey,
  sortFamilies,
} from '../../src/_js/design-hub/helpers.js';

export const runDesignHubHelpersTests = async ( assert ) => {
  // getFamilyStatusKey
  {
    assert.equal(
      getFamilyStatusKey( { healthy: true, status: 'ok' } ),
      FAMILY_STATUS.HEALTHY,
      'a healthy family is always "healthy", regardless of its raw status'
    );
  }

  {
    assert.equal(
      getFamilyStatusKey( { healthy: true, status: 'failed' } ),
      FAMILY_STATUS.HEALTHY,
      'healthy wins over a stale "failed" status'
    );
  }

  {
    assert.equal(
      getFamilyStatusKey( { healthy: false, status: 'failed' } ),
      FAMILY_STATUS.FAILED,
      'an unhealthy family with a failed status is "failed"'
    );
  }

  {
    assert.equal(
      getFamilyStatusKey( { healthy: false, status: '' } ),
      FAMILY_STATUS.NOT_DOWNLOADED,
      'an unhealthy family with no status yet is "not_downloaded"'
    );
  }

  {
    assert.equal(
      getFamilyStatusKey( {} ),
      FAMILY_STATUS.NOT_DOWNLOADED,
      'a bare/empty entry defaults to "not_downloaded"'
    );
  }

  // sortFamilies
  {
    assert.deepEqual(
      sortFamilies( [
        { family: 'Zeta', display: 'Zeta', used: false },
        { family: 'Alpha', display: 'Alpha', used: true },
        { family: 'Beta', display: 'Beta', used: false },
        { family: 'Gamma', display: 'Gamma', used: true },
      ] ).map( ( f ) => f.family ),
      [ 'Alpha', 'Gamma', 'Beta', 'Zeta' ],
      'used families sort first, then each group sorts alphabetically by display name'
    );
  }

  {
    assert.deepEqual(
      sortFamilies( [] ),
      [],
      'sorting an empty list returns an empty list'
    );
  }

  {
    assert.deepEqual(
      sortFamilies( 'not-an-array' ),
      [],
      'a non-array input is handled defensively and returns an empty list'
    );
  }

  {
    const input = [
      { family: 'Beta', display: 'Beta', used: false },
      { family: 'Alpha', display: 'Alpha', used: false },
    ];
    const inputCopy = JSON.parse( JSON.stringify( input ) );
    sortFamilies( input );
    assert.deepEqual( input, inputCopy, 'sortFamilies never mutates its input array' );
  }
};
