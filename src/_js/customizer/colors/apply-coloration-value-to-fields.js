import * as globalService from "../global-service";
import { getSettingConfig } from "../global-service";

export const applyColorationValueToFields = ( colorationLevel ) => {

  const defaultColorationLevel = globalService.getSettingConfig( 'sm_coloration_level' ).default;
  const isDefaultColoration = colorationLevel === defaultColorationLevel;

  const settings = globalService.getSettings();

  const value = parseInt( colorationLevel, 10 );
  const threshold = value < 50 ? 4 : value < 75 ? 3 : value < 100 ? 2 : 1;

  Object.keys( settings ).forEach( settingID => {
    const config = getSettingConfig( settingID );

    if ( config?.type === 'sm_toggle' && typeof config.coloration !== 'undefined' ) {
      const { coloration } = config;

      wp.customize( settingID, setting => {
        setting.set( isDefaultColoration ? config.default : coloration >= threshold );
      } );
    }
  } );

};
