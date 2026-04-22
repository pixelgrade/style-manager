import { useCallback, useContext, useState } from 'react';

import { getCSSFromPalettes } from "../../utils";
import { useCustomizeSettingCallback } from "../../hooks";
import { ConfigContext } from "../../components";

const computeInitialCSS = ( siteVariationSettingID ) => {
  const outputSetting = wp.customize( 'sm_advanced_palette_output' );
  const variationSetting = wp.customize( siteVariationSettingID );
  if ( ! outputSetting || ! variationSetting ) {
    return '';
  }
  try {
    const palettes = JSON.parse( outputSetting() );
    return getCSSFromPalettes( palettes, variationSetting() );
  } catch ( e ) {
    return '';
  }
};

const ColorsStyleTag = props => {
  const siteVariationSettingID = 'sm_site_color_variation';
  // Seed the CSS with the current palette output on mount so the preview
  // overlay has real CSS variables available immediately. Without this the
  // tag stayed empty until a setting change fired, leaving the overlay with
  // no --sm-bg-color-N values (grades all fall back to the default bg).
  const [ CSS, setCSS ] = useState( () => computeInitialCSS( siteVariationSettingID ) );

  const onSiteVariationChange = useCallback( newVariation => {
    wp.customize( 'sm_advanced_palette_output', setting => {
      const output = setting();
      const palettes = JSON.parse( output );
      const newCSS = getCSSFromPalettes( palettes, newVariation );

      setCSS( newCSS );
    } );
  }, [] );

  const onOutputChange = useCallback( newValue => {
    const palettes = JSON.parse( newValue );

    wp.customize( siteVariationSettingID, setting => {
      const variation = setting();
      setCSS( getCSSFromPalettes( palettes, variation ) );
    } );
  }, [] );

  useCustomizeSettingCallback( 'sm_advanced_palette_output', onOutputChange );
  useCustomizeSettingCallback( siteVariationSettingID, onSiteVariationChange );

  return (
    <style>{ CSS }</style>
  )
};

export default ColorsStyleTag;
