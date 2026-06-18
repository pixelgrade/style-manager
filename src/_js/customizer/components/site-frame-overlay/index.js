import React, { useState } from 'react';

import { Overlay } from '../index';
import useCustomizeSettingCallback from '../../hooks/use-customize-setting-callback';

import './style.scss';

/**
 * The Site Frame board. Same principles as the Colors / Typography / Spacing
 * boards: preview the system on neutral ground, bind to the engine, show
 * resolved values.
 *
 * The frame is whole-page chrome (a top bar, a left bar and a docked nav
 * rail) tinted by a color grade. It is static chrome, not a behaviour, so a
 * board — not the Live Site flow — is the right surface. Colors resolve
 * through the REAL cascade: the schematic carries `.sm-palette-{id}
 * .sm-variation-{n}` so `--site-frame-surface: var(--sm-current-bg-color)`
 * paints it exactly as the frontend `_site-frame.scss` does.
 */

const getSettingValue = ( settingID, fallback ) => {
  if ( ! window.wp?.customize ) {
    return fallback;
  }

  const setting = window.wp.customize( settingID );
  const value = setting ? setting() : undefined;

  return ( undefined === value || null === value || '' === value ) ? fallback : value;
};

// The computed color system (every palette with its id, label and sourceIndex)
// — the same source the engine and Customizer read.
const getPalettes = () => {
  try {
    const arr = JSON.parse( getSettingValue( 'sm_advanced_palette_output', '[]' ) );
    return Array.isArray( arr ) ? arr : [];
  } catch ( e ) {
    return [];
  }
};

const getPalette = paletteId => getPalettes().find(
  palette => palette && String( palette.id ) === String( paletteId )
) || null;

// Mirrors anima/inc/site-frame.php → resolveAccentVariation(): the `accent`
// grade resolves to the palette's source grade shifted by the site variation.
const resolveAccentVariation = paletteId => {
  let siteVar = parseInt( getSettingValue( 'sm_site_color_variation', 1 ), 10 );
  if ( ! siteVar || siteVar < 1 ) {
    siteVar = 1;
  }

  const palette = getPalette( paletteId );
  let sourceIndex = palette ? parseInt( palette.sourceIndex, 10 ) : NaN;
  if ( isNaN( sourceIndex ) ) {
    sourceIndex = 3;
  }

  return ( ( sourceIndex - siteVar + 1 + 12 ) % 12 ) + 1;
};

// Mirrors anima/inc/site-frame.php → applySiteFrameState() variation resolution.
const resolveVariation = ( paletteId, variationRaw ) => {
  if ( 'accent' === variationRaw ) {
    return { variation: resolveAccentVariation( paletteId ), isAccent: true };
  }

  let variation = parseInt( variationRaw, 10 );
  if ( ! variation || variation < 1 || variation > 12 ) {
    variation = 11;
  }

  return { variation, isAccent: false };
};

const getPaletteLabel = paletteId => {
  const palette = getPalette( paletteId );
  return palette && palette.label ? palette.label : paletteId;
};

const SiteFrameOverlay = props => {
  const { show } = props;

  return (
    <Overlay show={ show }>
      <SiteFramePreview key={ 'overlay_site_frame_preview' } />
    </Overlay>
  );
};

const SiteFramePreview = () => {
  const { __ } = wp.i18n;

  const [ style, setStyle ] = useState( () => getSettingValue( 'sm_site_frame_style', 'none' ) );
  const [ paletteId, setPaletteId ] = useState( () => getSettingValue( 'sm_site_frame_palette', '' ) );
  const [ variationRaw, setVariationRaw ] = useState( () => getSettingValue( 'sm_site_frame_variation', 'accent' ) );
  const [ isDark, setIsDark ] = useState( false );

  useCustomizeSettingCallback( 'sm_site_frame_style', newValue => setStyle( newValue || 'none' ) );
  useCustomizeSettingCallback( 'sm_site_frame_palette', newValue => setPaletteId( newValue || '' ) );
  useCustomizeSettingCallback( 'sm_site_frame_variation', newValue => setVariationRaw( newValue || '11' ) );

  const enabled = 'none' !== style;
  const { variation, isAccent } = resolveVariation( paletteId, variationRaw );

  const frameClasses = [
    'sm-site-frame-preview__frame',
    paletteId ? `sm-palette-${ paletteId }` : '',
    `sm-variation-${ variation }`,
    isAccent ? 'has-site-frame-accent' : '',
    isDark ? 'is-dark' : '',
    enabled ? 'is-enabled' : 'is-disabled',
  ].filter( Boolean ).join( ' ' );

  const gradeLabel = isAccent
    ? __( 'Accent grade', '__plugin_txtd' )
    : `${ __( 'Grade', '__plugin_txtd' ) } ${ variation }`;

  return (
    <div className="sm-site-frame-preview">
      <div className="sm-site-frame-preview__header">
        <h1>{ __( 'Site Frame', '__plugin_txtd' ) }</h1>
        <p>
          { __( 'A reusable editorial frame around your whole site — a top bar, a left rail and a docked navigation rail — tinted by a color grade. Pick a palette and grade and watch the frame retint, exactly as the frontend resolves it.', '__plugin_txtd' ) }
        </p>
      </div>

      <div className="sm-site-frame-preview__stage">
        <div className={ frameClasses }>
          <span className="sm-site-frame-preview__bar sm-site-frame-preview__bar--top" aria-hidden="true" />
          <span className="sm-site-frame-preview__bar sm-site-frame-preview__bar--left" aria-hidden="true" />
          <div className="sm-site-frame-preview__rail" aria-hidden="true">
            <span className="sm-site-frame-preview__monogram">A</span>
            <ul className="sm-site-frame-preview__menu">
              <li /><li /><li />
            </ul>
          </div>

          <div className="sm-site-frame-preview__page">
            <span className="sm-site-frame-preview__line sm-site-frame-preview__line--title" />
            <span className="sm-site-frame-preview__line" />
            <span className="sm-site-frame-preview__line" />
            <span className="sm-site-frame-preview__line" style={ { width: '55%' } } />
          </div>

          { ! enabled && (
            <div className="sm-site-frame-preview__off">
              { __( 'Site Frame is off — set Style to Editorial to frame your site.', '__plugin_txtd' ) }
            </div>
          ) }
        </div>
      </div>

      <div className="sm-site-frame-preview__footer">
        <button
          type="button"
          className={ `sm-site-frame-preview__mode ${ isDark ? 'is-dark' : 'is-light' }` }
          onClick={ () => setIsDark( prev => ! prev ) }
        >
          { isDark ? __( 'Dark mode', '__plugin_txtd' ) : __( 'Light mode', '__plugin_txtd' ) }
        </button>
        { enabled && (
          <span className="sm-site-frame-preview__resolved">
            { paletteId
              ? `${ getPaletteLabel( paletteId ) } · ${ gradeLabel }`
              : gradeLabel }
          </span>
        ) }
      </div>
    </div>
  );
};

export default SiteFrameOverlay;
