import React, { createContext, Fragment, useCallback, useContext, useEffect, useMemo, useState } from 'react';

import Worker from "worker-loader!./worker.js";

import { getSettingConfig, popFromBackArray, pushToBackArray } from "../../../global-service";
import { useCustomizeSettingCallback } from "../../../utils";
import ConfigContext from "../../context";
import { useTraceUpdate } from "../../../utils";

import { getColorOptionsIDs, getColorOptionsDefaults } from "../../utils"
import { SourceColors } from "../source-colors";
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
import fineTunePaletteIcon from "../../../svg/fine-tune-palette.svg";

export { Builder }
export * from './utils';

let myWorker = null;

try {
  myWorker = new Worker();
} catch (e) {

}

const withOptions = ( Component ) => {

  return ( props ) => {
    const settingsIDs = getColorOptionsIDs();
    const defaults = getColorOptionsDefaults();
    const [ options, setOptions ] = useState( defaults );

    useEffect( () => {
      const newOptions = {};

      settingsIDs.forEach( settingID => {
        wp.customize( settingID, setting => {
          newOptions[ settingID ] = setting();
        } );
      } );

      setOptions( newOptions );
    }, [] );

    useEffect( () => {
      const callbacks = {};

      settingsIDs.forEach( settingID => {
        wp.customize( settingID, setting => {
          callbacks[ settingID ] = newValue => {
            setOptions( { ...options, [settingID]: newValue } );
          };
          setting.bind( callbacks[ settingID ] );
        } );
      } );

      return () => {
        Object.keys( callbacks ).forEach( settingID => {
          wp.customize( settingID, setting => {
            setting.unbind( callbacks[ settingID ] );
          } )
        } )
      }
    }, [ options ] )

    return (
      <Component { ...props } options={ options } />
    )
  }
}

const Builder = withOptions( props => {
  const { options, sourceSettingID, outputSettingID } = props;
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
      // Use empty string instead of false since that is what the DB provides.
      // This way we avoid triggering a setting change when it really is not (false !== '' and the setting is updated).
      setting.set( ( activePreset === null ) ? true  : '' );
    } );

  }, [ activePreset ] );

  const updateSource = useCallback( newValue => {
    wp.customize( sourceSettingID, setting => {
      setting.set( getValueFromColors( newValue ) );
    } );
  }, [ sourceSettingID ] );

  useEffect(() => {
    const newPalettes = getPalettesFromColors( config, options );

    wp.customize( outputSettingID, setting => {
      setting.set( JSON.stringify( newPalettes ) );
    } );
  }, [ config, options ] );

  const onSourceChange = useCallback( ( newValue ) => {
    const newConfig = getColorsFromInputValue( newValue );
    setConfig( newConfig );
  }, [] );

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

  useCustomizeSettingCallback( sourceSettingID, onSourceChange, [ options ] );
  useCustomizeSettingCallback( outputSettingID, onOutputChange );
  useCustomizeSettingCallback( 'sm_site_color_variation', onSiteVariationChange );

  useEffect( () => {

    const callback = ( isExpanded ) => {

      if ( ! isExpanded ) {
        popFromBackArray();
      }
    }

    const sectionIDs = [
      'sm_color_usage_section',
      'sm_fine_tune_palette_section'
    ];

    sectionIDs.forEach( sectionID => {
      wp.customize.section( sectionID, section => {
        section.expanded.bind( callback );
      } )
    } );

    return () => {
      sectionIDs.forEach( sectionID => {
        wp.customize.section( sectionID, section => {
          section.expanded.unbind( callback );
        } )
      } );
    }
  }, [] );

  const providerValue = useMemo( () => {
    return {
      config: config,
      setConfig: updateSource,
      options: options,
      resetActivePreset
    }
  }, [ config, updateSource, options, resetActivePreset ] );

  const onPresetChange = useCallback( preset => {
    updateSource( preset.config );
    setActivePreset( preset.uid );
  }, [ updateSource, setActivePreset ] );

  return (
    <ConfigContext.Provider value={ providerValue }>
      <button onClick={() => { setConfig( [] ) } }>Aici</button>
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
            { styleManager.l10n.colorPalettes.builderColorUsagePanelLabel }
          </div>
        </div>
      </div>
      <div className="sm-group">
        <div className="sm-group__body">
          <Control label={ styleManager.l10n.colorPalettes.builderBrandColorsLabel }>
            <SourceColors
              sourceSetting={ sourceSetting }
              onChange={ () => {
                setActivePreset( null );
              } } />
            <style>{ CSSOutput }</style>
          </Control>
        </div>
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
      </div>
      <div className="sm-group">
        <Accordion>
          <AccordionSection title={ styleManager.l10n.colorPalettes.builderColorPresetsTitle } open={ true }>
            <div className="customize-control-description">
              { styleManager.l10n.colorPalettes.builderColorPresetsDesc }
            </div>
            <PresetsList
              active={ activePreset }
              onChange={ onPresetChange }
              updateSource={ updateSource }
              options={ options }
            />
          </AccordionSection>
          { !! myWorker &&
            <AccordionSection title={ styleManager.l10n.colorPalettes.builderImageExtractTitle }>
              <DropZone worker={ myWorker } />
            </AccordionSection>
          }
        </Accordion>
      </div>
    </ConfigContext.Provider>
  );
} );

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
