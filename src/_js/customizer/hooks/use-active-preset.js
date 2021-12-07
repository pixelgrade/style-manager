import { useCallback, useEffect, useState } from 'react';

import { useCustomizeSettingCallback } from "../hooks";

const useActivePreset = () => {
  const [ activePreset, setActivePreset ] = useState( null );

  useEffect( () => {
    wp.customize( 'sm_color_palette_in_use', setting => {
      setActivePreset( setting() );
    } );
  }, [] );

  const updateSettings = useCallback( ( newValue ) => {

    wp.customize( 'sm_color_palette_in_use', setting => {
      setting.set( newValue );
    } );

    wp.customize( 'sm_is_custom_color_palette', setting => {
      // Use empty string instead of false since that is what the DB provides.
      // This way we avoid triggering a setting change when it really is not (false !== '' and the setting is updated).
      setting.set( ! newValue ? true  : '' );
    } );

  }, [] );

  const onActivePresetChange = useCallback( newValue => {
    setActivePreset( newValue );
  } );

  useCustomizeSettingCallback( 'sm_color_palette_in_use', onActivePresetChange )

  return [ activePreset, updateSettings ];
}

export default useActivePreset;
