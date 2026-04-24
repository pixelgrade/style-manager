import { SHOWCASE_READY_MESSAGE, SHOWCASE_UPDATE_MESSAGE } from './messages.js';

export const subscribeToIframeReadback = ( callback ) => {
  const handleMessage = ( event ) => {
    if ( event.origin !== window.location.origin || event.data?.type !== SHOWCASE_READY_MESSAGE ) {
      return;
    }

    callback( event.data );
  };

  window.addEventListener( 'message', handleMessage );

  return () => window.removeEventListener( 'message', handleMessage );
};

export const postShowcaseState = ( iframe, payload ) => {
  if ( ! iframe?.contentWindow ) {
    return;
  }

  iframe.contentWindow.postMessage( {
    type: SHOWCASE_UPDATE_MESSAGE,
    ...payload,
  }, window.location.origin );
};
