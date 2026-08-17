import * as globalService from "../global-service";
import { reloadConnectedFields, runConnectedFieldsBatch } from "./connected-fields";
import { getFontSizingPresetConfig } from "./font-sizing-presets";

export const initializeConnectedFieldsPresets = () => {

  wp.customize( 'sm_fonts_connected_fields_preset', setting => {
    const settingIDs = styleManager.fontPalettes.masterSettingIds;
    const config = globalService.getSettingConfig( 'sm_fonts_connected_fields_preset' );
    const value = setting();

    const updateConnectedSettingsConfigs = ( newValue ) => {

      if ( !config?.choices?.[ newValue ]?.config ) {
        return;
      }

      const newValueConfig = config.choices[ newValue ].config;

      Object.keys( newValueConfig ).forEach( settingID => {
        const masterFontConfig = globalService.getSettingConfig( settingID );
        const newMasterFontConfig = Object.assign( {}, masterFontConfig, {
          connected_fields: newValueConfig[ settingID ]
        } );
        globalService.setSettingConfig( settingID, newMasterFontConfig );
      } );
    }

    updateConnectedSettingsConfigs( value );

    setting.bind( newValue => {
      updateConnectedSettingsConfigs( newValue );

      reloadConnectedFields();

      settingIDs.forEach( settingID => {
        wp.customize( settingID, setting => {
          const value = setting();
          setting.callbacks.fireWith( setting, [ value, value ] );
        } );
      } )
    } );
  } );

  wp.customize( 'sm_font_sizing', setting => {

    setting.bind( newValue => {
      const config = getFontSizingPresetConfig( newValue );

      if ( ! config ) {
        return;
      }

      runConnectedFieldsBatch( () => {
        Object.keys( config ).forEach( settingID => {
          wp.customize( `${ settingID }_elevation`, elevationSetting => {
            wp.customize( `${ settingID }_pitch`, pitchSetting => {
              elevationSetting.set( config[ settingID ][0] );
              pitchSetting.set( config[ settingID ][1] );
            } );
          } );
        } );
      } );
    } )

  } );
};
