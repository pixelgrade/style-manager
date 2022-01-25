
export const handlePresets = () => {
  const presets = Array.from( document.querySelectorAll( '.js-style-manager-preset' ) );

  presets.forEach( preset => {

    if ( preset.classList.contains( 'radio' ) ) {
      handleRadioPreset( preset );
    }

  } );
};

export const handleRadioPreset = preset => {
  const inputs = Array.from( preset.querySelectorAll( '[data-customize-setting-link]' ) );

  if ( ! inputs.length ) {
    return;
  }

  const settingID = inputs[0].getAttribute( 'data-customize-setting-link' );

  wp.customize( settingID, setting => {

    const onConnectedSettingChange = () => {
      const currentValue = setting();
      const customInput = inputs.find( input => input.value === 'custom' );
      const currentInput = inputs.find( input => input.value === currentValue );
      const currentInputOptions = JSON.parse( currentInput?.dataset?.options );

      if ( ! customInput || ! currentInputOptions ) {
        return false;
      }

      const isPreset = Object.keys( currentInputOptions ).every( optionId => {
        let sameValue = true;
        wp.customize( optionId, optionSetting => {
          sameValue = currentInputOptions[ optionId ] === optionSetting();
        } );
        return sameValue;
      } );

      if ( ! isPreset ) {
        setting.set( 'custom' );
      }
    };

    // to aboid binding same callback multiple times to the same setting
    // we build an array of settingIds and then bind / unbind the callback
    const linkedSettingsIds = [];

    inputs.forEach( input => {
      const options = JSON.parse( input.dataset.options );

      Object.keys( options ).forEach( connectedSettingId => {
        if ( linkedSettingsIds.indexOf( connectedSettingId ) === -1 ) {
          linkedSettingsIds.push( connectedSettingId );
        }
      } );
    } );

    const bindAll = () => {
      linkedSettingsIds.forEach( connectedSettingId => {
        wp.customize( connectedSettingId, connectedSetting => {
          connectedSetting.bind( onConnectedSettingChange );
        } );
      } );
    };

    const unbindAll = () => {
      linkedSettingsIds.forEach( connectedSettingId => {
        wp.customize( connectedSettingId, connectedSetting => {
          connectedSetting.unbind( onConnectedSettingChange );
        } );
      } );
    };

    setting.bind( newValue => {

      if ( newValue === 'custom' ) {
        return;
      }

      unbindAll();

      const input = inputs.find( input => input.value === newValue );
      const options = JSON.parse( input.dataset.options );

      Object.keys( options ).forEach( connectedSettingId => {
        wp.customize( connectedSettingId, connectedSetting => {
          connectedSetting.set( options[ connectedSettingId ] );
        } );
      } );

      bindAll();

    } );
  } );
};
