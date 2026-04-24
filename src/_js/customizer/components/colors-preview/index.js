/** @jsx createElement */
import { createElement, useEffect, useState } from '@wordpress/element';

import ColorSystemPreview from '../../../color-system-preview/ColorSystemPreview.jsx';
import DarkMode from '../../../dark-mode';
import { useCustomizeSettingCallback } from '../../hooks';

const parsePalettes = ( value ) => {
  try {
    const parsed = JSON.parse( value );

    return Array.isArray( parsed ) ? parsed : [];
  } catch ( error ) {
    return [];
  }
};

const getCustomizeSettingValue = ( settingId, fallback ) => {
  if ( ! window.wp?.customize ) {
    return fallback;
  }

  const setting = window.wp.customize( settingId );

  return setting ? setting() : fallback;
};

const ColorsPreview = () => {
  const [ isDark, setDark ] = useState( DarkMode.isCompiledDark() );
  const [ palettes, setPalettes ] = useState( () => parsePalettes( getCustomizeSettingValue( 'sm_advanced_palette_output', '[]' ) ) );
  const [ siteVariation, setSiteVariation ] = useState( () => parseInt( getCustomizeSettingValue( 'sm_site_color_variation', 1 ), 10 ) || 1 );

  useEffect( () => {
    DarkMode.bind( setDark );

    return () => {
      DarkMode.unbind( setDark );
    };
  }, [] );

  useCustomizeSettingCallback( 'sm_advanced_palette_output', ( newValue ) => {
    setPalettes( parsePalettes( newValue ) );
  } );

  useCustomizeSettingCallback( 'sm_site_color_variation', ( newValue ) => {
    setSiteVariation( parseInt( newValue, 10 ) || 1 );
  } );

  return (
    <ColorSystemPreview
      palettes={ palettes }
      isDark={ isDark }
      siteVariation={ siteVariation }
      strings={ window.styleManager?.l10n?.colorPalettes || {} }
    />
  );
};

export default ColorsPreview;
