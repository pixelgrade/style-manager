import React, { createContext, useCallback, useEffect, useMemo, useState } from 'react';

import { getColorsFromInputValue } from "../../utils";
import { useCustomizeSettingCallback } from "../../hooks";

const ConfigContext = createContext();

const useConfig = ( sourceSettingID ) => {

  return useMemo( () => {

    const sourceSetting = wp.customize( sourceSettingID );

    if ( sourceSetting ) {
      const sourceSettingValue = sourceSetting();
      return getColorsFromInputValue( sourceSettingValue );
    }

    return [];

  }, [] );

};

export const ConfigProvider = ( props ) => {
  const { sourceSettingID } = props;
  const initialConfig = useConfig( sourceSettingID );
  const [ config, setConfig ] = useState( initialConfig );

  const onSourceChange = useCallback( ( newValue ) => {
    const newConfig = getColorsFromInputValue( newValue );
    setConfig( newConfig );
  }, [] );

  useCustomizeSettingCallback( sourceSettingID, onSourceChange );

  const providerValue = {
    config,
    sourceSettingID,
  };

  return (
    <ConfigContext.Provider value={ providerValue }>
      { props.children }
    </ConfigContext.Provider>
  )
};

export const withConfigProvider = Component => {
  return ( props ) => {
    return (
      <ConfigProvider { ...props }>
        <Component { ...props } />
      </ConfigProvider>
    )
  }
};

export default ConfigContext;
