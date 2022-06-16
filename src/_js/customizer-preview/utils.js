import { getFontFieldCSSCode, getFontFieldCSSValue, maybeLoadFontFamily } from "../utils";

export const getSettingCSS = ( settingID, newValue, settingConfig ) => {

  if ( settingConfig.type === 'font' ) {
    maybeLoadFontFamily( newValue, settingID );
    const cssValue = getFontFieldCSSValue( settingID, newValue );
    return getFontFieldCSSCode( settingID, cssValue, newValue );
  }

  if ( ! Array.isArray( settingConfig.css ) ) {
    return '';
  }

  return settingConfig.css.reduce( ( acc, propertyConfig, index ) => {
    const { callback_filter, selector, property, unit } = propertyConfig;
    const settingCallback = callback_filter && typeof window[callback_filter] === "function" ? window[callback_filter] : defaultCallbackFilter;

    if ( ! selector || ! property ) {
      return acc;
    }

    return `${ acc }
      ${ settingCallback( newValue, selector, property, unit ) }`
  }, '' );
};

export const inPreviewIframe = () => {
  try {
    return window.self !== window.top;
  } catch ( e ) {
    return true;
  }
};

export const defaultCallbackFilter = ( value, selector, property, unit = '' ) => {
  return `${ selector } { ${ property }: ${ value }${ unit }; }`;
};
