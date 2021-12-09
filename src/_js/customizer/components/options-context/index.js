import React, { createContext, useEffect, useMemo, useRef, useState } from "react";

import { getColorOptionsIDs } from "../../utils";
import { useCustomizeSettingCallback } from "../../hooks";

const OptionsContext = createContext();

export const OptionsProvider = ( props ) => {
  const settingsIDs = useMemo( getColorOptionsIDs, [] );
  const [ options, setOptions ] = useState( {} );
  const nextOptions = useRef( {} );
  const callback = useRef( null );

  useEffect( () => {
    const newOptions = {};

    settingsIDs.forEach( settingID => {
      wp.customize( settingID, setting => {
        newOptions[ settingID ] = setting();
      } );
    } );

    setOptions( newOptions );
  }, [] );

  settingsIDs.forEach( settingID => {
    useCustomizeSettingCallback( settingID, newValue => {
      callback.current = () => {
        setOptions( nextOptions.current );
      }
      nextOptions.current = { ...nextOptions.current, [settingID]: newValue }
      cancelIdleCallback( callback.current );
      requestIdleCallback( callback.current );
    }, [ nextOptions.current ] )
  } );

  return (
    <OptionsContext.Provider value={ options }>
      { props.children }
    </OptionsContext.Provider>
  )
}

export default OptionsContext;
