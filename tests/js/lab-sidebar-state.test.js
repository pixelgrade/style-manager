export const runLabSidebarStateTests = async ( assert ) => {
  const {
    buildContextualActivationPatch,
    buildContextualSourcePatch,
  } = await import( '../../src/_js/lab/sidebar/contextual-state.js' );

  assert.deepEqual(
    buildContextualSourcePatch( '#ff5500' ),
    { contextual: '#ff5500' },
    'contextual source edits should not switch the active runtime palette'
  );

  assert.deepEqual(
    buildContextualActivationPatch(),
    { palette: 'contextual-lab' },
    'contextual palette activation should stay an explicit action'
  );
};
