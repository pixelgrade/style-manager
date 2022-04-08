import React, { useContext, useEffect } from 'react';

import {
  Accordion,
  AccordionSection,
  DropZone,
  PaletteList,
  FineTuneColorsShortcut,
  ColorsUsageShortcut,
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
      <ColorsUsageShortcut />
      <div className="sm-group">
        <div className="sm-group__body">
          <Control
            label={ styleManager.l10n.colorPalettes.builderBrandColorsLabel }
            description={ styleManager.l10n.colorPalettes.builderBrandColorsDesc }
          >
            <SourceColors />
            <ColorsStyleTag />
          </Control>
        </div>
      </div>
      <FineTuneColorsShortcut />
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
