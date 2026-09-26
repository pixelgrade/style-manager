/**
 * JS twin of style_manager_content_inset_explicit_css_cb() (src/sm-functions.php)
 * — keep in sync. The Customizer preview inlines a copy
 * (Screen\Customizer\Preview::sm_content_inset_explicit_css_cb_customizer_preview()).
 *
 * `--sm-content-inset-explicit: 1` is the opt-in signal for the Layout board
 * contract (Nova Blocks insets the content lines by Content Inset). The server
 * emits it only for a value Style Manager saved (style-manager#220); a value
 * saved before 2.7.0 or imported stays legacy until it is saved again.
 *
 * A live preview has no "saved" flag, so the tracker treats the first value it
 * sees as the loaded state (already covered by the server-rendered style) and
 * reports once the value has moved away from it in this session — the user
 * touched the control, so saving will mark it. From then on the preview shows
 * what saving would do: a value is explicit, an empty value (a reset) is not.
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

// Twin of style_manager_content_inset_has_value().
export const contentInsetHasValue = value => {
  if ( null === value || undefined === value || 'boolean' === typeof value ) {
    return false;
  }
  const string = String( value ).trim();
  return '' !== string && isFinite( Number( string ) );
};

export const getContentInsetExplicitCSS = ( touched, selector, property, value ) => {
  if ( ! touched ) {
    return '';
  }

  // `0` overrides a server-rendered `1` (Nova matches `style(--x: 1)` only).
  return `${ selector } { ${ property }: ${ contentInsetHasValue( value ) ? 1 : 0 }; }\n`;
};
