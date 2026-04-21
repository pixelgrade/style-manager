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

          // Previewer 'ready' fires on every preview refresh and WP swaps in a fresh iframe.
          // Reuse a stable mount container so React reconciles instead of stacking duplicate widgets.
          let smPreviewTabs = document.querySelector( '.sm-preview-tabs-root' );
          if ( ! smPreviewTabs ) {
            smPreviewTabs = document.createElement( 'div' );
            smPreviewTabs.className = 'sm-preview-tabs-root';
          }
          if ( smPreviewTabs.previousElementSibling !== iframe.previousElementSibling || smPreviewTabs.nextElementSibling !== iframe ) {
            iframe.insertAdjacentElement( 'beforebegin', smPreviewTabs );
          }
          ReactDOM.render( <PreviewTabs smPanel={ smPanel } />, smPreviewTabs );

        } );
      } );
    } );
  } );
};
