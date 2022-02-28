import { insertShortcutAfter } from "../utils";
import { ColorizeElementsButton } from "../components";
import { applyColorationValueToFields } from './apply-coloration-value-to-fields';
import { initializePaletteBuilder } from './initialize-palette-builder';
import { initializeColorPalettesPreview } from './initialize-color-palettes-preview';

export const initializeColors = () => {

  initializePaletteBuilder( 'sm_advanced_palette_source', 'sm_advanced_palette_output' );

  wp.customize( 'sm_coloration_level', setting => {
    setting.bind( applyColorationValueToFields );
  } );

  insertShortcutAfter( 'customize-control-sm_coloration_level_control', ColorizeElementsButton );

  initializeColorPalettesPreview();
};
