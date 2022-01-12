import { getSettingConfig } from "../../global-service";

export const getConnectedFieldsFontSizeInterval = ( settingID ) => {
  const settingConfig = getSettingConfig( settingID );
  const connectedFields = settingConfig.connected_fields || [];

  let minFontSize = Number.MAX_SAFE_INTEGER;
  let maxFontSize = Number.MIN_SAFE_INTEGER;
  let fontSizeUnit = false;
  let fontSizeUnitSet = false;
  let hasConsistentFontSizes = true;

  connectedFields.forEach( key => {
    const connectedSettingID = `${ styleManager.config.options_name }[${ key }]`;

    wp.customize( connectedSettingID, connectedSetting => {
      const connectedSettingValue = connectedSetting();
      const fontSize = connectedSettingValue?.font_size?.value;
      const unit = connectedSettingValue?.font_size?.unit;

      if ( fontSizeUnitSet ) {
        if ( unit !== fontSizeUnit ) {
          hasConsistentFontSizes = false;
        }
      } else {
        fontSizeUnit = unit;
        fontSizeUnitSet = true;
      }

      minFontSize = fontSize < minFontSize ? fontSize : minFontSize;
      maxFontSize = fontSize > maxFontSize ? fontSize : maxFontSize;
    } );
  } );

  if ( ! hasConsistentFontSizes ||
       minFontSize === Number.MAX_SAFE_INTEGER ||
       maxFontSize === Number.MIN_SAFE_INTEGER ||
       minFontSize > maxFontSize ) {
    return false;
  }

  return [ minFontSize, maxFontSize ];
}
