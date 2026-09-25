import test from 'node:test';
import assert from 'node:assert/strict';

import {
  RAIL_SMALL_DEFAULT,
  contractGeometry,
  readPreviewEnvironment,
  railWidths,
  resolveRails,
  scaledInset,
} from '../../src/_js/customizer/components/spacing-overlay/contract-geometry.js';

// The Layout board prints the page's numbers (nova-blocks#655): its geometry is
// the JS twin of Nova Blocks' layout engine. The expectations below are the
// rendered title/Post Content column (left, right, width) measured on a fresh
// sidecar-lab site (Anima LT + Nova Blocks + Style Manager, Site Container 75,
// default typography: a 15px body below 1440) with the `template-single-*`
// fixtures, one Small rail per railed side.

// The lab's runtime inputs: 15px root/body below 1440 (the 15/16 body scale),
// fluid above; `--nb-spacing` (= sides) runs 20px at 320 to 32px at 1440.
const spacingAt = w => 20 + ( w - 320 ) * 12 / 1120;
const labEnv = w => {
  const font = w >= 1440 ? 0.9375 * ( 16 + w / 100 - 14.4 ) : 15;
  return { viewport: w, rootFontSize: font, bodyFontSize: font, spacing: spacingAt( w ), sides: spacingAt( w ) };
};

const RAILS = { right: [ null, 230 ], left: [ 230, null ], both: [ 230, 230 ], railless: [ null, null ] };

const board = ( { w, inset, fx } ) => contractGeometry( {
  env: labEnv( w ),
  containerSetting: 75,
  insetSetting: inset ?? 230,
  explicit: null != inset,
  railLeft: RAILS[ fx ][ 0 ],
  railRight: RAILS[ fx ][ 1 ],
} );

// [ inset (null = never saved), fixture, viewport, page cs, page ce, page reading ]
const MEASURED = [
  [ null, 'right', 1280, 77.5, 926.3, 848.8 ],
  [ null, 'right', 1440, 157.5, 1002.9, 845.4 ],
  [ null, 'both', 1440, 437.1, 1002.9, 565.8 ],
  [ null, 'railless', 1280, 353.7, 926.3, 572.6 ],
  [ null, 'right', 1920, 228.8, 1336.6, 1107.9 ],
  [ 150, 'right', 1280, 218.1, 846.2, 628.1 ],
  [ 150, 'railless', 1440, 298.1, 1141.9, 843.8 ],
  [ 230, 'right', 1280, 293.1, 771.2, 478.1 ],
  [ 230, 'right', 1440, 373.1, 851.3, 478.1 ],
  [ 230, 'left', 1440, 588.8, 1066.9, 478.1 ],
  [ 230, 'both', 1280, 508.8, 771.2, 262.5 ],
  [ 230, 'railless', 1440, 373.1, 1066.9, 693.8 ],
  [ 230, 'right', 1920, 509.1, 1130.6, 621.5 ],
  [ 300, 'right', 1440, 438.8, 785.6, 346.9 ],
  [ 300, 'both', 1280, 549.2, 730.8, 181.7 ],
  // Narrow screens: the inset ramps down below 1280 (half the setting at 1024).
  [ 230, 'right', 1024, 142.6, 665.8, 523.3 ],
  [ 230, 'both', 1024, 358.2, 665.8, 307.6 ],
  [ 300, 'right', 1024, 177.6, 630.8, 453.3 ],
  [ 230, 'railless', 1180, 214.3, 965.7, 751.4 ],
];

for ( const [ inset, fx, w, cs, ce, reading ] of MEASURED ) {
  test( `board matches the page within 1px: inset ${ inset ?? 'unset' }, ${ fx }, ${ w }px`, () => {
    const g = board( { w, inset, fx } );
    assert.ok( Math.abs( g.cs - cs ) < 1, `cs ${ g.cs } vs page ${ cs }` );
    assert.ok( Math.abs( g.ce - ce ) < 1, `ce ${ g.ce } vs page ${ ce }` );
    assert.ok( Math.abs( g.reading - reading ) < 1, `reading ${ g.reading } vs page ${ reading }` );
  } );
}

test( 'the container is Site Container x the root font size, not a % of the viewport', () => {
  const g = board( { w: 1280, inset: 230, fx: 'right' } );
  assert.equal( g.container, 75 * 15 );
  assert.equal( g.containerCapped, false );
  // The drifted board model (75% of a 1280 viewport, Large rail, no font scale) printed 250 here.
  assert.notEqual( Math.round( g.reading ), 250 );
  assert.equal( Math.round( g.reading ), 478 );
} );

test( 'the container gives way to the viewport gutters on narrow screens', () => {
  const g = board( { w: 1024, inset: 230, fx: 'right' } );
  assert.ok( g.containerCapped );
  assert.ok( Math.abs( g.container - ( 1024 - 2 * spacingAt( 1024 ) ) ) < 1e-9 );
} );

test( 'untouched rails are Small 230, Medium 330, Large 400 — Small no longer follows Content Inset', () => {
  const r = resolveRails( '', '' );
  assert.deepEqual( [ r.s, r.m, r.l, r.touched ], [ RAIL_SMALL_DEFAULT, 330, 400, false ] );
  assert.equal( RAIL_SMALL_DEFAULT, 230 );
  const touched = resolveRails( 300, 22 );
  assert.equal( touched.touched, true );
  assert.equal( touched.s, 300 );
} );

test( 'rails are font-relative and clamped at half the container minus 1.5 rail gaps', () => {
  const g = contractGeometry( { env: labEnv( 1440 ), containerSetting: 75, insetSetting: 230, explicit: true, railRight: 400 } );
  assert.ok( Math.abs( g.railRightPx - 400 * 15 / 16 ) < 1e-9 );
  const tight = contractGeometry( { env: labEnv( 1440 ), containerSetting: 60, insetSetting: 230, explicit: true, railRight: 600 } );
  assert.ok( Math.abs( tight.railRightPx - ( 60 * 15 * 0.5 - 64 * 1.5 ) ) < 1e-9 );
} );

test( 'the inset is exact from 1280 up and ramps to half the setting at 1024', () => {
  assert.equal( scaledInset( 230, 15, 1280 ), 230 * 15 / 16 );
  assert.equal( scaledInset( 230, 17, 1280 ), 230 * 17 / 16 );
  assert.equal( scaledInset( 230, 15, 1920 ), 230 * 15 / 16 );
  assert.equal( scaledInset( 230, 15, 1024 ), 115 );
  assert.equal( scaledInset( 230, 15, 1248 ), 230 * 15 / 16 );
} );

test( 'an unsaved Content Inset is not applied (the page keeps the rail gap)', () => {
  const unsaved = board( { w: 1440, inset: null, fx: 'right' } );
  assert.equal( unsaved.explicit, false );
  assert.equal( unsaved.inset, 0 );
  assert.ok( Math.abs( unsaved.ge - unsaved.ce - 64 ) < 1e-9 );
  assert.equal( unsaved.cs, unsaved.ws );
} );

test( 'the inset cap keeps three rail gaps of reading column', () => {
  const g = contractGeometry( { env: labEnv( 1024 ), containerSetting: 75, insetSetting: 300, explicit: true, railLeft: 300, railRight: 300 } );
  assert.ok( g.insetCapped );
  assert.ok( Math.abs( g.reading - 3 * g.gap ) < 1e-9 );
} );

test( 'the Rail Gap setting scales the gap beside a rail', () => {
  const g = contractGeometry( { env: labEnv( 1440 ), containerSetting: 75, insetSetting: 230, explicit: false, railGap: 3, railRight: 230 } );
  assert.ok( Math.abs( g.ge - g.ce - 96 ) < 1e-9 );
} );

// ---- runtime inputs from the preview document ----

const fakePreview = ( { props, lengths, clientWidth = 1280, innerWidth = 1280 } ) => {
  const root = {
    clientWidth,
    appendChild( el ) {
      el.parent = this;
    },
  };
  return {
    innerWidth,
    document: {
      documentElement: root,
      createElement: () => {
        const el = { style: {}, remove() {} };
        el.getBoundingClientRect = () => {
          const name = /var\((--[\w-]+)\)/.exec( el.style.cssText )[ 1 ];
          return { width: lengths[ name ] };
        };
        return el;
      },
    },
    getComputedStyle: () => ( { getPropertyValue: name => props[ name ] ?? '' } ),
  };
};

test( 'reads font sizes, spacing, width and the saved-inset signal from the preview', () => {
  const env = readPreviewEnvironment( fakePreview( {
    props: {
      '--current-font-size': 'calc(...)',
      '--theme-body-final-font-size': 'calc(...)',
      '--nb-spacing': 'calc(...)',
      '--nb-wrapper-sides-spacings': 'calc(...)',
      '--sm-content-inset-explicit': ' 1',
    },
    lengths: {
      '--current-font-size': 15,
      '--theme-body-final-font-size': 15,
      '--nb-spacing': 30.29,
      '--nb-wrapper-sides-spacings': 30.29,
    },
  } ) );
  assert.deepEqual( env, { viewport: 1280, vw: 1280, rootFontSize: 15, bodyFontSize: 15, spacing: 30.29, sides: 30.29, explicit: true } );
} );

test( 'without the signal or a preview the board falls back safely', () => {
  const env = readPreviewEnvironment( fakePreview( { props: {}, lengths: {} } ) );
  assert.equal( env.explicit, false );
  assert.equal( env.rootFontSize, 16 );
  assert.equal( readPreviewEnvironment( null ), null );
} );

test( 'beside a rail the rail gap is the minimum when the inset is smaller', () => {
  const g = contractGeometry( { env: labEnv( 1440 ), containerSetting: 75, insetSetting: 40, explicit: true, railRight: 230 } );
  assert.ok( Math.abs( g.ge - g.ce - 64 ) < 1e-9, 'rail gap wins beside the rail' );
  assert.ok( Math.abs( g.cs - g.ws - 40 * 15 / 16 ) < 1e-9, 'the free side takes the inset' );
} );

// Small-only rail (nova-blocks#655, H-S6): `sm_rail_small` sets Small while the
// Rail Scale is untouched; Medium and Large stay on their defaults.
test( 'a Small-only value sets Small and leaves Medium and Large on their defaults', () => {
  const r = resolveRails( '', '', 180 );
  assert.equal( r.s, 180 );
  assert.equal( r.m, 330 );
  assert.equal( r.l, 400 );
  assert.equal( r.touched, false );
  assert.equal( r.smallOnly, true );
  assert.deepEqual( railWidths( '', '', '180' ), { s: 180, m: null, l: null, mult: 330 / 288 } );
  // Kept exact, like the inset it replaces.
  assert.equal( resolveRails( '', '', 187.5 ).s, 187.5 );
} );

test( 'a touched Rail Scale owns all three sizes and ignores the Small-only value', () => {
  assert.deepEqual( resolveRails( 288, '', 180 ), resolveRails( 288, '' ) );
  assert.deepEqual( resolveRails( 250, 16, 180 ), resolveRails( 250, 16 ) );
  assert.deepEqual( resolveRails( '', 0, 180 ), resolveRails( '', 0 ) );
} );

test( 'an empty or invalid Small-only value keeps the untouched defaults', () => {
  for ( const raw of [ '', null, undefined, 0, '0', -5, 'wide' ] ) {
    const r = resolveRails( '', '', raw );
    assert.deepEqual( [ r.s, r.m, r.l, r.smallOnly ], [ 230, 330, 400, false ], String( raw ) );
  }
} );

test( 'the rail-less legacy budget follows the Small-only value', () => {
  const pinned = contractGeometry( { env: labEnv( 1440 ), containerSetting: 75, insetSetting: 230, explicit: false, railSmall: resolveRails( '', '', 180 ).s } );
  const base = contractGeometry( { env: labEnv( 1440 ), containerSetting: 75, insetSetting: 230, explicit: false } );
  // 50 rail tokens narrower on each side at the 15/16 body scale.
  assert.ok( Math.abs( ( pinned.reading - base.reading ) - 2 * 50 * labEnv( 1440 ).bodyFontSize / 16 ) < 0.01 );
} );
