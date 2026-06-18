import React, { useState } from 'react';

import { Overlay } from '../index';
import useCustomizeSettingCallback from '../../hooks/use-customize-setting-callback';

import './style.scss';

/**
 * The Spacing system board. Same principles as the Colors / Typography
 * boards: enumerate the token space, bind to the engine, show resolved values.
 *
 * The numbers mirror the theme's own relationships:
 *   --theme-spacing-ratio: var(--sm-spacing-level)
 *   --spacing-y1:          calc(32 * var(--theme-spacing-ratio))
 */

const BASE_STEP = 32;

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
      <SpacingPreview key={ 'overlay_spacing_preview' } />
    </Overlay>
  );
};

const SpacingPreview = () => {
  const { __ } = wp.i18n;

  const [ containerWidth, setContainerWidth ] = useState( () => getSettingValue( 'sm_site_container_width', 75 ) );
  const [ contentInset, setContentInset ] = useState( () => getSettingValue( 'sm_content_inset', 230 ) );
  const [ spacingLevel, setSpacingLevel ] = useState( () => getSettingValue( 'sm_spacing_level', 1 ) );

  useCustomizeSettingCallback( 'sm_site_container_width', newValue => setContainerWidth( parseFloat( newValue ) || 75 ) );
  useCustomizeSettingCallback( 'sm_content_inset', newValue => setContentInset( parseFloat( newValue ) || 230 ) );
  useCustomizeSettingCallback( 'sm_spacing_level', newValue => {
    const value = parseFloat( newValue );
    setSpacingLevel( isNaN( value ) ? 1 : value );
  } );

  const baseStep = Math.round( BASE_STEP * spacingLevel );

  // The rhythm steps the theme derives from the base unit.
  const steps = [
    { label: '½×', factor: 0.5 },
    { label: '1×', factor: 1 },
    { label: '2×', factor: 2 },
    { label: '3×', factor: 3 },
  ];

  // Blueprint scale: the schematic viewport is rendered at a fixed width and
  // annotated with the REAL values — only the drawing is scaled.
  const insetScale = 0.2;

  return (
    <div className="sm-spacing-preview">
      <div className="sm-spacing-preview__header">
        <h1>{ __( 'Spacing & rhythm', '__plugin_txtd' ) }</h1>
        <p>
          { __( 'The spacing system sets how your layout breathes: how wide the site container stretches, how far content is inset within it, and the rhythm between elements. Adjust the options and watch the resolved values.', '__plugin_txtd' ) }
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
              <div
                className="sm-spacing-preview__content"
                style={ { marginLeft: `${ contentInset * insetScale }px`, marginRight: `${ contentInset * insetScale }px` } }
              >
                <div className="sm-spacing-preview__measure sm-spacing-preview__measure--inset">
                  <span>{ __( 'Content Inset', '__plugin_txtd' ) } · { contentInset }</span>
                </div>
                <div className="sm-spacing-preview__content-lines">
                  <span /><span /><span style={ { width: '60%' } } />
                </div>
              </div>
            </div>
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
