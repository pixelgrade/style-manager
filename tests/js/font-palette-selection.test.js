import { applyFontPaletteSelection } from '../../src/_js/customizer/font-palettes/utils.js';

export const runFontPaletteSelectionTests = async ( assert ) => {
  {
    const fontUpdates = [];
    const presetUpdates = [];

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
        setConnectedFieldsPreset: preset => {
          presetUpdates.push( preset );
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
    assert.deepEqual(
      presetUpdates,
      [ 'preset-1-7-5' ],
      'palette selection should also apply the mapped connected fields preset when provided'
    );
  }

  {
    const presetUpdates = [];

    applyFontPaletteSelection(
      {
        fontsLogic: {},
        connectedFieldsPreset: '',
      },
      {
        setFontSetting: () => {},
        setConnectedFieldsPreset: preset => {
          presetUpdates.push( preset );
        },
      }
    );

    assert.deepEqual(
      presetUpdates,
      [],
      'palette selection should leave the connected fields preset untouched when the palette does not define one'
    );
  }
};
