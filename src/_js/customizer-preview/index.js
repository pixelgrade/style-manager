import {
  domReady,
  maybeLoadWebfontloaderScript,
} from "../utils";

import {
  getSettingCSS,
  inPreviewIframe
} from './utils';


( () => {
  if ( ! inPreviewIframe() ) {
    return;
  }

  window.addEventListener( 'load', maybeLoadWebfontloaderScript );

  domReady( () => {

    const settings = window?.top?.styleManager?.config?.settings;
    const getStyleTagID = ( settingID => `dynamic_style_${ settingID.replace( /\\W/g, '_' ) }` );

    const properKeys = Object.keys( settings ).filter( settingID => {
      const setting = settings[settingID];
      return setting.type === 'font' || ( Array.isArray( setting.css ) && setting.css.length );
    } );

    properKeys.forEach( settingID => {
      const style = document.createElement( 'style' );
      const idAttr = getStyleTagID( settingID );

      style.setAttribute( 'id', idAttr );
      document.body.appendChild( style );
    } );

    // we create a queue of settingID => newValue pairs
    let updateQueue = {};

    // so we can update their respective style tags in only one pass
    // and avoid multiple "recalculate styles" and all changes will appear
    // at the same time in the customizer preview
    const onChange = _.debounce( () => {
      const queue = Object.assign( {}, updateQueue );
      updateQueue = {};

      Object.keys( queue ).forEach( settingID => {
        const idAttr = getStyleTagID( settingID );
        const style = document.getElementById( idAttr );
        const newValue = queue[ settingID ];
        const settingConfig = settings[ settingID ];

        style.innerHTML = getSettingCSS( settingID, newValue, settingConfig );
      } );
    }, 100 );

    properKeys.forEach( settingID => {
      wp.customize( settingID, setting => {
        setting.bind( ( newValue ) => {
          updateQueue[ settingID ] = newValue;
          onChange();
        } );
      } );
    } );
  } );
} )();
