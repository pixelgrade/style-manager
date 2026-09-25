import React, { useEffect, useRef, useState } from 'react';

import { Overlay } from '../index';
import useCustomizeSettingCallback from '../../hooks/use-customize-setting-callback';
import { createContentInsetExplicitTracker } from '../../../utils/content-inset-explicit';
import {
  DEFAULT_ENVIRONMENT,
  contractGeometry,
  resolveRails,
} from './contract-geometry';
import { disposeEnvironmentProbe, probeEnvironmentAt } from './probe-environment';

import './style.scss';

/**
 * The Layout system board. It draws the whole layout CONTRACT to measure like a
 * blueprint — the site container, the content inset, and the rail scale
 * (Small/Medium/Large sidebar widths) — reacting live to every control. An
 * Examples toggle instantiates the same scale as the four shipped sidecar
 * recipes. Vertical Rhythm and Density stay below (still Layout's story).
 *
 * Every printed number comes from contract-geometry.js, the JS twin of Nova
 * Blocks' layout engine, fed with the page's runtime inputs (font sizes,
 * spacing, gutters) resolved at the modelled viewport width from the editor
 * canvas' live styles (probe-environment.js) — so the board's "reading" is the
 * page's reading column at that width (nova-blocks#655). The rail math mirrors the PHP (style_manager_rail_widths)
 * + the preview JS twins; untouched, the rails are Small 230, Medium 330,
 * Large 400 (Small no longer follows the Content Inset; a saved Small-only
 * value, sm_rail_small, replaces the Small default).
 */

const BASE_STEP = 32;

const RAIL_CHOICES = [
  { key: 'none', label: 'None' },
  { key: 's', label: 'S' },
  { key: 'm', label: 'M' },
  { key: 'l', label: 'L' },
];

const getSettingValue = ( settingID, fallback ) => {
  if ( ! window.wp?.customize ) {
    return fallback;
  }
  const setting = window.wp.customize( settingID );
  return setting ? setting() : fallback;
};

const numOr = ( raw, fallback ) => {
  const v = parseFloat( raw );
  return isNaN( v ) ? fallback : v;
};

// The viewport widths the board can model (1280 is the historic blueprint).
const WIDTH_CHOICES = [ 1024, 1280, 1440, 1920 ];

// ---- SVG string helpers (ported from the playground blueprint) ----
const f = n => Math.round( n * 100 ) / 100;
const esc = s => String( s ).replace( /&/g, '&amp;' ).replace( /</g, '&lt;' ).replace( />/g, '&gt;' );
const T = ( x, y, str, cls, anchor ) => `<text x="${ f( x ) }" y="${ y }" class="${ cls || 'cx-txt' }" text-anchor="${ anchor || 'middle' }">${ esc( str ) }</text>`;
const LN = ( x1, y1, x2, y2, cls ) => `<line x1="${ f( x1 ) }" y1="${ y1 }" x2="${ f( x2 ) }" y2="${ y2 }" class="${ cls || 'cx-line' }"/>`;
const RC = ( x, y, w, h, cls, op ) => `<rect x="${ f( x ) }" y="${ y }" width="${ f( w ) }" height="${ h }" class="${ cls }"${ op != null ? ` opacity="${ op }"` : '' }/>`;
const OLN = ( x1, y1, x2, y2, cls, op ) => `<line x1="${ f( x1 ) }" y1="${ y1 }" x2="${ f( x2 ) }" y2="${ y2 }" class="${ cls }" opacity="${ op }"/>`;
const dim = ( x1, x2, y, label, cls ) => {
  let s = LN( x1, y - 6, x1, y + 6, 'cx-tick' ) + LN( x2, y - 6, x2, y + 6, 'cx-tick' ) + LN( x1, y, x2, y, 'cx-dim' );
  if ( label ) {
    s += T( ( x1 + x2 ) / 2, y - 8, label, cls || 'cx-txt' );
  }
  return s;
};

const px1 = n => Math.round( n * 10 ) / 10;

const buildContractSvg = ( g, r, { cw, ci, railKey, env } ) => {
  const { __ } = wp.i18n;
  const VW = Math.max( 320, Math.round( g.viewport ) ), H = 352;
  const { ws, we, cs, ce, cc, gs, ge, container } = g;
  const reading = Math.round( g.reading );
  const hasRail = g.railRightPx > 0;
  const fsB = env.bodyFontSize;

  // The rail scale as px on this page (font-relative, clamped at the engine's
  // per-rail maximum), drawn as a stepped depth gauge from the right edge.
  const railPx = token => Math.min( g.railMax, token * fsB / 16 );
  const sS = railPx( r.s ), sM = railPx( r.m ), sL = railPx( r.l );
  const cS = r.s * fsB / 16 > g.railMax + 0.5, cM = r.m * fsB / 16 > g.railMax + 0.5, cL = r.l * fsB / 16 > g.railMax + 0.5;

  const bt = 84, bb = 248;
  let s = `<svg class="cx-svg" viewBox="0 0 ${ VW } ${ H }" preserveAspectRatio="xMidYMid meet" role="img" aria-label="${ esc( __( 'Layout contract blueprint', '__plugin_txtd' ) ) }">`;

  // fills
  s += RC( 0.5, bt, VW - 1, bb - bt, 'cx-frame' );
  s += RC( ws, bt, container, bb - bt, 'cx-fill-c' );
  s += RC( cs, bt, Math.max( 0, ce - cs ), bb - bt, 'cx-fill-read' );
  s += RC( gs, bt, Math.max( 0, cs - gs ), bb - bt, 'cx-fill-inset' );
  s += RC( ce, bt, Math.max( 0, ge - ce ), bb - bt, 'cx-fill-inset' );
  if ( hasRail ) {
    // the rail in use, with the other scale steps as a nested depth gauge
    s += RC( ge, bt, g.railRightPx, bb - bt, 'cx-band', 0.12 );
    s += RC( we - sM, bt, sM, bb - bt, 'cx-band', 0.05 );
    s += RC( we - sL, bt, sL, bb - bt, 'cx-band', 0.05 );
    s += RC( we - sS, bt, sS, bb - bt, 'cx-band', 0.05 );
  }

  // content texture (orientation only)
  const ty = [ bt + 24, bt + 48, bt + 72, bt + 96, bt + 120 ];
  for ( let i = 0; i < ty.length; i++ ) {
    const w = ( i === ty.length - 1 ) ? reading * 0.42 : reading * ( 0.80 - i * 0.05 );
    if ( reading > 70 ) {
      s += LN( cs + 16, ty[ i ], cs + 16 + w, ty[ i ], 'cx-tex' );
    }
  }

  // named vertical lines
  s += LN( ws, bt, ws, bb, 'cx-line' );
  s += LN( cs, bt, cs, bb, 'cx-line' );
  s += LN( cc, bt - 6, cc, bb + 6, 'cx-center' );
  s += LN( ce, bt, ce, bb, 'cx-line' );
  if ( hasRail ) {
    s += LN( ge, bt, ge, bb, 'cx-line' );
  }
  s += LN( we, bt, we, bb, 'cx-line' );

  if ( hasRail ) {
    // S/M/L step lines graded (the one in use is solid); clamp dashed
    const steps = [ [ 's', sS, cS, 0.58 ], [ 'm', sM, cM, 0.78 ], [ 'l', sL, cL, 1 ] ];
    for ( const [ key, w, clamped, op ] of steps ) {
      s += OLN( we - w, bt, we - w, bb, 'cx-step' + ( clamped ? ' clamp' : '' ), key === railKey ? 1 : op * 0.5 );
    }

    // left mirror: one faint ghosted gutter line (gs) + a calm note
    const gsGhost = ws + g.railRightPx;
    s += '<g class="cx-mirror">';
    s += LN( gsGhost, bt, gsGhost, bb, 'cx-line faint' );
    s += T( gsGhost, bb - 8, 'gs', 'cx-nlab' );
    s += T( cs + 8, ( bt + bb ) / 2, '◂ ' + __( 'Left Rail mirrors', '__plugin_txtd' ), 'cx-txt-sm', 'start' );
    s += '</g>';
  }

  // named labels row (top), staggered onto two rows
  const labs = [ [ 16, 'fs', 'start' ], [ ws, 'ws' ], [ cs, 'cs' ], [ cc, 'cc' ], [ ce, 'ce' ] ];
  if ( hasRail ) {
    labs.push( [ ge, 'ge' ] );
  }
  labs.push( [ we, 'we' ], [ VW - 16, 'fe', 'end' ] );
  for ( let j = 0; j < labs.length; j++ ) {
    s += T( labs[ j ][ 0 ], ( j % 2 ? 32 : 18 ), labs[ j ][ 1 ], 'cx-nlab', labs[ j ][ 2 ] );
  }

  // container overall dimension (top): Site Container x the root font size
  const containerLabel = __( 'site container', '__plugin_txtd' ) + ' · ' + cw + ' × ' + px1( env.rootFontSize ) + 'px = ' +
    Math.round( g.containerIdeal ) + 'px' + ( g.containerCapped ? ' → ' + Math.round( container ) + 'px ' + __( '(viewport)', '__plugin_txtd' ) : '' );
  s += dim( ws, we, 60, containerLabel, 'cx-txt-acc' );

  // bottom dimension chain: [inset|gap] reading [inset|gap]
  const yc = bb + 28;
  const bandLabel = w => ( g.explicit ? __( 'inset', '__plugin_txtd' ) : __( 'gap', '__plugin_txtd' ) ) + ' ' + Math.round( w );
  s += dim( cs, ce, yc, __( 'reading', '__plugin_txtd' ) + ' ' + reading + 'px', 'cx-txt' );
  if ( ge - ce > 1 ) {
    s += dim( ce, ge, yc, bandLabel( ge - ce ), 'cx-txt-sm' );
  }
  if ( cs - gs > 1 && cs - gs > 60 ) {
    s += dim( gs, cs, yc, bandLabel( cs - gs ), 'cx-txt-sm' );
  }

  if ( hasRail ) {
    // rail depth ruler (the stepped gauge), labelled in rail tokens
    const yr = bb + 62;
    s += LN( we - Math.max( sS, sM, sL ), yr, we, yr, 'cx-dim' );
    s += LN( we, yr - 7, we, yr + 7, 'cx-tick' );
    s += OLN( we - sS, yr - 7, we - sS, yr, 'cx-step', 0.58 );
    s += OLN( we - sM, yr - 7, we - sM, yr, 'cx-step', 0.78 );
    s += OLN( we - sL, yr - 7, we - sL, yr, 'cx-step', 1 );
    const lbl = ( token, w, clamped ) => clamped ? ( token + '→' + Math.round( w * 16 / fsB ) ) : ( '' + token );
    s += T( we - sS / 2, yr + 17, 'S ' + lbl( r.s, sS, cS ), 'cx-txt-acc' );
    s += T( we - ( sS + sM ) / 2, yr + 31, 'M ' + lbl( r.m, sM, cM ), 'cx-txt-acc' );
    s += T( we - ( sM + sL ) / 2, yr + 17, 'L ' + lbl( r.l, sL, cL ), 'cx-txt-acc' );
    s += T( we, yr - 11, ( cL || cM || cS ) ? __( 'rail depth · clamped', '__plugin_txtd' ) + ' ◂' : __( 'rail depth', '__plugin_txtd' ) + ' ◂', 'cx-txt-sm', 'end' );
  }

  s += '</svg>';

  // caption block
  const chip = ( op, letter, tok, eff, clamped ) =>
    `<span class="cx-chip${ clamped ? ' cl' : '' }"><i style="opacity:${ op }"></i>${ letter } <b>${ clamped ? ( tok + '→' + eff ) : tok }</b></span>`;
  const toToken = w => Math.round( w * 16 / fsB );
  const measured = __( 'Measured at', '__plugin_txtd' ) + ' ' + Math.round( g.viewport ) + 'px · ' + __( 'body', '__plugin_txtd' ) + ' ' + px1( fsB ) + 'px · ' +
    ( g.explicit
      ? __( 'Content Inset', '__plugin_txtd' ) + ' ' + ci + ' → ' + px1( g.inset ) + 'px' + ( g.insetCapped ? ' (' + __( 'capped', '__plugin_txtd' ) + ')' : '' )
      : __( 'Content Inset not applied until you set it', '__plugin_txtd' ) );
  const cap = '<div class="cx-caption">' +
    '<div class="cx-cap-title">' + esc( __( 'The Layout contract', '__plugin_txtd' ) ) + '</div>' +
    '<p>' + esc( __( 'Every page is built on one editorial grid. This panel sets three things — how wide the site container runs, how far the content is inset, and the three rail sizes — and every block and recipe reads from it. The named lines fs · ws · gs · cs · cc · ce · ge · we · fe are the grid; one rail is drawn to measure and the left simply mirrors it.', '__plugin_txtd' ) ) + '</p>' +
    '<p class="cx-measured">' + esc( measured ) + '</p>' +
    '<div class="cx-chips"><span class="lead">' + esc( __( 'Rail scale', '__plugin_txtd' ) ) + '</span>' +
      chip( 0.42, 'S', r.s, toToken( sS ), cS ) + chip( 0.68, 'M', r.m, toToken( sM ), cM ) + chip( 1, 'L', r.l, toToken( sL ), cL ) +
    '</div></div>';

  return s + cap;
};

// ---- Examples: the four shipped sidecar recipes ----
// Each tile runs the same engine geometry as the contract (contractGeometry).
const tileGeom = ( args, tokens ) => {
  const g = contractGeometry( args );
  const fsB = args.env.bodyFontSize;
  const railPx = t => Math.min( g.railMax, t * fsB / 16 );
  const padL = g.railLeftPx > 0 ? 0 : g.cs - g.gs;
  const padR = g.railRightPx > 0 ? 0 : g.ge - g.ce;
  const avail = Math.max( 1, g.container - padL - padR );
  const beside = g.railLeftPx > 0 ? g.cs - g.gs : ( g.railRightPx > 0 ? g.ge - g.ce : 0 );
  const eff = tokens.map( t => Math.round( railPx( t ) * 16 / fsB ) );
  return {
    g,
    eff,
    clamped: tokens.map( ( t, i ) => eff[ i ] < t ),
    reading: Math.max( 1, Math.round( g.reading ) ),
    avail,
    padLPct: padL / g.container * 100,
    padRPct: padR / g.container * 100,
    gapPct: beside / avail * 100,
    leftPx: g.railLeftPx,
    rightPx: g.railRightPx,
  };
};
const basis = ( px, avail ) => ( px / avail * 100 );
const role = ( letter, token, eff ) => `<span class="lb">${ letter } ${ eff < token ? ( token + '&rarr;' + eff ) : token }</span>`;
const metaRail = bp => `<div class="lrail meta" style="flex-basis:${ bp }%"><span class="mdash"></span><span class="mdash sh"></span><div class="mdots"><span></span><span></span><span></span></div><span class="mdash sh"></span><div class="mdots"><span></span><span></span></div><span class="mdash"></span></div>`;
const cardRail = bp => `<div class="lrail right" style="flex-basis:${ bp }%"><div class="lcard"><span class="thumb"></span><span class="rl"></span><span class="rl sh"></span></div><div class="lcard"><span class="thumb"></span><span class="rl"></span></div></div>`;
const tile = ( name, desc, roles, geom, bodyInner, cwPct, foot ) =>
  '<div class="lay"><div class="lhead"><span class="dot"></span><span class="nav"></span><span class="nav c"></span></div>' +
  '<div class="lviewport"><div class="lcontainer" style="width:' + cwPct + '%"><div class="lpad" style="padding-left:' + geom.padLPct + '%;padding-right:' + geom.padRPct + '%;gap:' + geom.gapPct + '%">' + bodyInner + '</div></div></div>' +
  '<div class="lcap"><div class="ln">' + esc( name ) + '</div><div class="ld">' + esc( desc ) + ' · ' + wp.i18n.__( 'uses', '__plugin_txtd' ) + ' ' + roles + ' · ' + wp.i18n.__( 'reading', '__plugin_txtd' ) + ' ' + geom.reading + 'px</div>' + ( foot ? '<div class="foot">' + esc( foot ) + '</div>' : '' ) + '</div></div>';

const buildExamples = ( r, base ) => {
  const { __ } = wp.i18n;
  const clampSuffix = t => t.clamped.some( Boolean ) ? ' · <span class="clampflag">' + esc( __( 'clamped', '__plugin_txtd' ) ) + '</span>' : '';
  const cwPct = g => g.container / g.viewport * 100;

  const t1g = tileGeom( { ...base, railRight: r.m }, [ r.m ] );
  const t1 = tile( __( 'Right Rail', '__plugin_txtd' ), __( 'the classic article', '__plugin_txtd' ), role( 'M', r.m, t1g.eff[ 0 ] ) + clampSuffix( t1g ), t1g,
    '<div class="lread article"><span class="h"></span><div class="img"></div><i></i><i class="s"></i><i></i><i class="u"></i></div>' + cardRail( basis( t1g.rightPx, t1g.avail ) ), cwPct( t1g.g ) );

  const t2g = tileGeom( { ...base, railLeft: r.s, railRight: r.m }, [ r.s, r.m ] );
  const t2 = tile( __( 'Hive', '__plugin_txtd' ), __( 'three-column magazine', '__plugin_txtd' ),
    role( 'S', r.s, t2g.eff[ 0 ] ) + ' + ' + role( 'M', r.m, t2g.eff[ 1 ] ) + clampSuffix( t2g ), t2g,
    metaRail( basis( t2g.leftPx, t2g.avail ) ) + '<div class="lread hive"><div class="hcol"><span class="h" style="width:82%"></span><i></i><i class="s"></i><i></i></div><div class="hcol"><i></i><i class="s"></i><i></i><i class="u"></i></div></div>' + cardRail( basis( t2g.rightPx, t2g.avail ) ),
    cwPct( t2g.g ), __( 'left rail uses Small — per-side scales are a future refinement', '__plugin_txtd' ) );

  // The offset gutter is 12% of the container, expressed as a rail token.
  const probe = contractGeometry( base );
  const offsetToken = probe.container * 0.12 * 16 / base.env.bodyFontSize;
  const t3g = tileGeom( { ...base, railLeft: offsetToken, railRight: r.l }, [ r.l ] );
  const t3 = tile( __( 'Offset Editorial', '__plugin_txtd' ), __( 'asymmetric gutter, wide rail', '__plugin_txtd' ), role( 'L', r.l, t3g.eff[ 0 ] ) + clampSuffix( t3g ), t3g,
    '<div class="loffset" style="flex-basis:' + basis( t3g.leftPx, t3g.avail ) + '%"></div><div class="lread article"><span class="h"></span><i></i><i class="s"></i><div class="img"></div><i></i><i class="u"></i></div>' + cardRail( basis( t3g.rightPx, t3g.avail ) ), cwPct( t3g.g ) );

  const t4g = tileGeom( base, [] );
  const t4 = tile( __( 'Centered', '__plugin_txtd' ), __( 'pure reading page', '__plugin_txtd' ), __( 'no rails', '__plugin_txtd' ), t4g,
    '<div class="lread center"><div class="cwrap"><span class="h" style="width:62%"></span><i></i><i class="s"></i><i></i><i></i><i class="u"></i></div></div>', cwPct( t4g.g ) );

  const intro = '<p class="ex-intro">' + esc( __( 'The same scale, instantiated. Each tile is one of the shipped sidecar Layout Recipes, run through the same contract (container, inset, and the 50% rail clamp). S, M and L are each consumed somewhere, with live effective badges.', '__plugin_txtd' ) ) + '</p>';
  const galfoot = '<div class="galfoot">' + esc( __( 'Left Rail mirrors Right Rail. These are the shipped Layout Recipes — the same vocabulary the Sidecar’s recipe picker offers.', '__plugin_txtd' ) ) + '</div>';
  return intro + '<div class="lays">' + t1 + t2 + t3 + t4 + '</div>' + galfoot;
};

const SpacingOverlay = ( props ) => {
  const { show } = props;
  return (
    <Overlay show={ show }>
      <LayoutPreview key={ 'overlay_layout_preview' } />
    </Overlay>
  );
};

const LayoutPreview = () => {
  const { __ } = wp.i18n;

  const [ containerWidth, setContainerWidth ] = useState( () => numOr( getSettingValue( 'sm_site_container_width', 75 ), 75 ) );
  const [ contentInset, setContentInset ] = useState( () => numOr( getSettingValue( 'sm_content_inset', 230 ), 230 ) );
  const [ base, setBase ] = useState( () => getSettingValue( 'sm_rail_scale', '' ) );
  const [ pitch, setPitch ] = useState( () => getSettingValue( 'sm_rail_pitch', '' ) );
  const [ railSmall, setRailSmall ] = useState( () => getSettingValue( 'sm_rail_small', '' ) );
  const [ railGap, setRailGap ] = useState( () => numOr( getSettingValue( 'sm_rail_gap', 2 ), 2 ) );
  const [ spacingLevel, setSpacingLevel ] = useState( () => numOr( getSettingValue( 'sm_spacing_level', 1 ), 1 ) );
  const [ view, setView ] = useState( 'contract' );
  // The rail the contract is drawn with: Small is the Sidecar's default width.
  const [ railKey, setRailKey ] = useState( 's' );
  const [ modelWidth, setModelWidth ] = useState( 1280 );
  const [ env, setEnv ] = useState( null );

  // Content Inset applies on the page only once saved (the preview's signal)
  // or moved in this session (the preview turns the signal on at once).
  const insetTracker = useRef( null );
  if ( ! insetTracker.current ) {
    insetTracker.current = createContentInsetExplicitTracker();
    insetTracker.current( getSettingValue( 'sm_content_inset', '' ) );
  }
  const [ insetTouched, setInsetTouched ] = useState( false );

  useCustomizeSettingCallback( 'sm_site_container_width', v => setContainerWidth( numOr( v, 75 ) ) );
  useCustomizeSettingCallback( 'sm_content_inset', v => {
    setContentInset( numOr( v, 230 ) );
    if ( insetTracker.current( v ) ) {
      setInsetTouched( true );
    }
  } );
  useCustomizeSettingCallback( 'sm_rail_scale', v => setBase( v ) );
  useCustomizeSettingCallback( 'sm_rail_pitch', v => setPitch( v ) );
  useCustomizeSettingCallback( 'sm_rail_small', v => setRailSmall( v ) );
  useCustomizeSettingCallback( 'sm_rail_gap', v => setRailGap( numOr( v, 2 ) ) );
  useCustomizeSettingCallback( 'sm_spacing_level', v => setSpacingLevel( numOr( v, 1 ) ) );

  // Resolve the page's runtime inputs at the modelled width after every change
  // (the canvas restyles asynchronously, so read again shortly after).
  useEffect( () => {
    let alive = true;
    const read = () => probeEnvironmentAt( modelWidth ).then( next => {
      if ( alive ) {
        setEnv( next );
      }
    } );
    read();
    const timer = setTimeout( read, 500 );
    return () => {
      alive = false;
      clearTimeout( timer );
    };
  }, [ modelWidth, containerWidth, contentInset, base, pitch, railSmall, railGap, spacingLevel ] );

  useEffect( () => () => disposeEnvironmentProbe(), [] );

  const baseStep = Math.round( BASE_STEP * spacingLevel );

  const r = resolveRails( base, pitch, railSmall );
  const touched = r.touched;
  const runtime = env || { ...DEFAULT_ENVIRONMENT, viewport: modelWidth };
  const explicit = !! ( env?.explicit || insetTouched );

  const cw = Math.round( containerWidth );
  const ci = Math.round( contentInset );
  const geometryArgs = {
    env: runtime,
    containerSetting: containerWidth,
    insetSetting: contentInset,
    explicit,
    railGap,
    railSmall: r.s,
  };
  const railToken = 'none' === railKey ? null : r[ railKey ];
  const geometry = contractGeometry( { ...geometryArgs, railRight: railToken } );
  const board = view === 'contract'
    ? buildContractSvg( geometry, r, { cw, ci, railKey, env: runtime } )
    : buildExamples( r, geometryArgs );

  const steps = [
    { label: '½×', factor: 0.5 },
    { label: '1×', factor: 1 },
    { label: '2×', factor: 2 },
    { label: '3×', factor: 3 },
  ];

  const stageSub = view === 'contract'
    ? __( 'One blueprint of the contract — the site container and its width, the content inset, and the rail scale drawn as three stepped depths — all to measure. The left mirrors the right.', '__plugin_txtd' )
    : __( 'The same scale instantiated as the four shipped sidecar recipes. Secondary view — the contract diagram is the source of truth.', '__plugin_txtd' );

  return (
    <div className="sm-layout-preview">
      <div className="sm-layout-preview__header">
        <h1>{ __( 'Layout', '__plugin_txtd' ) }</h1>
        <p>
          { __( 'The rail scale lives inside one layout contract: site container, content inset, and the rail sizes. The board draws that contract to measure like a blueprint; the Pitch control sets how steeply the three rail sizes rise. Everything reacts live.', '__plugin_txtd' ) }
        </p>
      </div>

      <div className="sm-layout-preview__stage">
        <div className="sm-layout-preview__stage-head">
          <div>
            <h2>{ view === 'contract' ? __( 'The Layout contract', '__plugin_txtd' ) : __( 'Layout Recipes', '__plugin_txtd' ) }</h2>
            <p className="sm-layout-preview__stage-sub">{ stageSub }</p>
          </div>
          <div className="sm-layout-preview__toggle" role="tablist">
            <button
              type="button"
              className={ view === 'contract' ? 'is-active' : '' }
              aria-pressed={ view === 'contract' }
              onClick={ () => setView( 'contract' ) }
            >{ __( 'Contract', '__plugin_txtd' ) }</button>
            <button
              type="button"
              className={ view === 'examples' ? 'is-active' : '' }
              aria-pressed={ view === 'examples' }
              onClick={ () => setView( 'examples' ) }
            >{ __( 'Examples', '__plugin_txtd' ) }</button>
          </div>
        </div>
        { view === 'contract' && (
          <div className="sm-layout-preview__rail-pick" role="group" aria-label={ __( 'Rail drawn on the page', '__plugin_txtd' ) }>
            <span>{ __( 'Rail on the page', '__plugin_txtd' ) }</span>
            { RAIL_CHOICES.map( choice => (
              <button
                type="button"
                key={ choice.key }
                className={ railKey === choice.key ? 'is-active' : '' }
                aria-pressed={ railKey === choice.key }
                onClick={ () => setRailKey( choice.key ) }
              >{ 'none' === choice.key ? __( 'None', '__plugin_txtd' ) : choice.label }</button>
            ) ) }
            <span className="sm-layout-preview__rail-pick-sep">{ __( 'Screen', '__plugin_txtd' ) }</span>
            { WIDTH_CHOICES.map( width => (
              <button
                type="button"
                key={ width }
                className={ modelWidth === width ? 'is-active' : '' }
                aria-pressed={ modelWidth === width }
                onClick={ () => setModelWidth( width ) }
              >{ width }</button>
            ) ) }
          </div>
        ) }
        { ! touched && (
          <p className="sm-layout-preview__default-note">
            { r.smallOnly
              ? wp.i18n.sprintf(
                /* translators: %s: the Small rail width, in rail tokens. */
                __( 'Showing your Small rail (%s) with the default Medium 330 and Large 400 — until you set a rail scale.', '__plugin_txtd' ),
                r.s
              )
              : __( 'Showing the default rail scale — Small 230, Medium 330, Large 400 — until you set one.', '__plugin_txtd' ) }
          </p>
        ) }
        <div className="sm-layout-preview__board" dangerouslySetInnerHTML={ { __html: board } } />
      </div>

      <div className="sm-layout-preview__columns">
        <div className="sm-layout-preview__section">
          <h2>{ __( 'Vertical rhythm', '__plugin_txtd' ) }</h2>
          <p className="sm-layout-preview__hint">
            { __( 'Every distance between elements is a multiple of the base step.', '__plugin_txtd' ) }
            { ' ' }
            <strong>{ __( 'Base step', '__plugin_txtd' ) }: { baseStep }px</strong>
            { ' ' }({ __( 'Level', '__plugin_txtd' ) } { spacingLevel })
          </p>
          <div className="sm-layout-preview__ladder">
            { steps.map( step => {
              const px = Math.round( baseStep * step.factor );
              return (
                <div className="sm-layout-preview__step" key={ step.label }>
                  <span className="sm-layout-preview__step-label">{ step.label }</span>
                  <span className="sm-layout-preview__step-bar" style={ { width: `${ px * 2 }px` } } />
                  <span className="sm-layout-preview__step-value">{ px }px</span>
                </div>
              );
            } ) }
          </div>
        </div>

        <div className="sm-layout-preview__section">
          <h2>{ __( 'Density', '__plugin_txtd' ) }</h2>
          <p className="sm-layout-preview__hint">
            { __( 'The same content at the current spacing level.', '__plugin_txtd' ) }
          </p>
          <div className="sm-layout-preview__demo" style={ { gap: `${ baseStep }px` } }>
            { [ 1, 2, 3 ].map( card => (
              <div className="sm-layout-preview__card" style={ { padding: `${ Math.round( baseStep * 0.75 ) }px` } } key={ card }>
                <span className="sm-layout-preview__card-title" />
                <span className="sm-layout-preview__card-line" />
                <span className="sm-layout-preview__card-line" style={ { width: '70%' } } />
              </div>
            ) ) }
          </div>
        </div>
      </div>
    </div>
  );
};

export default SpacingOverlay;
