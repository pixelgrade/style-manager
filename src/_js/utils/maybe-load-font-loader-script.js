export const maybeLoadWebfontloaderScript = () => {

  if ( typeof WebFont === 'undefined' ) {
    let webfontloaderUrl;
    try {
      webfontloaderUrl = ( parent.styleManager || window.styleManager )?.config?.webfontloader_url;
    } catch ( e ) {
      webfontloaderUrl = window.styleManager?.config?.webfontloader_url;
    }

    if ( ! webfontloaderUrl ) {
      return;
    }

    let tk = document.createElement( 'script' );
    tk.src = webfontloaderUrl;
    tk.type = 'text/javascript';
    let s = document.getElementsByTagName( 'script' )[ 0 ];
    s.parentNode.insertBefore( tk, s );
  }
};
