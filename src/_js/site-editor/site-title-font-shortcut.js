/* global wp */
import React from 'react';

import { FontFamilyControl, pickFontFamily } from './font-control';
import { findFontControl, resolveThemeSettingId } from './font-setting-adapter';
import { plusUpsellUrl } from './upsell-url';

const SETTING_BASENAME = 'site_title_font';
const FILTER_NAMESPACE = 'pixelgrade-style-manager/site-title-font-shortcut';

const getRecommendedFamilies = control => {
  if ( ! control ) {
    return [];
  }

  const groups = control.querySelectorAll( 'select.style-manager_font_family optgroup' );
  const recommended = Array.from( groups )
    .find( group => 'recommended' === group.label.trim().toLowerCase() );

  return recommended
    ? Array.from( recommended.querySelectorAll( 'option' ) ).map( option => option.value )
    : [];
};

const isLockedSetting = ( settingId, plusPayload ) => !! (
  settingId
  && plusPayload
  && plusPayload.locked
  && Array.isArray( plusPayload.gatedSettingIds )
  && plusPayload.gatedSettingIds.includes( settingId )
);

const markDirectGatedEdit = ( settingId, family, plusPayload ) => {
  if ( ! isLockedSetting( settingId, plusPayload ) ) {
    return;
  }

  plusPayload.directGatedSettingValues = {
    ...( plusPayload.directGatedSettingValues || {} ),
    [ settingId ]: { font_family: family },
  };
};

const clearDirectGatedEdit = ( settingId, plusPayload ) => {
  if ( ! plusPayload?.directGatedSettingValues ) {
    return;
  }

  delete plusPayload.directGatedSettingValues[ settingId ];
};

const SiteTitleFontControl = ( { clientId, ensureEngineReady, payload } ) => {
  const { useEffect, useMemo, useRef, useState } = wp.element;
  const { __ } = wp.i18n;
  const [ family, setFamily ] = useState( '' );
  const [ ready, setReady ] = useState( false );
  const engineRef = useRef( null );
  const directMutationRef = useRef( false );
  const settingId = useMemo( () => resolveThemeSettingId(
    payload?.customizeSettings?.settings || {},
    SETTING_BASENAME
  ), [] );

  useEffect( () => {
    if ( ! settingId ) {
      return undefined;
    }

    const eng = ensureEngineReady();
    const setting = eng?.api?.( settingId );
    if ( ! eng || ! setting ) {
      return undefined;
    }

    engineRef.current = eng;
    const sync = value => {
      if ( ! directMutationRef.current ) {
        // A palette/sizing cascade or Usage edit supersedes any earlier direct
        // shortcut provenance before the Save · Plus listener sees the event.
        clearDirectGatedEdit( settingId, payload.plus );
      }
      setFamily( value?.font_family || '' );
    };
    setting.bind( sync );
    sync( setting() );
    setReady( true );

    return () => setting.unbind( sync );
  }, [ settingId ] );

  if ( ! ready || ! engineRef.current ) {
    return null;
  }

  const control = findFontControl( engineRef.current.root, settingId );
  if ( ! control ) {
    return null;
  }

  const locked = isLockedSetting( settingId, payload.plus );
  const recommended = getRecommendedFamilies( control );
  const InspectorControls = wp.blockEditor?.InspectorControls || wp.editor?.InspectorControls;
  const ToolsPanelItem = wp.components.__experimentalToolsPanelItem;

  if ( ! InspectorControls || ! ToolsPanelItem ) {
    return null;
  }

  return (
    <InspectorControls group="typography">
      <ToolsPanelItem
        hasValue={ () => false }
        isShownByDefault
        label={ __( 'Site Title font', '__plugin_txtd' ) }
        onDeselect={ () => {} }
        resetAllFilter={ attributes => attributes }
        panelId={ clientId }
      >
        <div className="sm-site-title-font-shortcut">
          <FontFamilyControl
            label={ __( 'Site Title font', '__plugin_txtd' ) }
            family={ family }
            recommended={ recommended }
            onPick={ picked => {
              directMutationRef.current = true;
              markDirectGatedEdit( settingId, picked, payload.plus );
              try {
                if ( ! pickFontFamily( engineRef.current.root, settingId, picked ) ) {
                  clearDirectGatedEdit( settingId, payload.plus );
                }
              } finally {
                directMutationRef.current = false;
              }
            } }
          />
          { locked && (
            <div className="sm-site-title-font-shortcut__trial">
              <span className="sm-site-title-font-shortcut__badge">{ payload.plus.badge || __( 'Plus', '__plugin_txtd' ) }</span>
              <span>{ __( 'Try it live. Saving this global font choice is part of Pixelgrade Plus.', '__plugin_txtd' ) }</span>
              { payload.plus.upsellUrl && (
                <a
                  href={ plusUpsellUrl( payload.plus, { content: 'site_title_font_shortcut' } ) }
                  target="_blank"
                  rel="noopener noreferrer"
                >
                  { payload.plus.learnMore || __( 'Learn more', '__plugin_txtd' ) }
                </a>
              ) }
            </div>
          ) }
        </div>
      </ToolsPanelItem>
    </InspectorControls>
  );
};

export const registerSiteTitleFontShortcut = ( { ensureEngineReady, payload } ) => {
  if ( ! wp.hooks?.addFilter || ! wp.compose?.createHigherOrderComponent || ! wp.blockEditor?.InspectorControls ) {
    return false;
  }

  const withSiteTitleFontShortcut = wp.compose.createHigherOrderComponent( BlockEdit => props => (
    <>
      <BlockEdit { ...props } />
      { 'core/site-title' === props.name && props.isSelected && (
        <SiteTitleFontControl
          clientId={ props.clientId }
          ensureEngineReady={ ensureEngineReady }
          payload={ payload }
        />
      ) }
    </>
  ), 'withSiteTitleFontShortcut' );

  wp.hooks.addFilter( 'editor.BlockEdit', FILTER_NAMESPACE, withSiteTitleFontShortcut );

  return true;
};
