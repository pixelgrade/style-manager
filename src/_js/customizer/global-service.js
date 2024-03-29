import _ from "lodash";

let callbacks = {};
let settings = {};

let overrideCustomizerBack = [];

export const loadSettings = () => {
  settings = JSON.parse( JSON.stringify( wp.customize.settings.settings ) );
};

export const getSettings = () => {
  return settings;
};

export const setSettings = ( newSettings ) => {
  settings = newSettings;
};

export const getSetting = ( settingID ) => {
  return settings[settingID];
};

export const getSettingConfig = ( settingID ) => {
  return styleManager.config.settings[ settingID ];
};

export const setSettingConfig = ( settingID, newConfig ) => {
  styleManager.config.settings[ settingID ] = newConfig;
};

export const setSetting = ( settingID, value ) => {
  settings[settingID] = value;
};

export const getCallback = ( settingID ) => {
  return callbacks[settingID];
};

export const setCallback = ( settingID, callback ) => {
  callbacks[settingID] = callback;
};

export const getCallbacks = () => {
  return callbacks;
};

export const deleteCallbacks = ( settingIDs ) => {
  settingIDs.forEach( settingID => {
    delete callbacks[settingID];
  } );
};

export const getBackArray = () => {
  return overrideCustomizerBack;
};

export const addToBackArray = ( section ) => {
  overrideCustomizerBack.push( section );
};

export const setBackArray = ( newArray ) => {
  overrideCustomizerBack = newArray.slice();
};

export const pushToBackArray = ( targetSection, section ) => {
  const backArray = getBackArray();
  window.document.body.classList.add( 'sm-customizer-shortcut-busy' );
  setBackArray( [] );
  targetSection.focus();
  setTimeout( () => {
    window.document.body.classList.remove( 'sm-customizer-shortcut-busy' );
  }, 600 );
  setBackArray( backArray );
  addToBackArray( section );
};

export const popFromBackArray = () => {
  const backArray = getBackArray();
  const targetSectionID = backArray.pop();

  if ( targetSectionID ) {
    wp.customize.section( targetSectionID, ( targetSection ) => {
      window.document.body.classList.add( 'sm-customizer-shortcut-busy' );
      targetSection.focus();
      setTimeout( () => {
        window.document.body.classList.remove( 'sm-customizer-shortcut-busy' );
      }, 600 );
    } );
  }
};

export const getConnectedFields = ( settingID ) => {
  const settingConfig = getSetting( settingID );
  const connectedFields = settingConfig.connected_fields || {};

  return connectedFields;
}

export const getConnectedFieldsIDs = ( settingID ) => {
  const connectedFields = getConnectedFields( settingID );
  const connectedFieldsIDs = Object.keys( connectedFields ).map( key => connectedFields[key].setting_id );

  return connectedFieldsIDs;
}

export const bindConnectedFields = ( settingIDs, filter = noop ) => {

  settingIDs.forEach( settingID => {
    wp.customize( settingID, parentSetting => {

      setCallback( settingID, newValue => {
        const connectedFields = getConnectedFieldsIDs( settingID );

        connectedFields.forEach( connectedSettingID => {
          wp.customize( connectedSettingID, connectedSetting => {
            connectedSetting.set( filter( newValue ) );
          } );
        } );
      } );

      parentSetting.bind( getCallback( settingID ) );
    } );
  } );
};

export const unbindConnectedFields = function( settingIDs ) {
  const globalCallbacks = _.pick( getCallbacks(), settingIDs );

  _.each( globalCallbacks, ( callback, settingID ) => {
    wp.customize( settingID, setting => {
      setting.unbind( callback );
    } );
  } );

  deleteCallbacks( settingIDs );
};

const noop = x => x;
