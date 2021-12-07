import React, { Fragment, useContext, useEffect } from 'react';

import { getPalettesFromColors } from "../../utils";

import {
  Accordion,
  AccordionSection,
  DropZone,
  PaletteList,
  FineTuneButton,
  ColorUsageButton,
  ColorsStyleTag,
  Control,
  SourceColors,
} from '../index';

import ConfigContext, { ConfigProvider } from "../config-context";
import OptionsContext, { OptionsProvider } from "../options-context";

const App = () => {
  const { options } = useContext( OptionsContext );
  const { config, outputSettingID } = useContext( ConfigContext );

  useEffect(() => {
    requestIdleCallback( () => {
      wp.customize( outputSettingID, setting => {
        const newPalettes = getPalettesFromColors( config, options );
        setting.set( JSON.stringify( newPalettes ) );
      } );
    } );
  }, [ config, options ] );

  return (
    <Fragment>
      <div className="sm-group">
        <ColorUsageButton />
      </div>
      <div className="sm-group">
        <div className="sm-group__body">
          <Control label={ styleManager.l10n.colorPalettes.builderBrandColorsLabel }>
            <SourceColors />
            <ColorsStyleTag />
          </Control>
        </div>
        <FineTuneButton />
      </div>
      <div className="sm-group">
        <Accordion>
          <AccordionSection title={ styleManager.l10n.colorPalettes.builderImageExtractTitle }>
            <DropZone />
          </AccordionSection>
          <AccordionSection title={ styleManager.l10n.colorPalettes.builderColorPresetsTitle } open={ true }>
            <div className="customize-control-description">
              { styleManager.l10n.colorPalettes.builderColorPresetsDesc }
            </div>
            <PaletteList />
          </AccordionSection>
        </Accordion>
      </div>
    </Fragment>
  );
}

export const Builder = ( props ) => {
  return (
    <ConfigProvider { ...props }>
      <OptionsProvider>
        <App />
      </OptionsProvider>
    </ConfigProvider>
  )
}
