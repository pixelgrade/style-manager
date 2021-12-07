import React, { createContext, useEffect, useMemo, useState } from "react";

import { getColorOptionsIDs } from "../../utils";
import { useCustomizeSettingCallback } from "../../hooks";

const OptionsContext = createContext();

export const OptionsProvider = ( props ) => {
  const settingsIDs = useMemo( getColorOptionsIDs, [] );
  const [ options, setOptions ] = useState( {} );

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
      setOptions( { ...options, [settingID]: newValue } );
    }, [ options ] )
  } );

  const providerValue = {
    options,
    setOptions,
  };

  return (
    <OptionsContext.Provider value={ providerValue }>
      { props.children }
    </OptionsContext.Provider>
  )
}

export default OptionsContext;
