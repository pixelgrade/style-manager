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
      const connectedSettingConfig = getSettingConfig( connectedSettingID );
      const connectedSettingValue = connectedSetting();
      // Prefer the CURRENT size so the interval describes the values being
      // remapped — this keeps re-derivation anchored to the user's own scale
      // and makes the neutral knob position an identity (issue #203).
      const fontSize = connectedSettingValue?.font_size?.value ?? connectedSettingConfig?.default?.font_size?.value;
      const unit = connectedSettingValue?.font_size?.unit ?? connectedSettingConfig?.default?.font_size?.unit;

      if ( fontSizeUnitSet ) {
        if ( !! unit && unit !== fontSizeUnit ) {
          hasConsistentFontSizes = false;
        }
      } else {
        if ( !! unit ) {
          fontSizeUnit = unit;
          fontSizeUnitSet = true;
        }
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
};
