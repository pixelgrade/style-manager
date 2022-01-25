import React, { useEffect } from "react";
import { getBackArray, pushToBackArray } from "../../global-service";
import colorizeElementsIcon from "../../svg/colorize-elements.svg";

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
    };

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
};

export default ColorizeElementsButton;
