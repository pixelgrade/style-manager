export const applyFontPaletteSelection = (
  {
    fontsLogic = {},
    connectedFieldsPreset = '',
  },
  {
    setFontSetting,
    setConnectedFieldsPreset,
  }
) => {
  Object.entries( fontsLogic ).forEach( ( [ settingID, config ] ) => {
    setFontSetting( settingID, config );
  } );

  if ( connectedFieldsPreset ) {
    setConnectedFieldsPreset( connectedFieldsPreset );
  }
};
