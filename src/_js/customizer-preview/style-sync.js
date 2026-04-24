export const bindPreviewSettings = ( {
  properKeys,
  customize,
  enqueue,
} ) => {
  properKeys.forEach( settingID => {
    customize( settingID, setting => {
      enqueue( settingID, setting() );

      if ( typeof setting.bind === 'function' ) {
        setting.bind( newValue => {
          enqueue( settingID, newValue );
        } );
      }
    } );
  } );
};
