/**
 * Intro/exit motion lifecycle for the Site Editor preview boards.
 *
 * The overlay host is React-mounted only while a preview mode is active, so
 * exit motion needs a deferred unmount: the store clears immediately (every
 * Preview toggle reads "Preview" again), while the host keeps rendering the
 * last mode with an `is-exiting` phase until the exit animation finishes.
 * The animations themselves live in style.scss (`sm-se-board-*` keyframes);
 * this module owns the phase machine and its timings so they stay testable.
 *
 * Phases: 'entering' (intro animations armed) -> 'open' (animations released,
 * so live re-renders of the board don't replay them) -> 'exiting' -> null.
 */

// The frame zoom runs 450ms; content stagger tails off around 770ms and the
// close affordance settles at 450ms. Past 800ms everything is at rest.
export const INTRO_SETTLE_MS = 800;
// A preview-to-preview switch keeps the surface and cross-fades only the
// content (200ms fade + a compressed stagger tailing off around 455ms).
export const SWITCH_SETTLE_MS = 500;
export const EXIT_MS = 220;
// prefers-reduced-motion collapses both directions to a plain opacity fade.
export const REDUCED_MOTION_FADE_MS = 120;
// Auto-opened previews wait a beat so the section page paints first and the
// motion reads as a response to the navigation, not a flash over it.
export const AUTO_OPEN_DELAY_MS = 150;

export const prefersReducedMotion = () =>
  !! ( 'undefined' !== typeof window
    && window.matchMedia
    && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches );

export const getIntroSettleMs = () => ( prefersReducedMotion() ? REDUCED_MOTION_FADE_MS : INTRO_SETTLE_MS );

export const getSwitchSettleMs = () => ( prefersReducedMotion() ? REDUCED_MOTION_FADE_MS : SWITCH_SETTLE_MS );

export const getExitMs = () => ( prefersReducedMotion() ? REDUCED_MOTION_FADE_MS : EXIT_MS );

export const clearOverlayRender = () => ( { mode: null, context: null, phase: null } );

/**
 * What the overlay host should render for the store state that just arrived,
 * given what it currently renders. `rendered` is `{ mode, context, phase }`.
 */
export const resolveOverlayRender = ( rendered, store ) => {
  if ( store.mode ) {
    const reentering = ! rendered.mode || 'exiting' === rendered.phase;
    // A mode -> mode switch (e.g. Colors -> Live Site, or the Tweak Board's
    // group previews) keeps the surface and cross-fades only the content:
    // the frame zoom is reserved for the editor <-> preview boundary, but a
    // hard cut would be the original instant-swap problem in miniature.
    // Mid-entrance switches keep 'entering' — the frame zoom is still
    // playing on the persistent content container, and the incoming
    // content picks up the entrance stagger on its fresh nodes.
    const switching = ! reentering
      && store.mode !== rendered.mode
      && 'entering' !== rendered.phase;

    return {
      mode: store.mode,
      context: store.context || null,
      phase: reentering ? 'entering' : ( switching ? 'switching' : rendered.phase ),
    };
  }

  if ( ! rendered.mode ) {
    return rendered;
  }

  return 'exiting' === rendered.phase ? rendered : { ...rendered, phase: 'exiting' };
};

/**
 * 'entering' and 'switching' settle into 'open': the fill-mode animations are
 * released once everything is at rest, so palette/font changes that re-render
 * the board's React tree mid-session don't replay the stagger on the new nodes.
 */
export const settleOverlayRender = rendered =>
  ( 'entering' === rendered.phase || 'switching' === rendered.phase
    ? { ...rendered, phase: 'open' }
    : rendered );

/**
 * Debounced auto-open for previews triggered by navigation rather than a
 * click. Returns a cancel function.
 */
export const schedulePreviewAutoOpen = ( open, delay = AUTO_OPEN_DELAY_MS ) => {
  const timer = setTimeout( open, delay );

  return () => clearTimeout( timer );
};
