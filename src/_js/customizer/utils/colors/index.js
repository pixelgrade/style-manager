/**
 * Customizer-side view of the shared color-generator math.
 *
 * Everything pure now lives in `src/_js/shared/color-generator/colors.js` — the module the
 * Node build artifact (`dist/node/palette-generator.js`) also imports, which is the drift
 * guard. This file re-exports it unchanged and adds back the one browser-only piece:
 * `getColorOptionsDefaults()`, which reads the option config PHP localizes into the
 * `styleManager` global. That read is what kept the generator un-importable from Node, so
 * it stays on this side of the seam and is injected into `getPalettesFromColors()` instead.
 */
import { getSettingConfig } from "../../global-service";
import { getColorOptionsIDs } from "../../../shared/color-generator/colors.js";

export * from "../../../shared/color-generator/colors.js";

export const getColorOptionsDefaults = () => {
  const settingsIDs = getColorOptionsIDs();
  const defaults = {};

  settingsIDs.forEach( settingID => {
    const config = getSettingConfig( settingID );

    if ( typeof config === 'undefined' || typeof config.default === 'undefined' ) {
      defaults[ settingID ] = '#000';
      return;
    }

    defaults[ settingID ] = config.default;
  } );

  return defaults;
};
