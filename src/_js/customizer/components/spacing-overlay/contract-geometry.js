/**
 * The Layout board's geometry — a JS twin of Nova Blocks' layout engine
 * (nova-blocks packages/core/src/scss/_layout.scss + base-styles
 * _content-layout.scss), so the numbers the board prints are the page's
 * (GitHub pixelgrade/nova-blocks#655). Keep in sync with the engine.
 *
 * Inputs the page resolves at runtime (the board reads them from the preview,
 * see readPreviewEnvironment()):
 * - `viewport`       the modelled viewport width (px);
 * - `rootFontSize`   `--current-font-size` at :root — the site container is
 *                    Site Container x this (Anima), NOT a % of the viewport;
 * - `bodyFontSize`   `--theme-body-final-font-size` — rails and the inset are
 *                    font-relative: token x bodyFontSize / 16 (the 15/16 body
 *                    scale on a default Anima site);
 * - `spacing`        `--nb-spacing` (px); the rail gap is spacing x Rail Gap;
 * - `sides`          `--nb-wrapper-sides-spacings` (px), the viewport gutter;
 * - `vw`             `100vw` (px; includes a classic scrollbar), which the
 *                    inset's narrow-screen ramp reads. Defaults to `viewport`.
 *
 * Engine rules mirrored here:
 * - container = min(Site Container x rootFontSize, viewport - 2 x sides);
 * - a present rail = min(container / 2 - 1.5 x gap, token x bodyFontSize / 16);
 * - Content Inset applies ONLY once saved (Style Manager's
 *   `--sm-content-inset-explicit` signal). Then, per side, the reading column
 *   starts/ends one inset from the container edge or rail edge (the rail gap
 *   is the minimum beside a rail); the inset is font-relative, scales down
 *   below 1280px (setting x (viewport - 768) / 512, half at 1024) and is capped
 *   so the column keeps three rail gaps;
 * - unsaved (legacy): beside a rail the column stops one rail gap from it and
 *   runs to the container edge on a free side; a rail-less page keeps the
 *   default Small-rail budget on both sides (container - 2 x (Small + gap)).
 * - the Small rail default is 230 (Style Manager's Content Inset default) —
 *   no longer the saved Content Inset (nova-blocks#655 decision 2).
 */

// Nova's `--nb-rail-small-setting` once a Content Inset is saved; while none
// is saved Style Manager prints this same default into `--sm-content-inset`.
export const RAIL_SMALL_DEFAULT = 230;
export const RAIL_MEDIUM_DEFAULT = 330;
export const RAIL_LARGE_DEFAULT = 400;

export const DEFAULT_ENVIRONMENT = {
  viewport: 1280,
  rootFontSize: 16,
  bodyFontSize: 16,
  spacing: 32,
  sides: 32,
};

const railSoft = x => x / Math.pow( 1 + Math.pow( x / 600, 12 ), 1 / 12 );

// Resolve S/M/L from the two rail settings — mirrors style_manager_rail_widths().
// Returns null when BOTH are unset (the caller then uses the defaults).
export const railWidths = ( baseRaw, pitchRaw ) => {
  const baseSet = baseRaw !== '' && baseRaw != null && ! isNaN( parseFloat( baseRaw ) ) && parseFloat( baseRaw ) > 0;
  const pitchSet = pitchRaw !== '' && pitchRaw != null && ! isNaN( parseFloat( pitchRaw ) );
  if ( ! baseSet && ! pitchSet ) {
    return null;
  }
  let s, m, l, mult = 330 / 288;
  if ( pitchSet ) {
    const b = baseSet ? parseFloat( baseRaw ) : 300;
    const fr = parseFloat( pitchRaw ) / 45;
    mult = 1 + ( Math.sqrt( 3 ) - 1 ) * fr * fr;
    s = railSoft( b ); m = railSoft( b * mult ); l = railSoft( b * mult * mult );
  } else {
    const b = parseFloat( baseRaw );
    s = b; m = b * 330 / 288; l = b * 400 / 288;
  }
  return { s: Math.round( s ), m: Math.round( m ), l: Math.round( l ), mult };
};

// The rail tokens the page uses: the rail scale once touched, else the
// defaults (Small 230, Medium 330, Large 400).
export const resolveRails = ( baseRaw, pitchRaw ) => {
  const resolved = railWidths( baseRaw, pitchRaw );
  if ( resolved ) {
    return { ...resolved, touched: true };
  }
  return { s: RAIL_SMALL_DEFAULT, m: RAIL_MEDIUM_DEFAULT, l: RAIL_LARGE_DEFAULT, mult: 330 / 288, touched: false };
};

// The font-relative inset, scaled down below 1280px (Nova `--nb-content-inset`).
export const scaledInset = ( setting, bodyFontSize, viewport ) => {
  const full = setting * bodyFontSize / 16;
  if ( viewport >= 1280 ) {
    return full;
  }
  return Math.min( full, setting * ( viewport - 768 ) / 512 );
};

/**
 * @param {Object}      args
 * @param {Object}      args.env            Runtime inputs (see DEFAULT_ENVIRONMENT).
 * @param {number}      args.containerSetting Site Container (sm_site_container_width).
 * @param {number}      args.insetSetting   Content Inset (sm_content_inset).
 * @param {boolean}     args.explicit       Whether a Content Inset is saved/touched.
 * @param {number}      args.railGap        Rail Gap multiplier (sm_rail_gap).
 * @param {number|null} args.railLeft       Left rail token (design px), or null.
 * @param {number|null} args.railRight      Right rail token (design px), or null.
 * @param {number}      args.railSmall      Small rail token (the rail-less budget).
 * @return {Object} Named lines (viewport x), widths and the reading measure.
 */
export const contractGeometry = ( {
  env = DEFAULT_ENVIRONMENT,
  containerSetting,
  insetSetting,
  explicit,
  railGap = 2,
  railLeft = null,
  railRight = null,
  railSmall = RAIL_SMALL_DEFAULT,
} ) => {
  const { viewport, rootFontSize, bodyFontSize, spacing, sides, vw = viewport } = { ...DEFAULT_ENVIRONMENT, ...env };
  const gap = spacing * railGap;
  const containerIdeal = containerSetting * rootFontSize;
  const container = Math.min( containerIdeal, viewport - 2 * sides );
  const ws = ( viewport - container ) / 2;
  const we = ws + container;

  const railMax = container * 0.5 - gap * 1.5;
  const railPx = token => ( token == null ? 0 : Math.min( railMax, token * bodyFontSize / 16 ) );
  const rL = railPx( railLeft );
  const rR = railPx( railRight );

  let gapL, gapR, inset = 0, insetFull = 0, insetCapped = false;
  if ( explicit ) {
    insetFull = scaledInset( insetSetting, bodyFontSize, vw );
    const cap = ( container - rL - rR ) * 0.5 - gap * 1.5;
    inset = Math.max( 0, Math.min( insetFull, cap ) );
    insetCapped = insetFull > cap + 0.05;
    gapL = rL > 0 ? Math.max( gap, inset ) : inset;
    gapR = rR > 0 ? Math.max( gap, inset ) : inset;
  } else if ( rL > 0 || rR > 0 ) {
    gapL = rL > 0 ? gap : 0;
    gapR = rR > 0 ? gap : 0;
  } else {
    // Rail-less, legacy: the default Small-rail budget stays reserved on both sides.
    const budget = railPx( railSmall ) + gap;
    gapL = budget;
    gapR = budget;
  }

  const gs = ws + rL;
  const ge = we - rR;
  const cs = gs + gapL;
  const ce = ge - gapR;

  return {
    viewport,
    container,
    containerIdeal,
    containerCapped: containerIdeal > container + 0.05,
    gap,
    ws, we, gs, ge, cs, ce,
    cc: ( cs + ce ) / 2,
    railLeftPx: rL,
    railRightPx: rR,
    railMax,
    inset,
    insetFull,
    insetCapped,
    explicit: !! explicit,
    reading: Math.max( 0, ce - cs ),
  };
};

const readLength = ( doc, value ) => {
  const probe = doc.createElement( 'div' );
  probe.style.cssText = 'position:absolute;visibility:hidden;pointer-events:none;height:0;left:0;top:0;width:' + value;
  doc.documentElement.appendChild( probe );
  const width = probe.getBoundingClientRect().width;
  probe.remove();
  return width;
};

/**
 * Read the page's runtime inputs from the Customizer preview document, at the
 * preview's width. Returns null when no preview is reachable.
 */
export const readPreviewEnvironment = win => {
  try {
    const doc = win?.document;
    if ( ! doc?.documentElement ) {
      return null;
    }
    const root = win.getComputedStyle( doc.documentElement );
    const has = name => '' !== root.getPropertyValue( name ).trim();
    const len = ( name, fallback ) => ( has( name ) ? readLength( doc, `var(${ name })` ) : fallback );
    const spacing = len( '--nb-spacing', DEFAULT_ENVIRONMENT.spacing );
    return {
      viewport: doc.documentElement.clientWidth || win.innerWidth,
      vw: win.innerWidth,
      rootFontSize: len( '--current-font-size', DEFAULT_ENVIRONMENT.rootFontSize ),
      bodyFontSize: len( '--theme-body-final-font-size', DEFAULT_ENVIRONMENT.bodyFontSize ),
      spacing,
      sides: len( '--nb-wrapper-sides-spacings', spacing ),
      explicit: '1' === root.getPropertyValue( '--sm-content-inset-explicit' ).trim(),
    };
  } catch ( e ) {
    return null;
  }
};
