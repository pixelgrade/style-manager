export const selectContract = ( documentRef, id ) => {
  documentRef.querySelectorAll( '[data-sm-lab-contract-row]' ).forEach( ( row ) => {
    const isSelected = row.getAttribute( 'data-sm-lab-contract-row' ) === id;
    row.setAttribute( 'aria-selected', isSelected ? 'true' : 'false' );
  } );

  documentRef.querySelectorAll( '[data-sm-lab-contract-panel]' ).forEach( ( panel ) => {
    panel.hidden = panel.getAttribute( 'data-sm-lab-contract-panel' ) !== id;
  } );
};

const activateRow = ( documentRef, row ) => {
  const id = row.getAttribute( 'data-sm-lab-contract-row' );

  if ( id ) {
    selectContract( documentRef, id );
  }
};

export const installContractExplorer = ( windowRef = window ) => {
  const documentRef = windowRef.document;

  documentRef.querySelectorAll( '[data-sm-lab-contract-row]' ).forEach( ( row ) => {
    row.addEventListener( 'click', () => activateRow( documentRef, row ) );
    row.addEventListener( 'keydown', ( event ) => {
      if ( event.key !== 'Enter' && event.key !== ' ' ) {
        return;
      }

      event.preventDefault?.();
      activateRow( documentRef, row );
    } );
  } );
};
