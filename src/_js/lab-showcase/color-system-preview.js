/** @jsx createElement */
import { createElement, useEffect, useState } from '@wordpress/element';

import ColorSystemPreview from '../color-system-preview/ColorSystemPreview.jsx';

const EVENT_NAME = 'style-manager-lab:showcase-state';

const getInitialConfig = () => ( {
  palettes: [],
  siteVariation: 1,
  isDark: false,
  strings: {},
  ...( window.styleManagerLabColorSystem || {} ),
} );

const LabColorSystemPreview = () => {
  const [ config, setConfig ] = useState( getInitialConfig );

  useEffect( () => {
    const listener = ( event ) => {
      setConfig( ( current ) => ( {
        ...current,
        isDark: Boolean( event.detail?.state?.dark ),
        siteVariation: event.detail?.state?.variation || event.detail?.siteVariation || current.siteVariation,
      } ) );
    };

    window.addEventListener( EVENT_NAME, listener );

    return () => window.removeEventListener( EVENT_NAME, listener );
  }, [] );

  return <ColorSystemPreview { ...config } />;
};

export const installColorSystemPreview = () => {
  const root = document.getElementById( 'style-manager-lab-color-system-root' );

  if ( ! root ) {
    return;
  }

  if ( typeof window.wp?.element?.createRoot === 'function' ) {
    window.wp.element.createRoot( root ).render( <LabColorSystemPreview /> );
    return;
  }

  if ( typeof window.wp?.element?.render === 'function' ) {
    window.wp.element.render( <LabColorSystemPreview />, root );
  }
};
