import './style.scss';

import React, { useCallback, useContext, useMemo } from 'react';

import { useActivePreset, useUpdateSourceSetting } from '../../hooks'
import { getBestColor, getPalettesFromColors } from '../../utils';
import { OptionsContext } from '../../components';

import { normalizeCloudPresets } from './utils';
import getRandomStripes from "./get-random-stripes";

const presets = normalizeCloudPresets( styleManager.colorPalettes.palettes );

const PresetsList = ( props ) => {
  return (
    <div className={ 'sm-presets-list' }>
      { presets.map( preset => <PaletteListItem preset={ preset } key={ preset.uid } /> ) }
    </div>
  )
}

export const PaletteListItem = ( props ) => {
  const { preset } = props;
  const { quote, image, uid } = preset;
  const options = useContext( OptionsContext );
  const [ activePreset, setActivePreset ] = useActivePreset();
  const updateSourceSetting = useUpdateSourceSetting();

  const onChange = useCallback( preset => {
    updateSourceSetting( preset.config );
    setActivePreset( preset.uid );
  }, [] );

  const palettes = useMemo( () => {
    return getPalettesFromColors( preset.config, options ).filter( palette => {
      const id = `${ palette.id }`;
      return id.charAt( 0 ) !== '_';
    } );
  }, [ preset.config, options ] );

  const colors = useMemo( () => {
    const sources = palettes.reduce( ( acc, palette ) => acc.concat( palette.source ), [] );
    const colors = palettes.reduce( ( acc, palette ) => acc.concat( palette.colors ), [] );

    colors.sort( ( c1, c2 ) => {
      let min1 = 21;
      let min2 = 21;
      sources.forEach( source => {
        const d1 = chroma.distance( source, c1 );
        const d2 = chroma.distance( source, c2 );
        min1 = d1 < min1 ? d1 : min1;
        min2 = d2 < min2 ? d2 : min2;
      } );
      return min1 - min2;
    } );

    return colors;
  }, [ palettes ] );

  const stripes = useMemo( getRandomStripes, [] );

  const filledStripes = useMemo( () => {

    return stripes.map( ( stripe, index ) => {
      const random = Math.floor( Math.random() * colors.length );
      const color = index > colors.length - 1 ? colors[ random ] : colors[ index ];
      return { ...stripe, color }
    } );

  }, [ stripes, colors ] );

  const textColor = useMemo( () => {
    return getBestColor( colors[0], ['#FFFFFF', '#000000'], 4.5, true );
  }, [ palettes, colors ] );

  return (
    <div className={ `sm-presets-list__item` } onClick={ () => { onChange( preset ) } }>
      <div className={ `sm-presets-preview ${ uid === activePreset ? 'sm-presets-preview--active' : '' }` }
           style={ { backgroundImage: `url(${ image })` } }>
        { quote && <div className="sm-presets-preview__quote" style={ { color: textColor } }>{ quote }</div> }
        <div className="sm-presets-preview__stripes">
          { filledStripes.map( ( stripe, index ) => {
            return (
              <div key={ index }
                   className={ `sm-presets-preview__stripe sm-presets-preview__stripe-w${ stripe.width } sm-presets-preview__stripe-p${ stripe.pos }` }>
                <div className="sm-presets-preview__pixel" style={ { color: stripe.color } }/>
              </div>
            );
          } ) }
        </div>
      </div>
    </div>
  )
}

export default PresetsList;
