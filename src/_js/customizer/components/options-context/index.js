import React, { createContext, useCallback, useEffect, useMemo, useRef, useState } from "react";

import { getColorOptionsIDs } from "../../utils";
import { useCustomizeSettingCallback } from "../../hooks";

const OptionsContext = createContext();

export const OptionsProvider = ( props ) => {
  const settingsIDs = useMemo( getColorOptionsIDs, [] );
  const [ options, setOptions ] = useState( {} );
  const nextOptions = useRef( {} );
  const callback = useCallback( () => {
    setOptions( nextOptions.current );
  }, [ options ] );

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
      cancelIdleCallback( callback );
      nextOptions.current = { ...nextOptions.current, [settingID]: newValue };
      requestIdleCallback( callback );
    }, [] );
  } );

  return (
    <OptionsContext.Provider value={ options }>
      { props.children }
    </OptionsContext.Provider>
  )
}

export default OptionsContext;
