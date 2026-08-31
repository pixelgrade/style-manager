/**
 * Re-export of the shared palette-source parser.
 *
 * The implementation lives in `src/_js/shared/color-generator/parse-source.js` so the
 * Customizer bundle and the Node build artifact parse `sm_advanced_palette_source`
 * identically — including the transient `showPicker` strip, which changes the generated
 * output if it is skipped.
 */
export { getColorsFromInputValue, stripTransientSourceColorState } from "../../shared/color-generator/parse-source.js";
