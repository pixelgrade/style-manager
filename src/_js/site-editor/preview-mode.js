let previewMode = null;
let previewContext = null;
const previewModeListeners = new Set();

const normalizePreviewContext = context => context || null;

export const getPreviewMode = () => previewMode;

export const getPreviewContext = () => previewContext;

export const getPreviewState = () => ( {
  mode: previewMode,
  context: previewContext,
} );

export const isPreviewEntryOpen = ( previewEntry, state = getPreviewState() ) => {
  if ( ! previewEntry ) {
    return false;
  }

  return state.mode === previewEntry.mode
    && normalizePreviewContext( state.context ) === normalizePreviewContext( previewEntry.context );
};

export const subscribePreviewMode = listener => {
  previewModeListeners.add( listener );
  listener( getPreviewState() );

  return () => previewModeListeners.delete( listener );
};

export const setPreviewMode = ( mode, context = null ) => {
  previewMode = mode || null;
  previewContext = previewMode ? normalizePreviewContext( context ) : null;
  const state = getPreviewState();
  previewModeListeners.forEach( listener => listener( state ) );
};

export const resetPreviewModeForTests = () => {
  previewMode = null;
  previewContext = null;
  previewModeListeners.clear();
};
