import { applyFontPaletteSelection } from '../../src/_js/customizer/font-palettes/utils.js';

export const runFontPaletteSelectionTests = async ( assert ) => {
  {
    const fontUpdates = [];

    applyFontPaletteSelection(
      {
        fontsLogic: {
          sm_font_primary: { font_family: 'Trueno' },
          sm_font_body: { font_family: 'Trueno' },
        },
        connectedFieldsPreset: 'preset-1-7-5',
      },
      {
        setFontSetting: ( settingID, config ) => {
          fontUpdates.push( [ settingID, config ] );
        },
      }
    );

    assert.deepEqual(
      fontUpdates,
      [
        [ 'sm_font_primary', { font_family: 'Trueno' } ],
        [ 'sm_font_body', { font_family: 'Trueno' } ],
      ],
      'palette selection should update each font setting from the palette fonts logic'
    );
  }
};
