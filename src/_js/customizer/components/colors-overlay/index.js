import React, { useEffect, useState } from "react";

import { Overlay, ColorsPreview } from "../../components";

import { OptionsProvider } from '../options-context';

const ColorsOverlay = ( props ) => {
  const { show } = props;
  const setting = wp.customize( 'sm_advanced_palette_output' );
  const [ palettes, setPalettes ] = useState( JSON.parse( setting() ) );

  const changeListener = ( newValue ) => {
    setPalettes( JSON.parse( newValue ) );
  };

  useEffect( () => {
    // Attach the listeners on component mount.
    setting.bind( changeListener );

    // Detach the listeners on component unmount.
    return () => {
      setting.unbind( changeListener );
    }
  }, [] );

  return (
    <Overlay show={ show }>
      <ColorsPreview />
    </Overlay>
  )
};

export default ColorsOverlay;
