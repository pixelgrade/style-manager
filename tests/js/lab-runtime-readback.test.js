export const runLabRuntimeReadbackTests = async ( assert ) => {
  const { normalizeRuntimeReadback } = await import( '../../src/_js/lab/runtime-readback.js' );

  assert.deepEqual(
    normalizeRuntimeReadback( {} ),
    {
      colors: {
        bg: '',
        accent: '',
        fg1: '',
        fg2: '',
      },
      contextualPalette: null,
      contextualReadout: null,
    },
    'empty iframe readbacks should normalize to stable inspector defaults'
  );

  assert.deepEqual(
    normalizeRuntimeReadback( {
      colors: {
        bg: '#ffffff',
      },
      contextualPalette: {
        id: 'contextual-lab',
      },
      contextualReadout: {
        surface: '#ff5500',
      },
    } ),
    {
      colors: {
        bg: '#ffffff',
        accent: '',
        fg1: '',
        fg2: '',
      },
      contextualPalette: {
        id: 'contextual-lab',
      },
      contextualReadout: {
        surface: '#ff5500',
      },
    },
    'partial iframe readbacks should preserve known values and fill missing color roles'
  );
};
