export const emptyReadbackColors = {
  bg: '',
  accent: '',
  fg1: '',
  fg2: '',
};

export const normalizeRuntimeReadback = ( data = {} ) => ( {
  colors: {
    ...emptyReadbackColors,
    ...( data.colors || {} ),
  },
  contextualPalette: data.contextualPalette || null,
  contextualReadout: data.contextualReadout || null,
} );
