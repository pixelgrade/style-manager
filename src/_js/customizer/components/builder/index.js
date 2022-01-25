import React, { Fragment, createContext, useContext, useEffect, useMemo } from 'react';

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

import {
  ColorsMasterProvider,
  PalettesContext
} from '../../components';

const OutputUpdater = () => {
  const palettes = useContext( PalettesContext );

  useEffect(() => {
    wp.customize( 'sm_advanced_palette_output', setting => {
      setting.set( JSON.stringify( palettes ) );
    } );
  }, [ palettes ] );

  return null;
};

export const Builder = ( props ) => {

  return (
    <ColorsMasterProvider { ...props }>
      <OutputUpdater />
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
          <AccordionSection title={ styleManager.l10n.colorPalettes.builderColorPresetsTitle } open={ true }>
            <div className="customize-control-description">
              { styleManager.l10n.colorPalettes.builderColorPresetsDesc }
            </div>
            <PaletteList />
          </AccordionSection>
          <AccordionSection title={ styleManager.l10n.colorPalettes.builderImageExtractTitle }>
            <DropZone />
          </AccordionSection>
        </Accordion>
      </div>
    </ColorsMasterProvider>
  );
};
