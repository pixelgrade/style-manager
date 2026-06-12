/* global WebFont */
import _ from 'lodash';

import { getSettingCSS } from '../customizer-preview/utils';
import { getCSSFromPalettes, maybeFillPalettesArray } from '../customizer/utils';
import { getFontDetails, determineFontType, convertFontVariantToFVD } from '../customizer/fonts/utils';
import { standardizeToArray } from '../utils/standardize-to-array';

const getStyleTagID = settingID => `dynamic_style_${ settingID.replace( /\W/g, '_' ) }`;

/**
 * The JS equivalents of the CSS callback_filter functions the Customizer
 * preview defines (see Screen\Customizer\Preview). getSettingCSS() looks
 * them up on window by name.
 */
const installCssCallbacks = ( api, fallbackPalettes, userPalettesCount ) => {

  window.sm_advanced_palette_output_cb = ( value ) => {
    let palettes = [];
    try {
      palettes = JSON.parse( value );
    } catch ( e ) {
      palettes = [];
    }

    const variationSetting = api( 'sm_site_color_variation' );
    const variation = parseInt( variationSetting ? variationSetting() : 1, 10 ) || 1;

    if ( ! Array.isArray( palettes ) || ! palettes.length ) {
      palettes = fallbackPalettes;
    }

    maybeFillPalettesArray( palettes, userPalettesCount );

    return getCSSFromPalettes( palettes, variation );
  };

  // site_color_variation updates the same style tag as advanced_palette_output
  // to avoid cascading two competing rule sets (same as the Customizer preview).
  window.sm_site_color_variation_cb = ( value ) => {
    const outputSetting = api( 'sm_advanced_palette_output' );

    if ( outputSetting ) {
      // Recompute through the output callback and update its style tags directly.
      const css = window.sm_advanced_palette_output_cb( outputSetting() );

      document.querySelectorAll( `#${ getStyleTagID( 'sm_advanced_palette_output' ) }` ).forEach( tag => {
        tag.innerHTML = css;
      } );

      const canvasDocument = getCanvasDocument();
      if ( canvasDocument ) {
        const tag = canvasDocument.getElementById( getStyleTagID( 'sm_advanced_palette_output' ) );
        if ( tag ) {
          tag.innerHTML = css;
        }
      }
    }

    return '';
  };

  window.sm_color_select_dark_cb = ( value, selector, property ) => {
    return `${ selector } {${ property }: var(--sm-current-${ value }-color);}`;
  };

  window.sm_color_select_darker_cb = window.sm_color_select_dark_cb;

  window.sm_color_switch_dark_cb = ( value, selector, property ) => {
    const color = true === value ? 'accent' : 'fg1';
    return `${ selector } { ${ property }: var(--sm-current-${ color }-color); }`;
  };

  window.sm_color_switch_darker_cb = ( value, selector, property ) => {
    const color = true === value ? 'accent' : 'fg2';
    return `${ selector } { ${ property }: var(--sm-current-${ color }-color); }`;
  };
};

const getCanvasIframe = () => document.querySelector( 'iframe[name="editor-canvas"]' );

const getCanvasDocument = () => {
  const iframe = getCanvasIframe();

  if ( ! iframe ) {
    return null;
  }

  try {
    return iframe.contentDocument;
  } catch ( e ) {
    return null;
  }
};

/**
 * Load a font value's webfont into a given window (the editor canvas iframe),
 * mirroring maybeLoadFontFamily() but with WebFont's `context` option.
 */
const loadFontInContext = ( font, settingID, contextWindow, cache ) => {
  if ( 'undefined' === typeof WebFont || ! font || 'undefined' === typeof font.font_family ) {
    return;
  }

  const fontConfig = window.styleManager?.config?.settings?.[ settingID ];
  const loadAllVariants = !! fontConfig?.fields?.[ 'font-weight' ]?.loadAllVariants;

  let family = font.font_family;
  const fontType = determineFontType( family );

  if ( 'system_font' === fontType ) {
    return;
  }

  const fontDetails = getFontDetails( family, fontType );
  if ( ! fontDetails ) {
    return;
  }

  let variants =
    (
      'undefined' !== typeof font.font_variant
      && ! loadAllVariants
      && 'undefined' !== typeof fontDetails.variants
      && _.includes( fontDetails.variants, font.font_variant )
    ) ? font.font_variant : ( 'undefined' !== typeof fontDetails.variants ? fontDetails.variants : [] );

  variants = standardizeToArray( variants );

  const loaderArgs = {
    classes: false,
    events: false,
    context: contextWindow,
  };

  if ( 'theme_font' === fontType || 'cloud_font' === fontType ) {
    if ( 'undefined' === typeof fontDetails.src ) {
      return;
    }

    if ( ! _.isEmpty( variants ) ) {
      family = family + ':' + variants.map( variant => convertFontVariantToFVD( variant ) ).join( ',' );
    }

    if ( cache.indexOf( family ) === - 1 ) {
      WebFont.load( { ...loaderArgs, custom: { families: [ family ], urls: [ fontDetails.src ] } } );
      cache.push( family );
    }
  } else if ( 'google_font' === fontType ) {
    if ( ! _.isEmpty( variants ) ) {
      family = family + ':' + variants.join( ',' );
    }

    if ( cache.indexOf( family ) === - 1 ) {
      WebFont.load( { ...loaderArgs, google: { families: [ family ] } } );
      cache.push( family );
    }
  }
};

/**
 * Compute whether dark mode should be on for a given setting value.
 */
const isDarkModeValueDark = value => {
  if ( 'on' === value ) {
    return true;
  }

  if ( 'auto' === value && window.matchMedia ) {
    return window.matchMedia( '(prefers-color-scheme: dark)' ).matches;
  }

  return false;
};

/**
 * Live preview for the Site Editor: regenerates per-setting style tags inside
 * the editor canvas iframe whenever a Style Manager setting changes — the same
 * mechanics as the Customizer preview (dynamic_style_* tags + getSettingCSS).
 */
export const initializePreview = ( api, payload ) => {
  const settings = window.styleManager?.config?.settings || {};
  const fallbackPalettes = payload?.preview?.fallbackPalettes || [];
  const userPalettesCount = payload?.preview?.userPalettesCount || 0;

  installCssCallbacks( api, fallbackPalettes, userPalettesCount );

  const properKeys = Object.keys( settings ).filter( settingID => {
    const setting = settings[ settingID ];
    return 'font' === setting.type || ( Array.isArray( setting.css ) && setting.css.length );
  } );

  let canvasFontsCache = [];

  const ensureStyleTags = () => {
    const canvasDocument = getCanvasDocument();

    if ( ! canvasDocument || ! canvasDocument.body ) {
      return null;
    }

    properKeys.forEach( settingID => {
      const idAttr = getStyleTagID( settingID );

      if ( ! canvasDocument.getElementById( idAttr ) ) {
        const style = canvasDocument.createElement( 'style' );
        style.setAttribute( 'id', idAttr );
        canvasDocument.body.appendChild( style );
      }
    } );

    return canvasDocument;
  };

  const renderSetting = ( canvasDocument, settingID, value ) => {
    const styleTag = canvasDocument.getElementById( getStyleTagID( settingID ) );

    if ( ! styleTag ) {
      return;
    }

    const settingConfig = settings[ settingID ];

    styleTag.innerHTML = getSettingCSS( settingID, value, settingConfig );

    // getSettingCSS loads webfonts into the pane document (for the controls);
    // mirror the load into the canvas iframe so the content renders with them.
    if ( 'font' === settingConfig.type && value && 'object' === typeof value ) {
      const iframe = getCanvasIframe();
      if ( iframe && iframe.contentWindow ) {
        try {
          loadFontInContext( value, settingID, iframe.contentWindow, canvasFontsCache );
        } catch ( e ) {
          // Never let font loading break the preview pipeline.
        }
      }
    }
  };

  const renderAll = () => {
    const canvasDocument = ensureStyleTags();

    if ( ! canvasDocument ) {
      return;
    }

    properKeys.forEach( settingID => {
      const setting = api( settingID );
      if ( setting ) {
        renderSetting( canvasDocument, settingID, setting() );
      }
    } );
  };

  // Debounced queue, like the Customizer preview, so a flurry of connected
  // field updates results in a single style recalculation pass.
  let updateQueue = {};
  const flushQueue = _.debounce( () => {
    const queue = { ...updateQueue };
    updateQueue = {};

    const canvasDocument = ensureStyleTags();

    if ( ! canvasDocument ) {
      return;
    }

    Object.keys( queue ).forEach( settingID => {
      renderSetting( canvasDocument, settingID, queue[ settingID ] );
    } );
  }, 100 );

  properKeys.forEach( settingID => {
    api( settingID, setting => {
      setting.bind( newValue => {
        updateQueue[ settingID ] = newValue;
        flushQueue();
      } );
    } );
  } );

  // Dark mode: toggle the admin body class; the existing EditWithBlocks
  // MutationObserver propagates it to the canvas iframe root as `is-dark`.
  api( 'sm_dark_mode_advanced', setting => {
    const applyDarkMode = value => {
      document.body.classList.toggle( 'dark-mode-advanced', isDarkModeValueDark( value ) );
    };

    setting.bind( applyDarkMode );
  } );

  // The canvas iframe gets torn down and recreated as the user navigates
  // between templates — re-establish the style tags whenever that happens.
  const bindCanvas = () => {
    const iframe = getCanvasIframe();

    if ( iframe && ! iframe.hasAttribute( 'data-sm-preview-bound' ) ) {
      iframe.setAttribute( 'data-sm-preview-bound', '1' );
      canvasFontsCache = [];
      iframe.addEventListener( 'load', () => {
        canvasFontsCache = [];
        renderAll();
      } );
    }

    renderAll();
  };

  bindCanvas();

  if ( window.MutationObserver ) {
    const observer = new MutationObserver( _.debounce( bindCanvas, 200 ) );
    observer.observe( document.body, { childList: true, subtree: true } );
  }

  return {
    renderAll,
    /**
     * Swap the server-generated editor CSS in after a save, wherever the
     * inline style landed (canvas iframe and/or pane document).
     */
    applySavedCSS: css => {
      if ( ! css || ! css.editor ) {
        return;
      }

      const inlineStyleID = `${ payload.editorDynamicStyleHandle }-inline-css`;
      const documents = [ document ];
      const canvasDocument = getCanvasDocument();
      if ( canvasDocument ) {
        documents.push( canvasDocument );
      }

      documents.forEach( doc => {
        const tag = doc.getElementById( inlineStyleID );
        if ( tag ) {
          tag.innerHTML = css.editor;
        }
      } );
    },
  };
};
