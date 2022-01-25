import React, { createContext, useCallback, useEffect, useMemo, useRef, useState } from "react";

import { getColorOptionsIDs } from "../../utils";
import { useCustomizeSettingCallback } from "../../hooks";

const OptionsContext = createContext();

export const OptionsProvider = ( props ) => {
  const settingsIDs = useMemo( getColorOptionsIDs, [] );
  const nextOptions = useRef( {} );

  settingsIDs.forEach( settingID => {
    wp.customize( settingID, setting => {
      nextOptions.current = { ...nextOptions.current, [settingID]: setting() };
    } );

    useCustomizeSettingCallback( settingID, newValue => {
      cancelIdleCallback( callback );
      nextOptions.current = { ...nextOptions.current, [settingID]: newValue };
      requestIdleCallback( callback );
    }, [] );
  } );

  const [ options, setOptions ] = useState( nextOptions.current );

  const callback = useCallback( () => {
    setOptions( nextOptions.current );
  }, [ options ] );

  return (
    <OptionsContext.Provider value={ options }>
      { props.children }
    </OptionsContext.Provider>
  )
};

export default OptionsContext;
