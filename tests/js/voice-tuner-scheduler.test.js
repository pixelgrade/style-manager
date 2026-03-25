import { createVoiceTunerUpdateScheduler } from '../../src/_js/customizer/font-palettes/voice-tuner-scheduler.js';

export const runVoiceTunerSchedulerTests = async ( assert ) => {
  const queuedFrames = [];
  let nextFrameId = 1;
  const cancelledFrameIds = [];
  const flushCalls = [];

  const scheduler = createVoiceTunerUpdateScheduler( {
    requestFrame: callback => {
      queuedFrames.push( callback );
      return nextFrameId++;
    },
    cancelFrame: frameId => {
      cancelledFrameIds.push( frameId );
    },
    flush: () => {
      flushCalls.push( flushCalls.length + 1 );
    },
  } );

  scheduler.schedule();

  assert.equal( flushCalls.length, 0, 'schedule should not flush synchronously' );
  assert.equal( queuedFrames.length, 1, 'schedule should queue one animation frame' );

  scheduler.schedule();

  assert.equal( queuedFrames.length, 1, 'multiple schedule calls in one frame should be coalesced' );

  queuedFrames.shift()();

  assert.equal( flushCalls.length, 1, 'queued animation frame should flush once' );

  scheduler.schedule();

  assert.equal( queuedFrames.length, 1, 'scheduler should allow a new frame after flushing' );

  scheduler.cancel();

  assert.deepEqual( cancelledFrameIds, [ 2 ], 'cancel should forward the pending frame id' );
};
