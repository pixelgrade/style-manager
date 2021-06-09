import React, { useCallback, useEffect, useState } from 'react';

import { popFromBackArray, pushToBackArray } from "../../../global-service";
import { useCustomizeSettingCallback } from "../../../utils";

import { SourceColors } from "../source-colors";
import ConfigContext from "../../context";
import DropZone from "../dropzone";
import PresetsList from '../palette-list';
import { Accordion, AccordionSection } from '../accordion';

import {
  getColorsFromInputValue,
  getPalettesFromColors,
  getCSSFromPalettes,
  getValueFromColors,
} from "./utils";

import customizeColorsUsageIcon from "../../../svg/customize-colors-usage.svg";

export { Builder }
export * from './utils';

const Builder = ( props ) => {
  const { sourceSettingID, outputSettingID } = props;
  const sourceSetting = wp.customize( sourceSettingID );
  const [ config, setConfig ] = useState( getColorsFromInputValue( sourceSetting() ) );
  const [ CSSOutput, setCSSOutput ] = useState( '' );

  const activePresetSetting = wp.customize( 'sm_color_palette_in_use' );
  const activePresetValue = activePresetSetting ? activePresetSetting() : null;
  const [ activePreset, setActivePreset ] = useState( activePresetValue );

  const resetActivePreset = useCallback( () => { setActivePreset( null ) }, [] );

  useEffect( () => {

    wp.customize( 'sm_color_palette_in_use', setting => {
      setting.set( activePreset );
    } );

    wp.customize( 'sm_is_custom_color_palette', setting => {
      setting.set( activePreset === null );
    } );

  }, [ activePreset ] );

  const updateSource = ( newValue ) => {
    wp.customize( sourceSettingID, setting => {
      setting.set( getValueFromColors( newValue ) );
    } );
  }

  const onSourceChange = ( newValue ) => {
    const newConfig = getColorsFromInputValue( newValue );
    const newPalettes = getPalettesFromColors( newConfig );

    setConfig( getColorsFromInputValue( newValue ) );

    wp.customize( outputSettingID, setting => {
      setting.set( JSON.stringify( newPalettes ) );
    } );
  }

  const onOutputChange = ( value ) => {
    const palettes = JSON.parse( value );

    wp.customize( 'sm_site_color_variation', setting => {
      const variation = setting();
      setCSSOutput( getCSSFromPalettes( palettes, variation ) );
    } );
  }

  const onSiteVariationChange = ( newVariation ) => {
    wp.customize( outputSettingID, setting => {
      const output = setting();
      const palettes = JSON.parse( output );
      setCSSOutput( getCSSFromPalettes( palettes, newVariation ) );
    } );
  }

  useCustomizeSettingCallback( sourceSettingID, onSourceChange );
  useCustomizeSettingCallback( outputSettingID, onOutputChange );
  useCustomizeSettingCallback( 'sm_site_color_variation', onSiteVariationChange );

  useEffect( () => {

    const callback = ( isExpanded ) => {

      if ( ! isExpanded ) {
        popFromBackArray();
      }
    }

    const sourceSection = wp.customize.section( 'sm_color_usage_section' );

    if ( ! sourceSection ) {
      return;
    }

    sourceSection.expanded.bind( callback );

    return () => {
      sourceSection.expanded.unbind( callback );
    }
  }, [] );

  return (
    <ConfigContext.Provider value={ { config: config, setConfig: updateSource, resetActivePreset } }>
      <div className="sm-group">
        <div className="sm-panel-toggle" onClick={ () => {
          wp.customize.section( 'sm_color_usage_section', ( colorUsageSection ) => {
            pushToBackArray( colorUsageSection, 'sm_color_palettes_section' );
          } );
        } }>
          <div className="sm-panel-toggle__icon" dangerouslySetInnerHTML={{
            __html: `
                <svg viewBox="${ customizeColorsUsageIcon.viewBox }">
                  <use xlink:href="#${ customizeColorsUsageIcon.id }" />
                </svg>`
          } } />
          <div className="sm-panel-toggle__label">
            Customize colors usage
          </div>
        </div>
      </div>
      <div className="sm-group">
        <div className="sm-group__body">
          <Control label={ 'Brand Colors' }>
            <SourceColors
              sourceSetting={ sourceSetting }
              onChange={ () => {
                setActivePreset( null );
              } } />
            <style>{ CSSOutput }</style>
          </Control>
        </div>
      </div>
      <div className="sm-group">
        <Accordion>
          <AccordionSection title={ 'Explore colors' } open={ true }>
            <div className="customize-control-description">
              Curated color presets to help you lay the foundations of the color system and make it easy to get started.
            </div>
            <PresetsList active={ activePreset } onChange={ ( preset ) => {
              updateSource( preset.config );
              setActivePreset( preset.uid );
            } } />
          </AccordionSection>
          <AccordionSection title={ 'Extract from Image' }>
            <DropZone />
          </AccordionSection>
        </Accordion>
      </div>
    </ConfigContext.Provider>
  );
}

const Control = ( props ) => {
  const { label, children } = props;

  return (
    <div className="sm-control">
      { label &&
        <div className="sm-control__header">
          <div className="sm-control__label">{ label }</div>
        </div>
      }
      { children && <div className="sm-control__body">{ children }</div> }
    </div>
  )
}
