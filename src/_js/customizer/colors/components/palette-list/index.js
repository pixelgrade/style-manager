import React, { useContext, useMemo } from 'react';
import { useTraceUpdate } from '../../../utils'
import { getTextHex } from "../../utils";
import ConfigContext from "../../context";
import { normalizeCloudPresets } from './utils';
import { getPalettesFromColors } from "../builder";
import getRandomStripes from "./get-random-stripes";
import './style.scss';

const presets = normalizeCloudPresets( styleManager.colorPalettes.palettes );

const PresetsList = ( props ) => {

  useTraceUpdate( props );

  return (
    <div className={ 'sm-presets-list' }>
      {
        presets.map( preset => {
          return (
            <PaletteListItem
              preset={ preset }
              key={ preset.uid }
              active={ preset.uid === props.active }
              onChange={ props.onChange }
              updateSource={ props.updateSource }
              options={ props.options } />
          );
        } )
      }
    </div>
  )
}

export const PaletteListItem = ( props ) => {

  const {
    preset,
    options,
    active,
    onChange,
    updateSource
  } = props;

  const { config, quote, image } = preset;

  useTraceUpdate( props );

  const palettes = useMemo( () => {
    return getPalettesFromColors( config, options ).filter( palette => {
      const id = `${ palette.id }`;
      return id.charAt( 0 ) !== '_';
    } );
  }, [ config, options ] );

  const sources = palettes.reduce( ( acc, palette ) => acc.concat( palette.source ), [] );
  const colors = palettes.reduce( ( acc, palette ) => acc.concat( palette.colors ), [] );

  colors.sort( ( c1, c2 ) => {
    let min1 = 21;
    let min2 = 21;
    sources.forEach( source => {
      const d1 = chroma.distance( source, c1.value );
      const d2 = chroma.distance( source, c2.value );
      min1 = d1 < min1 ? d1 : min1;
      min2 = d2 < min2 ? d2 : min2;
    } );
    return min1 - min2;
  } );

  const stripes = getRandomStripes();

  const filledStripes = stripes.map( ( stripe, index ) => {
    const random = Math.floor( Math.random() * colors.length );
    const color = index > colors.length - 1 ? colors[ random ].value : colors[ index ].value;
    return { ...stripe, color }
  } );

  const textColor = getTextHex( palettes[ 0 ], colors[ 0 ], options );

  return (
    <div className={ `sm-presets-list__item` } onClick={ () => {
      onChange( preset )
    } }>
      <div className={ `sm-presets-preview ${ active ? 'sm-presets-preview--active' : '' }` }
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
