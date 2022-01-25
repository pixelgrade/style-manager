import ReactDOM from "react-dom";
import { ColorizeElementsButton } from '../components';

export const initializeColorizeElementsButton = () => {
  const target = document.getElementById( 'customize-control-sm_coloration_level_control' );

  if ( ! target ) {
    return;
  }

  const button = document.createElement( 'li' );

  button.setAttribute( 'class', 'customize-control' );
  button.setAttribute( 'style', 'padding: 0' );

  target.insertAdjacentElement( 'afterend', button );

  ReactDOM.render( <ColorizeElementsButton />, button );
};
