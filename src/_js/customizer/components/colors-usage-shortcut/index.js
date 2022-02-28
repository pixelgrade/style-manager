import { pushToBackArray } from "../../global-service";
import colorsUsageIcon from "../../svg/customize-colors-usage.svg";
import { usePopFromBackArray } from "../../hooks";
import { CustomizerShortcut } from '../index';

const ColorsUsageShortcut = () => {
  const currentSectionID = 'sm_color_palettes_section';
  const targetSectionID = 'sm_color_usage_section';
  const label = styleManager.l10n.colorPalettes.builderColorUsagePanelLabel;

  const icon = `
                <svg viewBox="${ colorsUsageIcon.viewBox }">
                  <use xlink:href="#${ colorsUsageIcon.id }" />
                </svg>`;

  return (
    <CustomizerShortcut
      currentSectionID={ currentSectionID }
      targetSectionID={ targetSectionID }
      icon={ icon }
      label={ label }
    />
  )
};

export default ColorsUsageShortcut;
