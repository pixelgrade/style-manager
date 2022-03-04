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
        sm_font_primary: [ 6, 40 ],
        sm_font_secondary: [ 16, 16 ],
        sm_font_body: [ 0, 45 ],
      },
      // Rosa2
      regular: {
        sm_font_primary: [ 7, 80 ],
        sm_font_secondary: [ 24, 16 ],
        sm_font_body: [ 24, 45 ],
        // sm_font_body: [ 45, 45 ],
      },
      larger: {
        sm_font_primary: [ 18, 100 ],
        sm_font_secondary: [ 20, 45 ],
        // sm_font_body: [ 80, 30 ],
        sm_font_body: [ 70, 30 ],
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
            pitchSetting.set( config[ settingID ][1] );
          } );
        } );
      } );
    } )

  } );
};
