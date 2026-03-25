const getDefaultRequestFrame = () => {
  if ( typeof window !== 'undefined' && typeof window.requestAnimationFrame === 'function' ) {
    return window.requestAnimationFrame.bind( window );
  }

  return callback => {
    callback();
    return 0;
  };
};

const getDefaultCancelFrame = () => {
  if ( typeof window !== 'undefined' && typeof window.cancelAnimationFrame === 'function' ) {
    return window.cancelAnimationFrame.bind( window );
  }

  return () => {};
};

export const createVoiceTunerUpdateScheduler = ( {
  requestFrame = getDefaultRequestFrame(),
  cancelFrame = getDefaultCancelFrame(),
  flush,
} ) => {
  let pendingFrameId = null;

  return {
    schedule() {
      if ( pendingFrameId !== null ) {
        return;
      }

      pendingFrameId = requestFrame( () => {
        pendingFrameId = null;
        flush();
      } );
    },

    cancel() {
      if ( pendingFrameId === null ) {
        return;
      }

      const frameId = pendingFrameId;
      pendingFrameId = null;
      cancelFrame( frameId );
    },
  };
};
