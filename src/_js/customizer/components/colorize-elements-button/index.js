import React, { useEffect } from "react";
import { getBackArray, pushToBackArray } from "../../global-service";
import { CustomizerShortcut } from '../index';
import colorizeElementsIcon from "../../svg/colorize-elements.svg";

const ColorizeElementsButton = ( props ) => {
  const currentSectionID = 'sm_color_usage_section';
  const targetSectionID = `${ styleManager.config.options_name }[colors_section]`;
  const label = styleManager.l10n.colorPalettes.colorizeElementsPanelLabel;

  const icon = `
                <svg viewBox="${ colorizeElementsIcon.viewBox }">
                  <use xlink:href="#${ colorizeElementsIcon.id }" />
                </svg>`

  return (
    <CustomizerShortcut
      currentSectionID={ currentSectionID }
      targetSectionID={ targetSectionID }
      icon={ icon }
      label={ label }
    />
  )
};

export default ColorizeElementsButton;
