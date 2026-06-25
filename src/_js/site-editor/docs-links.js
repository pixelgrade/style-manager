/**
 * Route Style Manager's contextual documentation links into the in-editor article pop-up that
 * Pixelgrade Assistant exposes (window.pixelgradeAdminHub.docs.openArticle), instead of sending the
 * user to a new browser tab. Keeps editing context: the article opens over the SM sidebar.
 *
 * Fully graceful: a delegated listener scoped to the SM Site Editor sidebar. If Assistant (or the
 * opener) isn't present, or the user uses a modifier/middle click, the normal external link wins.
 */

const DOCS_LINK_SELECTOR = 'a[href*="/docs/"]';

const isPixelgradeDocsHref = ( href ) => /pixelgrade\.com\/docs\//.test( String( href || '' ) );

const getOpener = () => {
  const docs = window.pixelgradeAdminHub && window.pixelgradeAdminHub.docs;

  return docs && typeof docs.openArticle === 'function' ? docs.openArticle : null;
};

export const initDocsLinks = () => {
  if ( typeof document === 'undefined' || document.__smDocsLinksBound ) {
    return;
  }
  document.__smDocsLinksBound = true;

  document.addEventListener( 'click', ( event ) => {
    // Respect new-tab / modified / non-primary clicks.
    if (
      event.defaultPrevented ||
      0 !== event.button ||
      event.metaKey ||
      event.ctrlKey ||
      event.shiftKey ||
      event.altKey
    ) {
      return;
    }

    const link = event.target && event.target.closest ? event.target.closest( DOCS_LINK_SELECTOR ) : null;

    if ( ! link || ! isPixelgradeDocsHref( link.href ) ) {
      return;
    }

    // Only intercept links inside the Style Manager Site Editor sidebar.
    if ( ! link.closest( '.sm-site-editor' ) ) {
      return;
    }

    const openArticle = getOpener();

    if ( ! openArticle ) {
      // Assistant not present — let the external link proceed.
      return;
    }

    // openArticle returns false only when it cannot dispatch (no window/CustomEvent); in that case
    // we leave the external link intact rather than swallowing the click.
    if ( false !== openArticle( { url: link.href } ) ) {
      event.preventDefault();
    }
  } );
};
