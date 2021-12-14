
export const handlePresets = () => {
  const presets = Array.from( document.querySelectorAll( '.js-style-manager-preset' ) );

  presets.forEach( preset => {
    const inputs = Array.from( preset.querySelectorAll( '[data-customize-setting-link]' ) );

    if ( ! inputs.length ) {
      return;
    }

    const settingID = inputs[0].getAttribute( 'data-customize-setting-link' );

    wp.customize( settingID, setting => {
      setting.bind( newValue => {
        const input = inputs.find( input => input.value === newValue );
        const options = JSON.parse( input.dataset.options );

        Object.keys( options ).forEach( connectedSettingId => {
          wp.customize( connectedSettingId, connectedSetting => {
            connectedSetting.set( options[ connectedSettingId ] );
          } );
        } )
      } );
    } );
  } );
}
