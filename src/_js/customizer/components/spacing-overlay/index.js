import React, { useState } from 'react';

import { Overlay } from '../index';
import useCustomizeSettingCallback from '../../hooks/use-customize-setting-callback';

import './style.scss';

/**
 * The Layout system board. Same principles as the Colors / Typography boards:
 * enumerate the token space, bind to the engine, show resolved values. It now
 * covers the whole page-anatomy story (the Spacing section merged into Layout):
 * container width, content inset, the rail scale (sidebar widths), and rhythm.
 *
 * The numbers mirror the theme's own relationships:
 *   --theme-spacing-ratio: var(--sm-spacing-level)
 *   --spacing-y1:          calc(32 * var(--theme-spacing-ratio))
 *   --sm-rail-{small,medium,large}: derived from the rail base at fixed ratios
 *     anchored on 288 (so base 288 → 288 / 330 / 400).
 */

const BASE_STEP = 32;

// Rail scale: Small = base, Medium/Large scale up at fixed ratios anchored so
// base 288 reproduces the legacy 288 / 330 / 400 rail widths. Keep in sync with
// sm_rail_scale_css_cb (PHP + preview JS twins).
const RAIL_ANCHOR = 288;
const RAIL_MEDIUM_RATIO = 330 / 288;
const RAIL_LARGE_RATIO = 400 / 288;
const RAIL_LEGACY_MEDIUM = 330;
const RAIL_LEGACY_LARGE = 400;

const getSettingValue = ( settingID, fallback ) => {
  if ( ! window.wp?.customize ) {
    return fallback;
  }

  const setting = window.wp.customize( settingID );
  const value = setting ? parseFloat( setting() ) : NaN;

  return isNaN( value ) ? fallback : value;
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

  const [ containerWidth, setContainerWidth ] = useState( () => getSettingValue( 'sm_site_container_width', 75 ) );
  const [ contentInset, setContentInset ] = useState( () => getSettingValue( 'sm_content_inset', 230 ) );
  const [ spacingLevel, setSpacingLevel ] = useState( () => getSettingValue( 'sm_spacing_level', 1 ) );
  const [ railBase, setRailBase ] = useState( () => getSettingValue( 'sm_rail_scale', 0 ) );

  useCustomizeSettingCallback( 'sm_site_container_width', newValue => setContainerWidth( parseFloat( newValue ) || 75 ) );
  useCustomizeSettingCallback( 'sm_content_inset', newValue => setContentInset( parseFloat( newValue ) || 230 ) );
  useCustomizeSettingCallback( 'sm_spacing_level', newValue => {
    const value = parseFloat( newValue );
    setSpacingLevel( isNaN( value ) ? 1 : value );
  } );
  useCustomizeSettingCallback( 'sm_rail_scale', newValue => {
    const value = parseFloat( newValue );
    setRailBase( isNaN( value ) ? 0 : value );
  } );

  const baseStep = Math.round( BASE_STEP * spacingLevel );

  // Rail widths. Once the rail scale is touched (base > 0) all three derive from
  // the base; until then the legacy values hold — Small tracks the content inset
  // (the pre-token coupling), Medium 330, Large 400.
  const railActive = railBase > 0;
  const railSmall = railActive ? Math.round( railBase ) : Math.round( contentInset );
  const railMedium = railActive ? Math.round( railBase * RAIL_MEDIUM_RATIO ) : RAIL_LEGACY_MEDIUM;
  const railLarge = railActive ? Math.round( railBase * RAIL_LARGE_RATIO ) : RAIL_LEGACY_LARGE;

  const rails = [
    { key: 'l', label: __( 'L', '__plugin_txtd' ), value: railLarge },
    { key: 'm', label: __( 'M', '__plugin_txtd' ), value: railMedium },
    { key: 's', label: __( 'S', '__plugin_txtd' ), value: railSmall },
  ];

  // The rhythm steps the theme derives from the base unit.
  const steps = [
    { label: '½×', factor: 0.5 },
    { label: '1×', factor: 1 },
    { label: '2×', factor: 2 },
    { label: '3×', factor: 3 },
  ];

  // Blueprint scale: the schematic viewport is rendered at a fixed width and
  // annotated with the REAL values — only the drawing is scaled. Rails reserve
  // their Large width on each side, so the reading column narrows as the scale
  // grows (the real per-container behavior, clamped in the engine at 50%).
  const insetScale = 0.14;
  const railScale = 0.13;
  const railReserve = Math.round( railLarge * railScale );

  return (
    <div className="sm-spacing-preview">
      <div className="sm-spacing-preview__header">
        <h1>{ __( 'Layout', '__plugin_txtd' ) }</h1>
        <p>
          { __( 'Layout sets your page anatomy: how wide the site container stretches, how far content is inset, how wide the sidebars and rails are, and the rhythm between elements. Adjust the options and watch the resolved values.', '__plugin_txtd' ) }
        </p>
      </div>

      <div className="sm-spacing-preview__section">
        <h2>{ __( 'Page anatomy', '__plugin_txtd' ) }</h2>
        <div className="sm-spacing-preview__blueprint">
          <div className="sm-spacing-preview__viewport">
            <span className="sm-spacing-preview__viewport-label">{ __( 'Viewport', '__plugin_txtd' ) }</span>
            <div className="sm-spacing-preview__container" style={ { width: `${ containerWidth }%` } }>
              <div className="sm-spacing-preview__measure sm-spacing-preview__measure--container">
                <span>{ __( 'Site Container', '__plugin_txtd' ) } · { containerWidth }%</span>
              </div>
              <div className="sm-spacing-preview__measure sm-spacing-preview__measure--inset">
                <span>{ __( 'Content Inset', '__plugin_txtd' ) } · { contentInset }</span>
              </div>
              <div className="sm-spacing-preview__anatomy">
                <div className="sm-spacing-preview__rail sm-spacing-preview__rail--left" style={ { width: `${ railReserve }px` } }>
                  { rails.map( rail => (
                    <span
                      key={ rail.key }
                      className={ `sm-spacing-preview__rail-band sm-spacing-preview__rail-band--${ rail.key }` }
                      style={ { width: `${ Math.round( rail.value * railScale ) }px` } }
                    >
                      <em>{ rail.label }</em>
                    </span>
                  ) ) }
                </div>
                <div
                  className="sm-spacing-preview__content"
                  style={ { paddingLeft: `${ contentInset * insetScale }px`, paddingRight: `${ contentInset * insetScale }px` } }
                >
                  <div className="sm-spacing-preview__content-lines">
                    <span /><span /><span style={ { width: '60%' } } />
                  </div>
                </div>
                <div className="sm-spacing-preview__rail sm-spacing-preview__rail--right" style={ { width: `${ railReserve }px` } }>
                  { rails.map( rail => (
                    <span
                      key={ rail.key }
                      className={ `sm-spacing-preview__rail-band sm-spacing-preview__rail-band--${ rail.key }` }
                      style={ { width: `${ Math.round( rail.value * railScale ) }px` } }
                    >
                      <em>{ rail.label }</em>
                    </span>
                  ) ) }
                </div>
              </div>
            </div>
          </div>
          <div className="sm-spacing-preview__rail-legend">
            <span className="sm-spacing-preview__rail-legend-title">{ __( 'Rail scale', '__plugin_txtd' ) }</span>
            <span className="sm-spacing-preview__rail-legend-item"><em className="sm-spacing-preview__rail-swatch sm-spacing-preview__rail-band--s" />{ __( 'Small', '__plugin_txtd' ) } · { railSmall }</span>
            <span className="sm-spacing-preview__rail-legend-item"><em className="sm-spacing-preview__rail-swatch sm-spacing-preview__rail-band--m" />{ __( 'Medium', '__plugin_txtd' ) } · { railMedium }</span>
            <span className="sm-spacing-preview__rail-legend-item"><em className="sm-spacing-preview__rail-swatch sm-spacing-preview__rail-band--l" />{ __( 'Large', '__plugin_txtd' ) } · { railLarge }</span>
            { ! railActive && (
              <span className="sm-spacing-preview__rail-legend-note">{ __( 'Default — Small follows the content inset until you set a rail scale.', '__plugin_txtd' ) }</span>
            ) }
          </div>
        </div>
      </div>

      <div className="sm-spacing-preview__columns">
        <div className="sm-spacing-preview__section">
          <h2>{ __( 'Vertical rhythm', '__plugin_txtd' ) }</h2>
          <p className="sm-spacing-preview__hint">
            { __( 'Every distance between elements is a multiple of the base step.', '__plugin_txtd' ) }
            { ' ' }
            <strong>{ __( 'Base step', '__plugin_txtd' ) }: { baseStep }px</strong>
            { ' ' }({ __( 'Level', '__plugin_txtd' ) } { spacingLevel })
          </p>
          <div className="sm-spacing-preview__ladder">
            { steps.map( step => {
              const px = Math.round( baseStep * step.factor );
              return (
                <div className="sm-spacing-preview__step" key={ step.label }>
                  <span className="sm-spacing-preview__step-label">{ step.label }</span>
                  <span className="sm-spacing-preview__step-bar" style={ { width: `${ px * 2 }px` } } />
                  <span className="sm-spacing-preview__step-value">{ px }px</span>
                </div>
              );
            } ) }
          </div>
        </div>

        <div className="sm-spacing-preview__section">
          <h2>{ __( 'Density', '__plugin_txtd' ) }</h2>
          <p className="sm-spacing-preview__hint">
            { __( 'The same content at the current spacing level.', '__plugin_txtd' ) }
          </p>
          <div className="sm-spacing-preview__demo" style={ { gap: `${ baseStep }px` } }>
            { [ 1, 2, 3 ].map( card => (
              <div className="sm-spacing-preview__card" style={ { padding: `${ Math.round( baseStep * 0.75 ) }px` } } key={ card }>
                <span className="sm-spacing-preview__card-title" />
                <span className="sm-spacing-preview__card-line" />
                <span className="sm-spacing-preview__card-line" style={ { width: '70%' } } />
              </div>
            ) ) }
          </div>
        </div>
      </div>
    </div>
  );
};

export default SpacingOverlay;
