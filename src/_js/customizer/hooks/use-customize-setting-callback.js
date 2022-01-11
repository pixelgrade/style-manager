import React, { useEffect } from 'react';

const useCustomizeSettingCallback = ( settingID, callback, deps = [] ) => {

  if ( typeof callback !== "function" ) {
    return;
  }

  useEffect( () => {

    wp.customize( settingID, setting => {
      setting.bind( callback );
    } )

    return () => {
      wp.customize( settingID, setting => {
        setting.unbind( callback );
      } )
    }

  }, deps );

}

export default useCustomizeSettingCallback;
