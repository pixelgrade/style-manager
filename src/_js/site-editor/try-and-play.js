/**
 * Try & Play — a reusable, feature-agnostic trial-overlay component.
 *
 * Drops a soft, semi-transparent overlay over a group of gated-but-functional
 * controls. The real controls stay VISIBLE underneath (slightly dimmed) but are
 * not interactive or tabbable while covered. A primary "Try and play" button
 * dissolves the overlay, restores the controls, and replaces it with a slim,
 * persistent trial-reminder banner. The dismissal is remembered per browser
 * session (sessionStorage), so moving between tabs and back doesn't re-prompt —
 * but a fresh page load re-discloses it.
 *
 * It hardcodes NOTHING product-, Plus-, or color-specific: every string, link,
 * id and badge is passed in, so the same module backs fonts, spacing, or any
 * future gated-preview surface.
 *
 * Framework-light: vanilla DOM, no React dependency. The overlay is positioned
 * absolutely over `targetEl`, so `targetEl` must be a positioned ancestor of
 * the controls it covers (the caller is responsible for `position: relative`,
 * or relies on the shipped `.sm-tap-host` helper class applied here).
 *
 * @since 2.4.0
 */

const STORAGE_PREFIX = 'smTryAndPlay:';

/**
 * A stable-per-document-load nonce, so a dismissal counts only for THIS page
 * load. We persist in sessionStorage (the brief: keyed by id), which survives
 * both tab-switches AND reloads within a tab. We want it to survive the former
 * (don't re-prompt while moving between SM tabs) but NOT the latter (a fresh
 * page load re-discloses the trial). Folding the document's load timestamp into
 * the key threads that needle: the same document keeps one nonce for its whole
 * life (tab-switches match), while every reload / new document mints a new one
 * (the old dismissal no longer matches, so the overlay returns).
 *
 * @return {string}
 */
const getLoadNonce = () => {
	try {
		// timeOrigin is the document's navigation start — unique per load,
		// constant for the document's lifetime.
		return String( Math.round( window.performance.timeOrigin || 0 ) );
	} catch ( e ) {
		return '0';
	}
};

const LOAD_NONCE = getLoadNonce();

/**
 * Whether a Try & Play surface has already been revealed for this page load.
 *
 * @param {string} key Storage key (already namespaced + load-scoped).
 *
 * @return {boolean}
 */
const isRevealed = key => {
	try {
		return '1' === window.sessionStorage.getItem( key );
	} catch ( e ) {
		// Private mode / storage disabled: treat as not-yet-revealed (the
		// overlay simply re-shows; never blocks the editor).
		return false;
	}
};

/**
 * Persist that a Try & Play surface has been revealed for this page load.
 *
 * @param {string} key Storage key (already namespaced + load-scoped).
 */
const markRevealed = key => {
	try {
		window.sessionStorage.setItem( key, '1' );
	} catch ( e ) {
		// No-op: dismissal just won't persist across tab switches.
	}
};

/**
 * Decode HTML entities arriving from PHP-escaped copy (esc_html'd notes).
 *
 * @param {string} text
 *
 * @return {string}
 */
const decodeHtmlText = text => {
	const decoder = document.createElement( 'textarea' );
	decoder.innerHTML = text || '';
	return decoder.value;
};

/**
 * Build a small "Learn more →" link from a `{ label, url }` descriptor.
 *
 * @param {{label?: string, url?: string}} learnMore
 * @param {string}                         className
 *
 * @return {HTMLAnchorElement|null}
 */
const buildLearnMore = ( learnMore, className ) => {
	if ( ! learnMore || ! learnMore.url ) {
		return null;
	}

	const link = document.createElement( 'a' );
	link.className = className;
	link.href = learnMore.url;
	link.target = '_blank';
	link.rel = 'noopener noreferrer';
	link.textContent = `${ decodeHtmlText( learnMore.label || 'Learn more' ) } →`;
	return link;
};

/**
 * Toggle the "covered" state of a control group: while covered, the controls are
 * dimmed, non-interactive and out of the tab order; revealed restores them.
 *
 * Prefers the native `inert` attribute (removes the subtree from focus, the a11y
 * tree, and pointer events in one shot); falls back to aria-hidden + a sentinel
 * class the stylesheet uses to disable pointer events for older engines.
 *
 * @param {HTMLElement} groupEl
 * @param {boolean}     covered
 */
const setGroupCovered = ( groupEl, covered ) => {
	groupEl.classList.toggle( 'sm-tap-covered', covered );

	if ( 'inert' in HTMLElement.prototype ) {
		groupEl.inert = covered;
	} else if ( covered ) {
		groupEl.setAttribute( 'aria-hidden', 'true' );
	} else {
		groupEl.removeAttribute( 'aria-hidden' );
	}
};

/**
 * Mount a Try & Play trial overlay over a target element.
 *
 * @param {HTMLElement} targetEl The element wrapping the gated controls. The
 *                               overlay is positioned over it; it gets the
 *                               `sm-tap-host` class (which sets up relative
 *                               positioning). The controls themselves are
 *                               expected to be its children (or, when
 *                               `controlsEl` is given, that subtree).
 * @param {Object}      options
 * @param {string}      options.id          Unique key for sessionStorage
 *                                          dismissal (e.g. the section id).
 * @param {boolean}     options.locked      When false, nothing renders and the
 *                                          controls are left fully normal.
 * @param {string}      [options.overlayText] Short explanatory line on the
 *                                          overlay (HTML entities are decoded).
 * @param {string}      [options.buttonLabel] Primary button label
 *                                          (default "Try and play").
 * @param {{label?: string, url?: string}} [options.learnMore] Quiet learn-more
 *                                          link, shown on the overlay and in the
 *                                          persistent banner.
 * @param {string}      [options.bannerText]  Slim persistent reminder shown
 *                                          AFTER reveal (HTML entities decoded).
 * @param {string}      [options.badge]       Short badge label (e.g. "Plus").
 * @param {HTMLElement} [options.controlsEl]  The subtree to make inert while
 *                                          covered. Defaults to `targetEl`'s
 *                                          existing children at mount time.
 * @param {boolean}     [options.autoFocus=true] Move focus to the Try-and-play
 *                                          button when the overlay appears.
 *
 * @return {{ destroy: function(): void, isRevealed: function(): boolean }}
 *         A handle. `destroy()` removes the overlay/banner and restores the
 *         controls (does NOT clear the session dismissal).
 */
export const mountTryAndPlay = ( targetEl, options = {} ) => {
	const noop = { destroy: () => {}, isRevealed: () => true };

	// Unlocked (entitled), or nothing to cover: render nothing, touch nothing.
	if ( ! targetEl || ! options.locked ) {
		return noop;
	}

	const {
		id,
		overlayText = '',
		buttonLabel = 'Try and play',
		learnMore = null,
		bannerText = '',
		badge = '',
		autoFocus = true,
	} = options;

	const storageKey = `${ STORAGE_PREFIX }${ LOAD_NONCE }:${ id || 'default' }`;

	// The group whose controls go inert under the overlay. Snapshot the current
	// children into a wrapper-agnostic NodeList reference via the target itself.
	const controlsEl = options.controlsEl || targetEl;

	targetEl.classList.add( 'sm-tap-host' );

	let overlayEl = null;
	let bannerEl = null;
	let revealed = isRevealed( storageKey );

	// --- The slim persistent banner (post-reveal) ---------------------------

	const buildBanner = () => {
		if ( ! bannerText && ! learnMore ) {
			return null;
		}

		const banner = document.createElement( 'div' );
		banner.className = 'sm-tap-banner';
		banner.setAttribute( 'role', 'status' );

		if ( badge ) {
			const badgeEl = document.createElement( 'span' );
			badgeEl.className = 'sm-tap-banner__badge';
			badgeEl.textContent = decodeHtmlText( badge );
			banner.appendChild( badgeEl );
		}

		if ( bannerText ) {
			const text = document.createElement( 'span' );
			text.className = 'sm-tap-banner__text';
			text.textContent = decodeHtmlText( bannerText );
			banner.appendChild( text );
		}

		const link = buildLearnMore( learnMore, 'sm-tap-banner__link' );
		if ( link ) {
			banner.appendChild( link );
		}

		return banner;
	};

	const showBanner = () => {
		if ( bannerEl ) {
			return;
		}
		bannerEl = buildBanner();
		if ( bannerEl ) {
			targetEl.insertBefore( bannerEl, targetEl.firstChild );
		}
	};

	// --- The soft overlay (pre-reveal) --------------------------------------

	const reveal = ( { focusControls = true } = {} ) => {
		if ( revealed ) {
			return;
		}
		revealed = true;
		markRevealed( storageKey );

		// Restore the controls to fully interactive + tabbable.
		setGroupCovered( controlsEl, false );

		if ( overlayEl ) {
			overlayEl.classList.add( 'is-leaving' );
			const remove = () => {
				if ( overlayEl ) {
					overlayEl.remove();
					overlayEl = null;
				}
			};
			// Honour the fade-out, but never strand the overlay if the
			// transitionend doesn't fire (reduced motion, display:none, etc.).
			overlayEl.addEventListener( 'transitionend', remove, { once: true } );
			window.setTimeout( remove, 400 );
		}

		showBanner();

		// Move focus into the controls so keyboard users land where they can
		// act, instead of being stranded on a button that just vanished.
		if ( focusControls ) {
			const focusable = controlsEl.querySelector(
				'input, select, textarea, button, a[href], [tabindex]:not([tabindex="-1"])'
			);
			if ( focusable && 'function' === typeof focusable.focus ) {
				focusable.focus();
			}
		}
	};

	const buildOverlay = () => {
		const overlay = document.createElement( 'div' );
		overlay.className = 'sm-tap-overlay';
		// Modeless: the rest of the editor stays usable; this is an invitation,
		// not a modal wall.
		overlay.setAttribute( 'role', 'group' );

		const inner = document.createElement( 'div' );
		inner.className = 'sm-tap-overlay__inner';

		if ( badge ) {
			const badgeEl = document.createElement( 'span' );
			badgeEl.className = 'sm-tap-overlay__badge';
			badgeEl.textContent = decodeHtmlText( badge );
			inner.appendChild( badgeEl );
		}

		if ( overlayText ) {
			const text = document.createElement( 'p' );
			text.className = 'sm-tap-overlay__text';
			text.textContent = decodeHtmlText( overlayText );
			inner.appendChild( text );
		}

		const button = document.createElement( 'button' );
		button.type = 'button';
		// WP-core primary Button look.
		button.className = 'components-button is-primary sm-tap-overlay__button';
		button.textContent = decodeHtmlText( buttonLabel );
		button.addEventListener( 'click', () => reveal() );
		inner.appendChild( button );

		const link = buildLearnMore( learnMore, 'sm-tap-overlay__link' );
		if ( link ) {
			inner.appendChild( link );
		}

		overlay.appendChild( inner );
		overlay._tapButton = button;
		return overlay;
	};

	// --- Initial state ------------------------------------------------------

	if ( revealed ) {
		// Already tried this session: controls normal, slim banner only.
		setGroupCovered( controlsEl, false );
		showBanner();
	} else {
		setGroupCovered( controlsEl, true );
		overlayEl = buildOverlay();
		targetEl.appendChild( overlayEl );

		if ( autoFocus && overlayEl._tapButton ) {
			// Defer so the element is laid out (and the sidebar has settled)
			// before we pull focus. Only focus when the overlay is actually
			// visible (offsetParent is null while its tab is hidden) and the
			// user/editor hasn't already landed in a text field — never yank
			// focus out from under someone or fight the editor's own settling.
			window.requestAnimationFrame( () => {
				const button = overlayEl && overlayEl._tapButton;
				if ( ! button || null === button.offsetParent ) {
					return;
				}
				const active = document.activeElement;
				const isEditingElsewhere = active
					&& active !== document.body
					&& ! overlayEl.contains( active )
					&& /^(INPUT|SELECT|TEXTAREA)$/.test( active.tagName );
				if ( ! isEditingElsewhere ) {
					button.focus();
				}
			} );
		}
	}

	return {
		destroy: () => {
			if ( overlayEl ) {
				overlayEl.remove();
				overlayEl = null;
			}
			if ( bannerEl ) {
				bannerEl.remove();
				bannerEl = null;
			}
			setGroupCovered( controlsEl, false );
			targetEl.classList.remove( 'sm-tap-host' );
		},
		isRevealed: () => revealed,
	};
};

export default mountTryAndPlay;
