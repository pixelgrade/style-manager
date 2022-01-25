import ReactDOM from "react-dom";

import { PreviewTabs } from '../components';

export const initializeColorPalettesPreview = () => {

  wp.customize.bind( 'ready', function() {
    wp.customize.panel( 'style_manager_panel', smPanel => {
      wp.customize.section( 'sm_color_palettes_section', function( smColorsSection ) {
        wp.customize.previewer.bind( 'ready', () => {

          const iframe = document.querySelector( '#customize-preview iframe' );

          if ( ! iframe ) {
            return;
          }

          const smPreviewTabs = document.createElement( 'div' );
          iframe.insertAdjacentElement( 'beforebegin', smPreviewTabs );
          ReactDOM.render( <PreviewTabs smPanel={ smPanel } />, smPreviewTabs );

        } );
      } );
    } );
  } );
};
