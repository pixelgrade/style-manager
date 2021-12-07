import { useCallback, useContext, useState } from 'react';

import { getCSSFromPalettes } from "../../utils";
import { useCustomizeSettingCallback } from "../../hooks";
import { ConfigContext } from "../../components";

const ColorsStyleTag = props => {
  const { outputSettingID } = useContext( ConfigContext );
  const siteVariationSettingID = 'sm_site_color_variation';
  const [ CSS, setCSS ] = useState( '' );

  const onSiteVariationChange = useCallback( newVariation => {
    wp.customize( outputSettingID, setting => {
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

  useCustomizeSettingCallback( outputSettingID, onOutputChange );
  useCustomizeSettingCallback( siteVariationSettingID, onSiteVariationChange );

  return (
    <style>{ CSS }</style>
  )
}

export default ColorsStyleTag;
