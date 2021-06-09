import { getPalettesFromColors } from "../builder";
import getRandomStripes from "./get-random-stripes";
import getTextColor from "./get-text-color";

const normalizeCloudPresets = ( presets ) => {
  return Object.keys( presets ).map( key => {
    const preset = presets[ key ];

    const colorGroups = preset.color_groups.map( group => {
      const { _uid, ...other } = group;

      const sources = group.sources.map( source => {
        const { color, _uid, ...other } = source;

        return {
          ...other,
          uid: _uid,
          value: color
        }
      } );

      sources.sort( ( a, b ) => a._priority > b._priority ? 1 : -1 );

      return {
        ...other,
        sources,
        uid: _uid,
      };
    } );

    colorGroups.sort( ( a, b ) => a._priority > b._priority ? 1 : -1 );

    const palettes = getPalettesFromColors( colorGroups );

    return {
      uid: preset.hashid,
      config: colorGroups,
      stripes: getRandomStripes( palettes ),
      textColor: getTextColor( palettes[0] ),
      image: preset?.preview?.background_image_url,
      quote: preset?.description
    }
  } );
}

export { normalizeCloudPresets }
