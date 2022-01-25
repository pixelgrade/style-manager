import { useCallback, useContext, useState } from 'react';

import { getCSSFromPalettes } from "../../utils";
import { useCustomizeSettingCallback } from "../../hooks";
import { ConfigContext } from "../../components";

const ColorsStyleTag = props => {
  const siteVariationSettingID = 'sm_site_color_variation';
  const [ CSS, setCSS ] = useState( '' );

  const onSiteVariationChange = useCallback( newVariation => {
    wp.customize( 'sm_advanced_palette_output', setting => {
      const output = setting();
      const palettes = JSON.parse( output );
      setCSS( getCSSFromPalettes( palettes, newVariation ) );
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
