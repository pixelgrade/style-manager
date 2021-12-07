import { useCallback } from "react";

const useUpdateSourceSetting = () => {
  return useCallback( newValue => {
    wp.customize( 'sm_advanced_palette_source', setting => {
      setting.set( JSON.stringify( newValue ) );
    } );
  }, [] );
}

export default useUpdateSourceSetting;
