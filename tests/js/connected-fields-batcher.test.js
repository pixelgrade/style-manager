import { createConnectedFieldsBatcher } from '../../src/_js/customizer/fonts/connected-fields/batcher.js';

export const runConnectedFieldsBatcherTests = async ( assert ) => {
  {
    const flushed = [];
    const batcher = createConnectedFieldsBatcher( ( settingID, fontsLogic ) => {
      flushed.push( [ settingID, fontsLogic ] );
    } );

    batcher.schedule( 'sm_font_primary', { value: 'first' } );

    assert.deepEqual( flushed, [
      [ 'sm_font_primary', { value: 'first' } ],
    ], 'non-batched updates should flush immediately' );
  }

  {
    const flushed = [];
    const batcher = createConnectedFieldsBatcher( ( settingID, fontsLogic ) => {
      flushed.push( [ settingID, fontsLogic ] );
    } );

    batcher.run( () => {
      batcher.schedule( 'sm_font_primary', { value: 'before' } );
      batcher.schedule( 'sm_font_primary', { value: 'after' } );
      batcher.schedule( 'sm_font_secondary', { value: 'secondary' } );

      assert.equal( flushed.length, 0, 'batched updates should stay queued until flush' );
    } );

    assert.deepEqual( flushed, [
      [ 'sm_font_primary', { value: 'after' } ],
      [ 'sm_font_secondary', { value: 'secondary' } ],
    ], 'batched updates should flush once per setting using the latest payload' );
  }

  {
    const flushed = [];
    const batcher = createConnectedFieldsBatcher( ( settingID, fontsLogic ) => {
      flushed.push( [ settingID, fontsLogic ] );
    } );

    batcher.run( () => {
      batcher.schedule( 'sm_font_primary', { value: 'outer' } );

      batcher.run( () => {
        batcher.schedule( 'sm_font_primary', { value: 'inner' } );
      } );

      assert.equal( flushed.length, 0, 'nested batches should wait for the outer flush' );
    } );

    assert.deepEqual( flushed, [
      [ 'sm_font_primary', { value: 'inner' } ],
    ], 'nested batches should still collapse to a single final flush' );
  }
};
