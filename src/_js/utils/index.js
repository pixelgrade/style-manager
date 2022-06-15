export * from './debounce';
export * from './extract-allowed-css-properties';
export * from './get-font-family-fallback-stack';
export * from './get-font-field-css-properties';
export * from './get-font-field-css-code';
export * from './get-font-field-css-value';
export * from './get-font-subfield-unit';
export * from './maybe-load-font-family';
export * from './maybe-load-font-loader-script';
export * from './sanitize-font-family-css-value';
export * from './standardize-to-array';

export const domReady = ( fn ) => {
  // If we're early to the party
  document.addEventListener( "DOMContentLoaded", fn );
  // If late; I mean on time.
  if ( document.readyState === "interactive" || document.readyState === "complete" ) {
    fn();
  }
}
