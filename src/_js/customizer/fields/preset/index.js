
export const handlePresets = () => {
  const presets = Array.from( document.querySelectorAll( '.js-style-manager-preset' ) );

  presets.forEach( preset => {

    if ( preset.classList.contains( 'radio' ) ) {
      handleRadioPreset( preset );
    }

  } );
};

// A preset stores its option values as JSON (numbers/booleans), while the live
// settings often read back as strings (e.g. "12", "1"). Compare them loosely so
// 12 matches "12" and true matches "1"/true — otherwise a preset never re-matches
// and the control sticks on "Custom".
const valuesMatch = ( presetValue, settingValue ) => {
  if ( presetValue === settingValue ) {
    return true;
  }

  if ( 'boolean' === typeof presetValue ) {
    const truthy = true === settingValue || '1' === settingValue || 1 === settingValue;
    return presetValue ? truthy : ! truthy;
  }

  const presetNumber = parseFloat( presetValue );
  const settingNumber = parseFloat( settingValue );
  if ( ! isNaN( presetNumber ) && ! isNaN( settingNumber ) ) {
    return presetNumber === settingNumber;
  }

  return String( presetValue ) === String( settingValue );
};

const parsePresetOptions = input => {
  try {
    return JSON.parse( input.dataset.options );
  } catch ( e ) {
    return null;
  }
};

export const handleRadioPreset = preset => {
  const inputs = Array.from( preset.querySelectorAll( '[data-customize-setting-link]' ) );

  if ( ! inputs.length ) {
    return;
  }

  const settingID = inputs[0].getAttribute( 'data-customize-setting-link' );
  const customInput = inputs.find( input => input.value === 'custom' );

  wp.customize( settingID, setting => {

    // Which named preset (if any) every connected option currently matches.
    const deriveCurrentPreset = () => {
      const match = inputs.find( input => {
        if ( input.value === 'custom' ) {
          return false;
        }

        const options = parsePresetOptions( input );
        if ( ! options || ! Object.keys( options ).length ) {
          return false;
        }

        return Object.keys( options ).every( optionId => {
          let same = false;
          wp.customize( optionId, optionSetting => {
            same = valuesMatch( options[ optionId ], optionSetting() );
          } );
          return same;
        } );
      } );

      return match ? match.value : 'custom';
    };

    // True while we re-point the preset to reflect the current values, so the
    // apply handler below doesn't re-write (and churn) the settings we matched.
    let isReDeriving = false;

    const onConnectedSettingChange = () => {
      if ( ! customInput ) {
        return;
      }

      const target = deriveCurrentPreset();
      if ( setting() !== target ) {
        isReDeriving = true;
        setting.set( target );
        isReDeriving = false;
      }
    };

    // The distinct connected settings across every preset.
    const linkedSettingsIds = [];

    inputs.forEach( input => {
      const options = parsePresetOptions( input ) || {};

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

      // 'custom' applies nothing; a re-derived value only updates the indicator.
      if ( newValue === 'custom' || isReDeriving ) {
        return;
      }

      unbindAll();

      const input = inputs.find( input => input.value === newValue );
      const options = input ? parsePresetOptions( input ) : null;

      if ( options ) {
        Object.keys( options ).forEach( connectedSettingId => {
          wp.customize( connectedSettingId, connectedSetting => {
            connectedSetting.set( options[ connectedSettingId ] );
          } );
        } );
      }

      bindAll();
    } );

    // Watch connected settings from the start so the preset tracks edits made
    // directly to the controls (in either direction), not only after a pick.
    bindAll();
  } );
};
