import React, { createContext, useCallback, useEffect, useMemo, useRef, useState } from "react";

import { getColorOptionsIDs } from "../../utils";
import { useCustomizeSettingCallback } from "../../hooks";

// Safari < 16.4 lacks requestIdleCallback/cancelIdleCallback support.
const scheduleCallback = window.requestIdleCallback || ( ( cb ) => setTimeout( cb, 1 ) );
const cancelScheduledCallback = window.cancelIdleCallback || clearTimeout;

const OptionsContext = createContext();

export const OptionsProvider = ( props ) => {
  const settingsIDs = useMemo( getColorOptionsIDs, [] );
  const nextOptions = useRef( {} );

  settingsIDs.forEach( settingID => {
    wp.customize( settingID, setting => {
      nextOptions.current = { ...nextOptions.current, [settingID]: setting() };
    } );

    useCustomizeSettingCallback( settingID, newValue => {
      cancelScheduledCallback( callback );
      nextOptions.current = { ...nextOptions.current, [settingID]: newValue };
      scheduleCallback( callback );
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
