import { pushToBackArray } from "../../global-service";
import customizeColorsUsageIcon from "../../svg/customize-colors-usage.svg";
import { usePopFromBackArray } from "../../hooks";

const ColorUsageButton = () => {
  usePopFromBackArray( 'sm_color_usage_section' );

  return (
    <div className="sm-panel-toggle" onClick={ () => {
      wp.customize.section( 'sm_color_usage_section', ( colorUsageSection ) => {
        pushToBackArray( colorUsageSection, 'sm_color_palettes_section' );
      } );
    } }>
      <div className="sm-panel-toggle__icon" dangerouslySetInnerHTML={ {
        __html: `
                <svg viewBox="${ customizeColorsUsageIcon.viewBox }">
                  <use xlink:href="#${ customizeColorsUsageIcon.id }" />
                </svg>`
      } }/>
      <div className="sm-panel-toggle__label">
        { styleManager.l10n.colorPalettes.builderColorUsagePanelLabel }
      </div>
    </div>
  )
}

export default ColorUsageButton;
