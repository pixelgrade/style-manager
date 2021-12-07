import React, { useEffect, useState } from "react";

import Overlay from "../overlay";
import Preview from "../preview";

const ColorsOverlay = ( props ) => {
  const { setting, show } = props;
  const [ palettes, setPalettes ] = useState( JSON.parse( setting() ) );

  const changeListener = ( newValue ) => {
    setPalettes( JSON.parse( newValue ) );
  }

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
      <Preview palettes={ palettes } />
    </Overlay>
  )
}

export default ColorsOverlay;
