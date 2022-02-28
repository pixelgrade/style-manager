import { popFromBackArray, pushToBackArray } from "../../global-service";
import fineTuneIcon from "../../svg/fine-tune-palette.svg";
import { CustomizerShortcut } from "../index";

const FineTuneColorsShortcut = () => {
  const currentSectionID = 'sm_color_palettes_section';
  const targetSectionID = 'sm_fine_tune_color_palette_section';
  const label = styleManager.l10n.colorPalettes.builderFineTuneColorsLabel;

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
  );
};

export default FineTuneColorsShortcut;
