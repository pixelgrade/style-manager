/**
 * JS twin of style_manager_content_inset_explicit_css_cb() (src/sm-functions.php)
 * — keep in sync.
 *
 * `--sm-content-inset-explicit: 1` is the opt-in signal for the Layout board
 * contract (Nova Blocks insets the content lines by Content Inset). The server
 * emits it only when the option is saved. A live preview has no "saved" flag,
 * so the tracker treats the first value it sees as the loaded state (already
 * covered by the server-rendered style) and emits the signal once the value has
 * moved away from it in this session — the user touched the control.
 */
export const createContentInsetExplicitTracker = () => {
  let initial;
  let seen = false;
  let touched = false;

  return value => {
    const normalized = null === value || undefined === value ? '' : String( value );

    if ( ! seen ) {
      seen = true;
      initial = normalized;
    } else if ( normalized !== initial ) {
      touched = true;
    }

    return touched;
  };
};

export const getContentInsetExplicitCSS = ( touched, selector, property ) => {
  return touched ? `${ selector } { ${ property }: 1; }\n` : '';
};
