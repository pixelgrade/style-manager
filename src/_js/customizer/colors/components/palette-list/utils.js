import { getPalettesFromColors } from "../builder";
import getRandomStripes from "./get-random-stripes";

const normalizeCloudPresets = ( presets ) => {
  return Object.keys( presets ).filter( key => {
    const preset = presets[ key ];

    return Array.isArray( preset.color_groups ) && preset.color_groups.length;
  } ).map( key => {
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

      sources.sort( ( a, b ) => a._priority - b._priority );

      return {
        ...other,
        sources,
        uid: _uid,
      };
    } );

    colorGroups.sort( ( a, b ) => a._priority - b._priority );

    const palettes = getPalettesFromColors( colorGroups );

    return {
      uid: preset.hashid,
      config: colorGroups,
      stripes: getRandomStripes( palettes ),
      textColor: '#FFFFFF',
      image: preset?.preview?.background_image_url,
      quote: preset?.description
    }
  } );
}

export { normalizeCloudPresets }
