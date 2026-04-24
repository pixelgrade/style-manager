/** @jsx createElement */
import { createElement } from '@wordpress/element';
import { App } from './App.jsx';
import './admin.scss';

const root = document.getElementById( 'style-manager-lab-root' );

if ( root ) {
  if ( typeof window.wp?.element?.createRoot === 'function' ) {
    window.wp.element.createRoot( root ).render( <App config={ window.styleManagerLab || {} } /> );
  } else if ( typeof window.wp?.element?.render === 'function' ) {
    window.wp.element.render( <App config={ window.styleManagerLab || {} } />, root );
  }
}
