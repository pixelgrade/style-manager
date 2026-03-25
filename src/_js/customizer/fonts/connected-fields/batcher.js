export const createConnectedFieldsBatcher = ( flushCallback ) => {
  let depth = 0;
  const pending = new Map();

  const flush = () => {
    if ( pending.size === 0 ) {
      return;
    }

    const queued = Array.from( pending.entries() );
    pending.clear();

    queued.forEach( ( [ settingID, fontsLogic ] ) => {
      flushCallback( settingID, fontsLogic );
    } );
  };

  return {
    schedule( settingID, fontsLogic ) {
      if ( depth === 0 ) {
        flushCallback( settingID, fontsLogic );
        return;
      }

      pending.set( settingID, fontsLogic );
    },

    run( callback ) {
      depth++;

      try {
        return callback();
      } finally {
        depth--;

        if ( depth === 0 ) {
          flush();
        }
      }
    },
  };
};
