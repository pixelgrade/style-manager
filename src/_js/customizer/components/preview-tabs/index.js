import './style.scss';

import React, { useEffect, useRef, useState } from 'react';

import { ColorsOverlay } from '../../components';

const PreviewTabs = ( props ) => {
  const [ active, setActive ] = useState( 'site' );
  const previewedDevice = wp.customize.previewedDevice.get();
  const [ visible, setVisible ] = useState( previewedDevice === 'desktop' );

  const previewRef = useRef();
  const previewHeaderRef = useRef();

  const setting = wp.customize( 'sm_advanced_palette_output' );

  const tabs = [
    { id: 'site', label: styleManager.l10n.colorPalettes.previewTabLiveSiteLabel },
    { id: 'colors', label: styleManager.l10n.colorPalettes.previewTabColorSystemLabel, callback: () => {
        wp.customize.section( 'sm_color_palettes_section', section => {
          section.focus();
        } )
      } }
  ];

  wp.customize.section( 'sm_color_palettes_section', section => {

    useEffect( () => {

      const callback = expanded => {
        if ( expanded ) {
          setActive( 'colors' );
        }
      };

      section.expanded.bind( callback );

      return () => {
        section.expanded.unbind( callback );
      }
    } )
  } );

  useEffect( () => {

    const previewResizer = window?.sm?.customizer?.resizer;

    if ( ! previewResizer ) {
      return;
    }

    const top = previewHeaderRef.current.offsetHeight;

    const style = getComputedStyle( previewRef.current, null );
    const left = parseFloat( style.left.replace( "px", "" ) );
    const right = parseFloat( style.right.replace( "px", "" ) );

    previewResizer.setOffset( {
      top,
      right,
      bottom: 0,
      left,
    } );

    previewResizer.resize();

  }, [] );

  useEffect( () => {

    const callback = ( previewdDevice ) => {
      setVisible( previewdDevice === 'desktop' );
    };

    wp.customize.previewedDevice.bind( callback );

    return () => {
      wp.customize.previewedDevice.unbind( callback );
    }

  }, [] );

  return (
    <div className={ `sm-preview ${ visible ? 'sm-preview--visible' : '' }` } ref={ previewRef }>
      <div className="sm-preview__header" ref={ previewHeaderRef }>
        <div className="sm-preview__tabs">
          { tabs.map( tab => {
            const isActive = active === tab.id;
            const noop = () => {};
            const callback = typeof tab.callback === 'function' ? tab.callback : noop;

            return (
              <div key={ tab.id } className={ `sm-preview__tab ${ isActive ? 'sm-preview__tab--active' : '' }` } onClick={ () => {
                setActive( tab.id );
                callback();
              } }>{ tab.label }</div>
            )
          } ) }
        </div>
      </div>
      <div className="sm-preview__content">
        <ColorsOverlay show={ active === 'colors' } setting={ setting } />
      </div>
    </div>
  );
};

export default PreviewTabs;
