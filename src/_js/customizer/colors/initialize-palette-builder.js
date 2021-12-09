import ReactDOM from "react-dom";

import { Builder } from '../components';

export const initializePaletteBuilder = ( sourceSettingID ) => {
  const containerID = `customize-control-${ sourceSettingID }_control`;
  const container = document.getElementById( containerID );

  if ( ! container ) {
    return;
  }

  const target = document.createElement( 'DIV' );

  Array.from(container.children).forEach( child => {
    child.style.display = 'none';
  } );

  container.insertBefore( target, container.firstChild );

  ReactDOM.render( <Builder sourceSettingID={ sourceSettingID } />, target );
}
