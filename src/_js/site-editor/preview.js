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

  // Rail-scale tokens: JS twin of style_manager_rail_scale_css_cb() +
  // style_manager_rail_widths(). Base + Pitch soft-ceiling math, with the
  // both-unset / v1-compat / v2 migration contract (see the PHP for the canonical
  // definition). Both settings are read from the engine so either one recomputes.
  const railSoft = x => x / Math.pow( 1 + Math.pow( x / 600, 12 ), 1 / 12 );
  const railRead = id => {
    const s = api( id );
    return s ? s() : '';
  };
  const railWidths = ( baseRaw, pitchRaw ) => {
    const baseSet = baseRaw !== '' && baseRaw != null && ! isNaN( parseFloat( baseRaw ) ) && parseFloat( baseRaw ) > 0;
    const pitchSet = pitchRaw !== '' && pitchRaw != null && ! isNaN( parseFloat( pitchRaw ) );
    if ( ! baseSet && ! pitchSet ) {
      return null;
    }
    let s, m, l;
    if ( pitchSet ) {
      const base = baseSet ? parseFloat( baseRaw ) : 300;
      const f = parseFloat( pitchRaw ) / 45;
      const mult = 1 + ( Math.sqrt( 3 ) - 1 ) * f * f;
      s = railSoft( base ); m = railSoft( base * mult ); l = railSoft( base * mult * mult );
    } else {
      const b = parseFloat( baseRaw );
      s = b; m = b * 330 / 288; l = b * 400 / 288;
    }
    return { small: Math.round( s ), medium: Math.round( m ), large: Math.round( l ) };
  };

  window.sm_rail_scale_css_cb = ( value, selector, property, unit = '' ) => {
    const w = railWidths( railRead( 'sm_rail_scale' ), railRead( 'sm_rail_pitch' ) );
    if ( ! w ) {
      return '';
    }
    const v = '--sm-rail-small' === property ? w.small
      : '--sm-rail-medium' === property ? w.medium
      : '--sm-rail-large' === property ? w.large : null;
    if ( null === v ) {
      return '';
    }
    return `${ selector } { ${ property }: ${ v }${ unit || '' }; }`;
  };

  // Pitch carries no CSS of its own; on change it recomputes and rewrites the
  // sm_rail_scale style tag(s) — top document + canvas — same pattern as
  // sm_site_color_variation_cb.
  window.sm_rail_pitch_css_cb = () => {
    const w = railWidths( railRead( 'sm_rail_scale' ), railRead( 'sm_rail_pitch' ) );
    const css = w
      ? `:root { --sm-rail-small: ${ w.small }; }\n:root { --sm-rail-medium: ${ w.medium }; }\n:root { --sm-rail-large: ${ w.large }; }\n`
      : '';
    const tagId = getStyleTagID( 'sm_rail_scale' );
    document.querySelectorAll( `#${ tagId }` ).forEach( tag => { tag.innerHTML = css; } );
    const canvasDocument = getCanvasDocument();
    if ( canvasDocument ) {
      const tag = canvasDocument.getElementById( tagId );
      if ( tag ) {
        tag.innerHTML = css;
      }
    }
    return '';
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
 * Keep Gutenberg's named font-family presets aligned with Style Manager's
 * live theme roles inside the editor canvas. Blocks with an explicit
 * `has-*-font-family` class read the WordPress preset variables directly,
 * bypassing the theme role selectors that Style Manager refreshes.
 */
const syncFontPresetsToCanvas = canvasDocument => {
  const root = canvasDocument?.documentElement;
  const view = canvasDocument?.defaultView;

  if ( ! root?.style || ! view?.getComputedStyle ) {
    return;
  }

  const styles = view.getComputedStyle( root );
  const roles = [
    [ '--wp--preset--font-family--heading', '--theme-heading-1-font-family' ],
    [ '--wp--preset--font-family--body', '--theme-body-font-family' ],
  ];

  roles.forEach( ( [ presetProperty, themeProperty ] ) => {
    const presetValue = styles.getPropertyValue( presetProperty ).trim();
    const themeValue = styles.getPropertyValue( themeProperty ).trim();

    if ( ! presetValue || ! themeValue || themeValue.includes( presetProperty ) ) {
      return;
    }

    root.style.setProperty( presetProperty, themeValue );
  } );
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

  // Frontend selectors (`.page-title`) match nothing in editor markup. The
  // payload carries the gutenbergified equivalents — merge them in here, for
  // the canvas only. styleManager.config itself must stay frontend-true: the
  // Live Site iframe reads it (window.top) for the rules it injects into the
  // frontend document. See issues #132/#133.
  const selectorOverrides = payload?.editorCssSelectors || {};
  const editorConfigCache = {};
  const getCanvasSettingConfig = settingID => {
    const base = settings[ settingID ];
    const overrides = selectorOverrides[ settingID ];

    if ( ! base || ! overrides || ! Array.isArray( base.css ) ) {
      return base;
    }

    if ( ! editorConfigCache[ settingID ] ) {
      editorConfigCache[ settingID ] = {
        ...base,
        css: base.css.map( ( propertyConfig, idx ) =>
          'undefined' !== typeof overrides[ idx ]
            ? { ...propertyConfig, selector: overrides[ idx ] }
            : propertyConfig
        ),
      };
    }

    return editorConfigCache[ settingID ];
  };

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

    const settingConfig = getCanvasSettingConfig( settingID );

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

    syncFontPresetsToCanvas( canvasDocument );
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

    syncFontPresetsToCanvas( canvasDocument );
  }, 100 );

  properKeys.forEach( settingID => {
    api( settingID, setting => {
      setting.bind( newValue => {
        updateQueue[ settingID ] = newValue;
        flushQueue();
      } );
    } );
  } );

	  const syncDarkModeToCanvas = isDark => {
	    const canvasDocument = getCanvasDocument();
	
	    if ( canvasDocument?.documentElement ) {
	      canvasDocument.documentElement.classList.toggle( 'is-dark', isDark );
	    }

	    // Gutenberg may rewrite the iframe html class after load; body is the
	    // durable scope for live preview selectors inside the canvas.
	    if ( canvasDocument?.body ) {
	      canvasDocument.body.classList.toggle( 'is-dark', isDark );
	    }
	  };

  const applyDarkModePreview = value => {
    const isDark = isDarkModeValueDark( value );

    document.body.classList.toggle( 'dark-mode-advanced', isDark );
    syncDarkModeToCanvas( isDark );
  };

  // Dark mode: toggle the admin body class and mirror the state directly into
  // the canvas iframe. The older EditWithBlocks bridge still covers saved
  // initial state, but live Site Editor setting changes must not depend on it.
  api( 'sm_dark_mode_advanced', setting => {
    applyDarkModePreview( setting() );
    setting.bind( applyDarkModePreview );
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
        const darkModeSetting = api( 'sm_dark_mode_advanced' );
        if ( darkModeSetting ) {
          applyDarkModePreview( darkModeSetting() );
        }
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

      if ( canvasDocument ) {
        syncFontPresetsToCanvas( canvasDocument );
      }
    },
  };
};
