export const maybeLoadWebfontloaderScript = () => {

  if ( typeof WebFont === 'undefined' ) {
    let tk = document.createElement( 'script' );
    tk.src = parent.styleManager.config.webfontloader_url;
    tk.type = 'text/javascript';
    let s = document.getElementsByTagName( 'script' )[ 0 ];
    s.parentNode.insertBefore( tk, s );
  }
};
