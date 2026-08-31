/**
 * Customizer-side entry to the shared color-generator.
 *
 * The math lives in `src/_js/shared/color-generator/` so the Customizer bundle and the
 * `dist/node/palette-generator.js` build artifact run the *same* code — that identity is
 * what makes `wp pixelgrade sm apply-color-palette` reproduce browser output byte for byte.
 *
 * The only thing added here is the option-defaults table the browser reads from the
 * `styleManager` global. It used to be captured once at module-load time; it is now
 * resolved per call and injected, which is why the shared module has no global dependency.
 */
import { getPalettesFromColors as generatePalettesFromColors } from "../../shared/color-generator/index.js";
import { getColorOptionsDefaults } from "./colors";

export { getFunctionalColors } from "../../shared/color-generator/index.js";

export const getPalettesFromColors = ( colorGroups, opts = {}, defaults = null ) => {
  return generatePalettesFromColors( colorGroups, opts, defaults || getColorOptionsDefaults() );
};
