import * as globalService from "../global-service";
import { reloadConnectedFields } from "./connected-fields";

export const initializeConnectedFieldsPresets = () => {

  wp.customize( 'sm_fonts_connected_fields_preset', setting => {
    const settingIDs = styleManager.fontPalettes.masterSettingIds;
    const config = globalService.getSettingConfig( 'sm_fonts_connected_fields_preset' );

    setting.bind( newValue => {
      const newValueConfig = config.choices[ newValue ].config;

      Object.keys( newValueConfig ).forEach( settingID => {
        const masterFontConfig = globalService.getSettingConfig( settingID );
        const newMasterFontConfig = Object.assign( {}, masterFontConfig, {
          connected_fields: newValueConfig[ settingID ]
        } );
        globalService.setSettingConfig( settingID, newMasterFontConfig );
      } );

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

    const configs = {
      // Felt
      smaller: {
        sm_font_primary: [ 21, 115 ],
        sm_font_secondary: [ 14, 17 ],
        sm_font_body: [ 17, 20 ],
      },
      // Rosa2
      regular: {
        sm_font_primary: [ 24, 165 ],
        sm_font_secondary: [ 16, 18 ],
        sm_font_body: [ 17, 24 ],
      },
      larger: {
        sm_font_primary: [ 28, 180 ],
        sm_font_secondary: [ 17, 22 ],
        sm_font_body: [ 18, 24 ],
      },
    }

    setting.bind( newValue => {
      const config = configs[ newValue ];

      if ( ! config ) {
        return;
      }

      Object.keys( config ).forEach( settingID => {
        wp.customize( `${ settingID }_elevation`, elevationSetting => {
          wp.customize( `${ settingID }_pitch`, pitchSetting => {
            elevationSetting.set( config[ settingID ][0] );
            pitchSetting.set( config[ settingID ][1] - config[ settingID ][0] );
          } );
        } );
      } );
    } )

  } );
};
