import ReactDOM from "react-dom";
import React, { useEffect } from "react";

import * as globalService from "../global-service";
import { getBackArray, getSettingConfig, pushToBackArray } from "../global-service";
import colorizeElementsIcon from "../svg/colorize-elements.svg";

import { initializePaletteBuilder } from './color-palette-builder';
import initializeColorPalettesPreview from './color-palettes-preview';

export const initializeColors = () => {

  initializePaletteBuilder( 'sm_advanced_palette_source', 'sm_advanced_palette_output' );

  wp.customize( 'sm_coloration_level', setting => {
    setting.bind( applyColorationValueToFields );
  } );

  initializeColorizeElementsButton();
  initializeColorPalettesPreview();
}

const ColorizeElementsButton = ( props ) => {

  const targetSectionID = `${ styleManager.config.options_name }[colors_section]`;

  useEffect( () => {

    const callback = ( isExpanded ) => {

      if ( ! isExpanded ) {
        const backArray = getBackArray();
        const targetSectionID = backArray.pop();

        if ( targetSectionID ) {
          wp.customize.section( targetSectionID, ( targetSection ) => {
            targetSection.focus();
          } );
        }
      }
    }

    const targetSection = wp.customize.section( targetSectionID );

    if ( ! targetSection ) {
      return;
    }

    targetSection.expanded.bind( callback );

    return () => {
      targetSection.expanded.unbind( callback );
    }

  }, [] );

  return (
    <div className="sm-group" style={ { marginTop: 0 } }>
      <div className="sm-panel-toggle" id="sm-colorize-elements-button" style={ { borderTopWidth: 0 } } onClick={ () => {
        wp.customize.section( targetSectionID, ( targetSection ) => {
          pushToBackArray( targetSection, 'sm_color_usage_section' );
        } );
      } }>
        <div className="sm-panel-toggle__icon" dangerouslySetInnerHTML={{
          __html: `
                <svg viewBox="${ colorizeElementsIcon.viewBox }">
                  <use xlink:href="#${ colorizeElementsIcon.id }" />
                </svg>`
        } } />
        <div className="sm-panel-toggle__label">
          { styleManager.l10n.colorPalettes.colorizeElementsPanelLabel }
        </div>
      </div>
    </div>
  )
}

const initializeColorizeElementsButton = () => {
  const target = document.getElementById( 'customize-control-sm_coloration_level_control' );
  const button = document.createElement( 'li' );

  button.setAttribute( 'class', 'customize-control' );
  button.setAttribute( 'style', 'padding: 0' );

  target.insertAdjacentElement( 'afterend', button );

  ReactDOM.render( <ColorizeElementsButton />, button );
}

const applyColorationValueToFields = ( colorationLevel ) => {

  const defaultColorationLevel = globalService.getSettingConfig( 'sm_coloration_level' ).default;
  const isDefaultColoration = colorationLevel === defaultColorationLevel;

  const settings = globalService.getSettings();

  const value = parseInt( colorationLevel, 10 );
  const threshold = value < 50 ? 4 : value < 75 ? 3 : value < 100 ? 2 : 1;

  Object.keys( settings ).forEach( settingID => {
    const config = getSettingConfig( settingID );

    if ( config?.type === 'sm_toggle' ) {
      const { coloration } = config;

      wp.customize( settingID, setting => {
        setting.set( isDefaultColoration ? config.default : coloration >= threshold );
      } );
    }
  } );

}
