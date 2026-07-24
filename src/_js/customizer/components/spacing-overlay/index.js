import React, { useState } from 'react';

import { Overlay } from '../index';
import useCustomizeSettingCallback from '../../hooks/use-customize-setting-callback';

import './style.scss';

/**
 * The Layout system board. It draws the whole layout CONTRACT to measure like a
 * blueprint — the site container width, the content inset, and the rail scale
 * (Small/Medium/Large sidebar widths) — reacting live to every control. An
 * Examples toggle instantiates the same scale as the four shipped sidecar
 * recipes. Vertical Rhythm and Density stay below (still Layout's story).
 *
 * The rail math mirrors the PHP (style_manager_rail_widths) + the preview JS
 * twins: Base + Pitch with a soft ceiling; when untouched the board shows the
 * legacy fallback (Small = content inset, Medium 330, Large 400).
 */

const BASE_STEP = 32;
const VIEWPORT = 1280;

const railSoft = x => x / Math.pow( 1 + Math.pow( x / 600, 12 ), 1 / 12 );

// Resolve S/M/L from the two rail settings — mirrors style_manager_rail_widths().
// Returns null when BOTH are unset (the caller then shows the legacy fallback).
const railWidths = ( baseRaw, pitchRaw ) => {
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

const buildContractSvg = ( r, cw, ci ) => {
  const { __ } = wp.i18n;
  const VW = VIEWPORT, H = 352;
  const container = VW * cw / 100;
  const ws = ( VW - container ) / 2, we = VW - ws;
  // Engine 50% guard on the rail (per-side), leaving room for the inset.
  const guard = Math.max( container * 0.5 - ci, 20 );
  const sS = Math.min( r.s, guard ), sM = Math.min( r.m, guard ), sL = Math.min( r.l, guard );
  const cS = r.s > guard + 0.5, cM = r.m > guard + 0.5, cL = r.l > guard + 0.5;
  const railL = sL;
  const geR = we - railL;            // rail inner edge (ge)
  const ceR = geR - ci;              // content end (ce)
  const csL = ws + ci;               // content start (cs)
  const cc = VW / 2;
  const reading = Math.max( 0, Math.round( ceR - csL ) );
  const containerPx = Math.round( container );
  const gsGhost = ws + railL;

  const bt = 84, bb = 248;
  let s = `<svg class="cx-svg" viewBox="0 0 ${ VW } ${ H }" preserveAspectRatio="xMidYMid meet" role="img" aria-label="${ esc( __( 'Layout contract blueprint', '__plugin_txtd' ) ) }">`;

  // fills
  s += RC( 0.5, bt, VW - 1, bb - bt, 'cx-frame' );
  s += RC( ws, bt, container, bb - bt, 'cx-fill-c' );
  s += RC( csL, bt, reading, bb - bt, 'cx-fill-read' );
  s += RC( ws, bt, ci, bb - bt, 'cx-fill-inset' );
  s += RC( ceR, bt, ci, bb - bt, 'cx-fill-inset' );
  // right rail nested depth gauge
  s += RC( geR, bt, sL, bb - bt, 'cx-band', 0.09 );
  s += RC( we - sM, bt, sM, bb - bt, 'cx-band', 0.09 );
  s += RC( we - sS, bt, sS, bb - bt, 'cx-band', 0.09 );

  // content texture (orientation only)
  const ty = [ bt + 24, bt + 48, bt + 72, bt + 96, bt + 120 ];
  for ( let i = 0; i < ty.length; i++ ) {
    const w = ( i === ty.length - 1 ) ? reading * 0.42 : reading * ( 0.80 - i * 0.05 );
    if ( reading > 70 ) {
      s += LN( csL + 16, ty[ i ], csL + 16 + w, ty[ i ], 'cx-tex' );
    }
  }

  // named vertical lines
  s += LN( ws, bt, ws, bb, 'cx-line' );
  s += LN( csL, bt, csL, bb, 'cx-line' );
  s += LN( cc, bt - 6, cc, bb + 6, 'cx-center' );
  s += LN( ceR, bt, ceR, bb, 'cx-line' );
  s += LN( geR, bt, geR, bb, 'cx-line' );
  s += LN( we, bt, we, bb, 'cx-line' );

  // right rail step lines (S/M/L) graded; clamp dashed
  s += OLN( geR, bt, geR, bb, 'cx-step' + ( cL ? ' clamp' : '' ), 1 );
  s += OLN( we - sM, bt, we - sM, bb, 'cx-step' + ( cM ? ' clamp' : '' ), 0.78 );
  s += OLN( we - sS, bt, we - sS, bb, 'cx-step' + ( cS ? ' clamp' : '' ), 0.58 );

  // left mirror: one faint ghosted gutter line (gs) + a calm note
  s += '<g class="cx-mirror">';
  s += LN( gsGhost, bt, gsGhost, bb, 'cx-line faint' );
  s += T( gsGhost, bb - 8, 'gs', 'cx-nlab' );
  s += T( ws + ci + 8, ( bt + bb ) / 2, '◂ ' + __( 'Left Rail mirrors', '__plugin_txtd' ), 'cx-txt-sm', 'start' );
  s += '</g>';

  // named labels row (top), staggered onto two rows
  const labs = [ [ 16, 'fs', 'start' ], [ ws, 'ws' ], [ csL, 'cs' ], [ cc, 'cc' ], [ ceR, 'ce' ], [ geR, 'ge' ], [ we, 'we' ], [ VW - 16, 'fe', 'end' ] ];
  for ( let j = 0; j < labs.length; j++ ) {
    s += T( labs[ j ][ 0 ], ( j % 2 ? 32 : 18 ), labs[ j ][ 1 ], 'cx-nlab', labs[ j ][ 2 ] );
  }

  // container overall dimension (top)
  s += dim( ws, we, 60, __( 'site container', '__plugin_txtd' ) + ' · ' + cw + '% · ' + containerPx + 'px', 'cx-txt-acc' );

  // bottom dimension chain: reading | inset
  const yc = bb + 28;
  s += dim( csL, ceR, yc, __( 'reading', '__plugin_txtd' ) + ' ' + reading + 'px', 'cx-txt' );
  if ( ci > 0 ) {
    s += dim( ceR, geR, yc, __( 'inset', '__plugin_txtd' ) + ' ' + ci, 'cx-txt-sm' );
  }

  // rail depth ruler (the stepped gauge)
  const yr = bb + 62;
  s += LN( geR, yr, we, yr, 'cx-dim' );
  s += LN( we, yr - 7, we, yr + 7, 'cx-tick' );
  s += OLN( we - sS, yr - 7, we - sS, yr, 'cx-step', 0.58 );
  s += OLN( we - sM, yr - 7, we - sM, yr, 'cx-step', 0.78 );
  s += OLN( geR, yr - 7, geR, yr, 'cx-step', 1 );
  const lblS = cS ? ( r.s + '→' + Math.round( sS ) ) : ( '' + r.s );
  const lblM = cM ? ( r.m + '→' + Math.round( sM ) ) : ( '' + r.m );
  const lblL = cL ? ( r.l + '→' + Math.round( sL ) ) : ( '' + r.l );
  s += T( we - sS / 2, yr + 17, 'S ' + lblS, 'cx-txt-acc' );
  s += T( we - ( sS + sM ) / 2, yr + 31, 'M ' + lblM, 'cx-txt-acc' );
  s += T( ( geR + we - sM ) / 2, yr + 17, 'L ' + lblL, 'cx-txt-acc' );
  s += T( we, yr - 11, ( cL || cM || cS ) ? __( 'rail depth · clamped', '__plugin_txtd' ) + ' ◂' : __( 'rail depth', '__plugin_txtd' ) + ' ◂', 'cx-txt-sm', 'end' );

  s += '</svg>';

  // caption block
  const chip = ( op, letter, tok, eff, clamped ) =>
    `<span class="cx-chip${ clamped ? ' cl' : '' }"><i style="opacity:${ op }"></i>${ letter } <b>${ clamped ? ( tok + '→' + eff ) : tok }</b></span>`;
  const cap = '<div class="cx-caption">' +
    '<div class="cx-cap-title">' + esc( __( 'The Layout contract', '__plugin_txtd' ) ) + '</div>' +
    '<p>' + esc( __( 'Every page is built on one editorial grid. This panel sets three things — how wide the site container runs, how far the content is inset, and the three rail sizes — and every block and recipe reads from it. The named lines fs · ws · gs · cs · cc · ce · ge · we · fe are the grid; one rail is drawn to measure and the left simply mirrors it.', '__plugin_txtd' ) ) + '</p>' +
    '<div class="cx-chips"><span class="lead">' + esc( __( 'Rail scale', '__plugin_txtd' ) ) + '</span>' +
      chip( 0.42, 'S', r.s, Math.round( sS ), cS ) + chip( 0.68, 'M', r.m, Math.round( sM ), cM ) + chip( 1, 'L', r.l, Math.round( sL ), cL ) +
    '</div></div>';

  return s + cap;
};

// ---- Examples: the four shipped sidecar recipes ----
const layoutGeom = ( tokens, container, ci ) => {
  const G = Math.max( 24, Math.round( container * 0.035 ) );
  const avail = Math.max( container - 2 * ci, 120 );
  const floor = Math.max( container * 0.40, 240 );
  const perRailMax = container * 0.5 - G;
  let eff = tokens.map( t => Math.min( t, perRailMax ) );
  const nR = eff.length;
  let sumR = eff.reduce( ( a, b ) => a + b, 0 );
  let reading = avail - sumR - nR * G;
  if ( nR > 0 && reading < floor && sumR > 0 ) {
    const scale = Math.max( ( sumR - ( floor - reading ) ) / sumR, 0.28 );
    eff = eff.map( w => w * scale );
    sumR = eff.reduce( ( a, b ) => a + b, 0 );
    reading = avail - sumR - nR * G;
  }
  const clamped = eff.map( ( w, i ) => Math.round( w ) < tokens[ i ] );
  eff = eff.map( Math.round );
  return { eff, reading: Math.max( 1, Math.round( reading ) ), avail, insetPct: ci / container * 100, gapPct: G / avail * 100, clamped };
};
const basis = ( px, avail ) => ( px / avail * 100 );
const role = ( letter, token, eff ) => `<span class="lb">${ letter } ${ eff < token ? ( token + '&rarr;' + eff ) : token }</span>`;
const metaRail = bp => `<div class="lrail meta" style="flex-basis:${ bp }%"><span class="mdash"></span><span class="mdash sh"></span><div class="mdots"><span></span><span></span><span></span></div><span class="mdash sh"></span><div class="mdots"><span></span><span></span></div><span class="mdash"></span></div>`;
const cardRail = bp => `<div class="lrail right" style="flex-basis:${ bp }%"><div class="lcard"><span class="thumb"></span><span class="rl"></span><span class="rl sh"></span></div><div class="lcard"><span class="thumb"></span><span class="rl"></span></div></div>`;
const tile = ( name, desc, roles, geom, bodyInner, cw, foot ) =>
  '<div class="lay"><div class="lhead"><span class="dot"></span><span class="nav"></span><span class="nav c"></span></div>' +
  '<div class="lviewport"><div class="lcontainer" style="width:' + cw + '%"><div class="lpad" style="padding-left:' + geom.insetPct + '%;padding-right:' + geom.insetPct + '%;gap:' + geom.gapPct + '%">' + bodyInner + '</div></div></div>' +
  '<div class="lcap"><div class="ln">' + esc( name ) + '</div><div class="ld">' + esc( desc ) + ' · ' + wp.i18n.__( 'uses', '__plugin_txtd' ) + ' ' + roles + '</div>' + ( foot ? '<div class="foot">' + esc( foot ) + '</div>' : '' ) + '</div></div>';

const buildExamples = ( r, cw, ci ) => {
  const { __ } = wp.i18n;
  const cont = Math.round( VIEWPORT * cw / 100 );
  const clampSuffix = g => g.clamped.some( Boolean ) ? ' · <span class="clampflag">' + esc( __( 'clamped', '__plugin_txtd' ) ) + '</span>' : '';

  const g1 = layoutGeom( [ r.m ], cont, ci );
  const t1 = tile( __( 'Right Rail', '__plugin_txtd' ), __( 'the classic article', '__plugin_txtd' ), role( 'M', r.m, g1.eff[ 0 ] ) + clampSuffix( g1 ), g1,
    '<div class="lread article"><span class="h"></span><div class="img"></div><i></i><i class="s"></i><i></i><i class="u"></i></div>' + cardRail( basis( g1.eff[ 0 ], g1.avail ) ), cw );

  const g2 = layoutGeom( [ r.s, r.m ], cont, ci );
  const t2 = tile( __( 'Hive', '__plugin_txtd' ), __( 'three-column magazine', '__plugin_txtd' ),
    role( 'S', r.s, g2.eff[ 0 ] ) + ' + ' + role( 'M', r.m, g2.eff[ 1 ] ) + clampSuffix( g2 ), g2,
    metaRail( basis( g2.eff[ 0 ], g2.avail ) ) + '<div class="lread hive"><div class="hcol"><span class="h" style="width:82%"></span><i></i><i class="s"></i><i></i></div><div class="hcol"><i></i><i class="s"></i><i></i><i class="u"></i></div></div>' + cardRail( basis( g2.eff[ 1 ], g2.avail ) ),
    cw, __( 'left rail uses Small — per-side scales are a future refinement', '__plugin_txtd' ) );

  const offset = Math.round( cont * 0.12 );
  const g3 = layoutGeom( [ offset, r.l ], cont, ci );
  const t3 = tile( __( 'Offset Editorial', '__plugin_txtd' ), __( 'asymmetric gutter, wide rail', '__plugin_txtd' ), role( 'L', r.l, g3.eff[ 1 ] ) + clampSuffix( g3 ), g3,
    '<div class="loffset" style="flex-basis:' + basis( g3.eff[ 0 ], g3.avail ) + '%"></div><div class="lread article"><span class="h"></span><i></i><i class="s"></i><div class="img"></div><i></i><i class="u"></i></div>' + cardRail( basis( g3.eff[ 1 ], g3.avail ) ), cw );

  const g4 = layoutGeom( [], cont, ci );
  const t4 = tile( __( 'Centered', '__plugin_txtd' ), __( 'pure reading page', '__plugin_txtd' ), __( 'no rails', '__plugin_txtd' ) + ' · ' + __( 'reading', '__plugin_txtd' ) + ' ' + g4.reading + 'px', g4,
    '<div class="lread center"><div class="cwrap"><span class="h" style="width:62%"></span><i></i><i class="s"></i><i></i><i></i><i class="u"></i></div></div>', cw );

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
  const [ spacingLevel, setSpacingLevel ] = useState( () => numOr( getSettingValue( 'sm_spacing_level', 1 ), 1 ) );
  const [ view, setView ] = useState( 'contract' );

  useCustomizeSettingCallback( 'sm_site_container_width', v => setContainerWidth( numOr( v, 75 ) ) );
  useCustomizeSettingCallback( 'sm_content_inset', v => setContentInset( numOr( v, 230 ) ) );
  useCustomizeSettingCallback( 'sm_rail_scale', v => setBase( v ) );
  useCustomizeSettingCallback( 'sm_rail_pitch', v => setPitch( v ) );
  useCustomizeSettingCallback( 'sm_spacing_level', v => setSpacingLevel( numOr( v, 1 ) ) );

  const baseStep = Math.round( BASE_STEP * spacingLevel );

  // Resolve rails; when untouched (both unset) show the legacy fallback so the
  // board matches the real frontend rendering (Small = content inset, M 330, L 400).
  const resolved = railWidths( base, pitch );
  const r = resolved || { s: Math.round( contentInset ), m: 330, l: 400, mult: 330 / 288 };
  const touched = !! resolved;

  const cw = Math.round( containerWidth );
  const ci = Math.round( contentInset );
  const board = view === 'contract' ? buildContractSvg( r, cw, ci ) : buildExamples( r, cw, ci );

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
        { ! touched && (
          <p className="sm-layout-preview__default-note">
            { __( 'Showing the current default — Small follows the content inset until you set a rail scale.', '__plugin_txtd' ) }
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
