class FakeElement {
  constructor() {
    this.attributes = new Map();
    this.listeners = {};
    this.hidden = false;
  }

  setAttribute( name, value ) {
    this.attributes.set( name, String( value ) );
  }

  getAttribute( name ) {
    return this.attributes.get( name ) ?? null;
  }

  addEventListener( type, callback ) {
    this.listeners[ type ] = this.listeners[ type ] || [];
    this.listeners[ type ].push( callback );
  }

  dispatchEvent( event ) {
    ( this.listeners[ event.type ] || [] ).forEach( ( callback ) => callback( event ) );
  }
}

const createContractDocument = () => {
  const rowA = new FakeElement();
  rowA.setAttribute( 'data-sm-lab-contract-row', 'active-palette' );
  rowA.setAttribute( 'aria-selected', 'true' );

  const rowB = new FakeElement();
  rowB.setAttribute( 'data-sm-lab-contract-row', 'contextual-palette' );
  rowB.setAttribute( 'aria-selected', 'false' );

  const panelA = new FakeElement();
  panelA.setAttribute( 'data-sm-lab-contract-panel', 'active-palette' );

  const panelB = new FakeElement();
  panelB.setAttribute( 'data-sm-lab-contract-panel', 'contextual-palette' );
  panelB.hidden = true;

  return {
    rowA,
    rowB,
    panelA,
    panelB,
    document: {
      querySelectorAll: ( selector ) => {
        if ( selector === '[data-sm-lab-contract-row]' ) {
          return [ rowA, rowB ];
        }

        if ( selector === '[data-sm-lab-contract-panel]' ) {
          return [ panelA, panelB ];
        }

        return [];
      },
    },
  };
};

export const runLabContractExplorerTests = async ( assert ) => {
  const { installContractExplorer, selectContract } = await import( '../../src/_js/lab-showcase/contract-explorer.js' );

  {
    const { document, rowA, rowB, panelA, panelB } = createContractDocument();

    selectContract( document, 'contextual-palette' );

    assert.equal( rowA.getAttribute( 'aria-selected' ), 'false' );
    assert.equal( rowB.getAttribute( 'aria-selected' ), 'true' );
    assert.equal( panelA.hidden, true );
    assert.equal( panelB.hidden, false );
  }

  {
    const { document, rowA, rowB, panelA, panelB } = createContractDocument();

    installContractExplorer( { document } );
    rowB.dispatchEvent( { type: 'click' } );

    assert.equal( rowA.getAttribute( 'aria-selected' ), 'false' );
    assert.equal( rowB.getAttribute( 'aria-selected' ), 'true' );
    assert.equal( panelA.hidden, true );
    assert.equal( panelB.hidden, false );
  }
};
