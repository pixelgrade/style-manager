// This is a mirror logic of the server-side Customize\Fonts::getFontFamilyFallbackStack().
import { resolveFontFallbackStack } from "./resolve-font-fallback-stack";

export const getFontFamilyFallbackStack = ( fontFamily ) => {
  let sm;
  try {
    sm = parent.styleManager || window.styleManager;
  } catch ( e ) {
    sm = window.styleManager;
  }

  let smCustomizer;
  try {
    smCustomizer = parent.sm?.customizer;
  } catch ( e ) {
    smCustomizer = null;
  }

  if ( ! sm || ! smCustomizer ) {
    return '';
  }

  const fontDetails = smCustomizer.getFontDetails( fontFamily );
  if ( ! fontDetails ) {
    return '';
  }

  // Each family gets the stack of its own category (style-manager#219).
  const catalogCategory = sm?.fonts?.google_fonts?.[ fontFamily ]?.category || '';

  return resolveFontFallbackStack( fontDetails, sm?.fonts?.categories || {}, catalogCategory );
};
