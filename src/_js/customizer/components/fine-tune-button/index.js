import { popFromBackArray, pushToBackArray } from "../../global-service";
import fineTunePaletteIcon from "../../svg/fine-tune-palette.svg";
import { usePopFromBackArray } from "../../hooks";

const FineTuneButton = () => {
  usePopFromBackArray( 'sm_fine_tune_palette_section' );

  return (
    <div className="sm-panel-toggle" onClick={ () => {
      wp.customize.section( 'sm_fine_tune_palette_section', ( fineTuneSection ) => {
        pushToBackArray( fineTuneSection, 'sm_color_palettes_section' );
      } );
    } }>
      <div className="sm-panel-toggle__icon" dangerouslySetInnerHTML={{
        __html: `
                <svg viewBox="${ fineTunePaletteIcon.viewBox }">
                  <use xlink:href="#${ fineTunePaletteIcon.id }" />
                </svg>`
      } } />
      <div className="sm-panel-toggle__label">
        { styleManager.l10n.colorPalettes.builderFineTunePanelLabel }
      </div>
    </div>
  )
}

export default FineTuneButton;
