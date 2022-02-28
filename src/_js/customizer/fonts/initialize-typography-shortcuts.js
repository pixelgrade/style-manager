import ReactDOM from "react-dom";

import { insertShortcutAfter } from "../utils";
import { getBackArray, pushToBackArray } from "../global-service";
import { CustomizerShortcut } from '../components/index';
import fineTuneIcon from "../svg/fine-tune-palette.svg";

const FineTuneTypographyShortcut = ( props ) => {
  const targetSectionID = 'sm_fine_tune_font_palette_section';
  const currentSectionID = 'sm_font_palettes_section';
  const label = styleManager.l10n.colorPalettes.builderFineTuneTypographyLabel;

  const icon = `
                <svg viewBox="${ fineTuneIcon.viewBox }">
                  <use xlink:href="#${ fineTuneIcon.id }" />
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

const FontElementsConfigurationShortcut = ( props ) => {
  const currentSectionID = 'sm_fine_tune_font_palette_section';
  const targetSectionID = `${ styleManager.config.options_name }[fonts_section]`;
  const label = styleManager.l10n.colorPalettes.builderFineTuneTypographyLabel;

  const icon = `
                <svg viewBox="${ fineTuneIcon.viewBox }">
                  <use xlink:href="#${ fineTuneIcon.id }" />
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

export const initializeTypographyShortcuts = () => {
    insertShortcutAfter( 'customize-control-sm_font_sizing_control', FineTuneTypographyShortcut );
    insertShortcutAfter( 'customize-control-sm_fonts_connected_fields_preset_control', FontElementsConfigurationShortcut );
};
