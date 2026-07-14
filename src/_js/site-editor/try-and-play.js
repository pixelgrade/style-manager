/**
 * Try & Play — a reusable, feature-agnostic trial-overlay component.
 *
 * For a group of gated-but-functional controls, the explanation sits in normal
 * flow in an intro ABOVE the controls (covering nothing); only a primary
 * "Try and play" button floats over a soft, blurred scrim that dims the controls
 * underneath (visible, but not interactive or tabbable while covered). Clicking
 * the button dissolves the scrim, restores the controls, and the same intro
 * switches to a slim, persistent trial reminder. The dismissal is remembered for
 * the page load, so moving between tabs and back doesn't re-prompt — but a fresh
 * load re-discloses it.
 *
 * It hardcodes NOTHING product-, Plus-, or color-specific: every string, link,
 * id and badge is passed in, so the same module backs fonts, spacing, or any
 * future gated-preview surface.
 *
 * Framework-light: vanilla DOM, no React dependency. The scrim is positioned
 * absolutely over `targetEl`, so `targetEl` must be a positioned ancestor of the
 * controls it covers (handled by the shipped `.sm-tap-host` helper applied here);
 * the intro is inserted as `targetEl`'s previous sibling, so it stays uncovered.
 *
 * ONE BOUNDARY PER TAB (pixelgrade-plus/docs/plus-gating-ui-contract.md,
 * "merged groups"): a tab must never stack several of these boundaries. SM
 * satisfies the rule by construction — every gated surface mounts exactly ONE
 * Try & Play per tab, and `targetEl` can already span any number of control
 * groups. Keep it that way when adding surfaces; Nova Blocks enforces the same
 * rule with its <TryAndPlayGroup> wrapper.
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
/**
 * Build the optional trailing CTA icon. Only the 'play' triangle is built in;
 * pass a falsy `icon` to omit it. Inline SVG so there's no icon-font or React
 * dependency (the component stays framework-light + reusable).
 *
 * @param {string|boolean} icon
 *
 * @return {SVGElement|null}
 */
const buildPlayIcon = icon => {
	if ( 'play' !== icon ) {
		return null;
	}

	const ns = 'http://www.w3.org/2000/svg';
	const svg = document.createElementNS( ns, 'svg' );
	svg.setAttribute( 'class', 'sm-tap-scrim__button-icon' );
	svg.setAttribute( 'viewBox', '0 0 24 24' );
	svg.setAttribute( 'width', '18' );
	svg.setAttribute( 'height', '18' );
	svg.setAttribute( 'aria-hidden', 'true' );
	svg.setAttribute( 'focusable', 'false' );

	const path = document.createElementNS( ns, 'path' );
	path.setAttribute( 'd', 'M8 5v14l11-7z' );
	path.setAttribute( 'fill', 'currentColor' );
	svg.appendChild( path );

	return svg;
};

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
 * Mount a Try & Play trial surface over a target element.
 *
 * @param {HTMLElement} targetEl The element wrapping the gated controls. The
 *                               scrim is positioned over it (it gets the
 *                               `sm-tap-host` class); the intro is inserted as
 *                               its previous sibling so it stays uncovered.
 * @param {Object}      options
 * @param {string}      options.id          Unique key for sessionStorage
 *                                          dismissal (e.g. the section id).
 * @param {boolean}     options.locked      When false, nothing renders and the
 *                                          controls are left fully normal.
 * @param {string}      [options.overlayText] The intro line shown above the
 *                                          controls BEFORE reveal (decoded).
 * @param {string}      [options.buttonLabel] Primary button label
 *                                          (default "Try and play").
 * @param {{label?: string, url?: string}} [options.learnMore] Quiet learn-more
 *                                          link shown in the intro.
 * @param {string}      [options.bannerText]  The reminder the same intro shows
 *                                          AFTER reveal (decoded).
 * @param {string}      [options.badge]       Short badge label (e.g. "Plus").
 * @param {HTMLElement} [options.controlsEl]  The subtree to make inert while
 *                                          covered. Defaults to `targetEl`.
 * @param {boolean}     [options.autoFocus=true] Move focus to the Try-and-play
 *                                          button when the scrim appears.
 *
 * @return {{ destroy: function(): void, isRevealed: function(): boolean }}
 *         A handle. `destroy()` removes the intro/scrim and restores the
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
		icon = 'play',
		learnMore = null,
		bannerText = '',
		badge = '',
		autoFocus = true,
		onActivate = null,
	} = options;

	const storageKey = `${ STORAGE_PREFIX }${ LOAD_NONCE }:${ id || 'default' }`;

	// The group whose controls go inert under the scrim.
	const controlsEl = options.controlsEl || targetEl;

	targetEl.classList.add( 'sm-tap-host' );

	let scrimEl = null;
	let tabListener = null;
	let introEl = null;
	let introTextEl = null;
	let revealed = isRevealed( storageKey );

	// --- The persistent intro, in normal flow ABOVE the controls ------------
	// Covers nothing. Before reveal it carries the invitation; after reveal it
	// switches to the slim trial reminder. The button is NOT here — it floats
	// over the scrim, the only thing that overlaps the controls.

	const buildIntro = () => {
		const intro = document.createElement( 'div' );
		intro.className = 'sm-tap-intro' + ( revealed ? ' is-revealed' : '' );
		intro.setAttribute( 'role', 'note' );

		if ( badge ) {
			const badgeEl = document.createElement( 'span' );
			badgeEl.className = 'sm-tap-intro__badge';
			badgeEl.textContent = decodeHtmlText( badge );
			intro.appendChild( badgeEl );
		}

		const text = document.createElement( 'span' );
		text.className = 'sm-tap-intro__text';
		text.textContent = decodeHtmlText( revealed ? ( bannerText || overlayText ) : overlayText );
		intro.appendChild( text );
		introTextEl = text;

		const link = buildLearnMore( learnMore, 'sm-tap-intro__link' );
		if ( link ) {
			intro.appendChild( link );
		}

		return intro;
	};

	const mountIntro = () => {
		if ( introEl ) {
			return;
		}
		introEl = buildIntro();
		if ( introEl && targetEl.parentNode ) {
			targetEl.parentNode.insertBefore( introEl, targetEl );
		}
	};

	// --- The soft scrim (pre-reveal) — JUST the button floats over it --------

	const reveal = ( { focusControls = true } = {} ) => {
		if ( revealed ) {
			return;
		}
		revealed = true;
		markRevealed( storageKey );

		// Restore the controls to fully interactive + tabbable.
		setGroupCovered( controlsEl, false );

		// Mark the host as "playing" so its State-2 texture (the soft diagonal
		// "this isn't saved" affordance) shows behind the now-live controls.
		targetEl.classList.add( 'is-playing' );

		// The intro stays put; swap the invitation for the slim trial reminder.
		if ( introEl ) {
			introEl.classList.add( 'is-revealed' );
		}
		if ( introTextEl && bannerText ) {
			introTextEl.textContent = decodeHtmlText( bannerText );
		}

		if ( scrimEl ) {
			scrimEl.classList.add( 'is-leaving' );
			const remove = () => {
				if ( scrimEl ) {
					scrimEl.remove();
					scrimEl = null;
				}
			};
			// Honour the fade-out, but never strand the scrim if transitionend
			// doesn't fire (reduced motion, display:none, etc.).
			scrimEl.addEventListener( 'transitionend', remove, { once: true } );
			window.setTimeout( remove, 400 );
		}

		// Move focus into the controls so keyboard users land where they can act,
		// instead of being stranded on a button that just vanished.
		if ( focusControls ) {
			const focusable = controlsEl.querySelector(
				'input, select, textarea, button, a[href], [tabindex]:not([tabindex="-1"])'
			);
			if ( focusable && 'function' === typeof focusable.focus ) {
				focusable.focus();
			}
		}
	};

	const buildScrim = () => {
		const scrim = document.createElement( 'div' );
		scrim.className = 'sm-tap-scrim';
		// Modeless: the rest of the editor stays usable; this is an invitation,
		// not a modal wall.
		scrim.setAttribute( 'role', 'group' );

		const button = document.createElement( 'button' );
		button.type = 'button';
		// WP-core primary Button look.
		button.className = 'components-button is-primary sm-tap-scrim__button';

		const labelEl = document.createElement( 'span' );
		labelEl.className = 'sm-tap-scrim__button-label';
		labelEl.textContent = decodeHtmlText( buttonLabel );
		button.appendChild( labelEl );

		const iconEl = buildPlayIcon( icon );
		if ( iconEl ) {
			button.appendChild( iconEl );
		}

		button.addEventListener( 'click', () => reveal() );

		scrim.appendChild( button );
		scrim._tapButton = button;
		return scrim;
	};

	// --- Initial state ------------------------------------------------------

	mountIntro();

	if ( revealed ) {
		// Already playing this session: no scrim, but keep the State-2 texture.
		targetEl.classList.add( 'is-playing' );
	} else {
		setGroupCovered( controlsEl, true );
		scrimEl = buildScrim();
		targetEl.appendChild( scrimEl );

		if ( autoFocus && scrimEl._tapButton ) {
			// Defer so the element is laid out (and the sidebar has settled)
			// before we pull focus. Only focus when the scrim is actually visible
			// (offsetParent is null while its tab is hidden) and the user/editor
			// hasn't already landed in a text field — never yank focus out from
			// under someone or fight the editor's own settling.
			window.requestAnimationFrame( () => {
				const button = scrimEl && scrimEl._tapButton;
				if ( ! button || null === button.offsetParent ) {
					return;
				}
				const active = document.activeElement;
				const isEditingElsewhere = active
					&& active !== document.body
					&& ! scrimEl.contains( active )
					&& /^(INPUT|SELECT|TEXTAREA)$/.test( active.tagName );
				if ( ! isEditingElsewhere ) {
					button.focus();
				}
			} );
		}
	}

	// When this gated tab is activated (a locked user lands on it), let the caller
	// react once per page load — used to auto-open the live Preview so the trial's
	// changes are actually visible. The activation event bubbles up to document.
	if ( onActivate ) {
		const previewKey = `${ STORAGE_PREFIX }${ LOAD_NONCE }:preview:${ id || 'default' }`;
		tabListener = event => {
			if ( ! event || ! event.detail || event.detail.id !== id || isRevealed( previewKey ) ) {
				return;
			}
			markRevealed( previewKey );
			onActivate();
		};
		document.addEventListener( 'sm:site-editor-tab-activated', tabListener );
	}

	return {
		destroy: () => {
			if ( tabListener ) {
				document.removeEventListener( 'sm:site-editor-tab-activated', tabListener );
				tabListener = null;
			}
			if ( scrimEl ) {
				scrimEl.remove();
				scrimEl = null;
			}
			if ( introEl ) {
				introEl.remove();
				introEl = null;
			}
			setGroupCovered( controlsEl, false );
			targetEl.classList.remove( 'sm-tap-host', 'is-playing' );
		},
		isRevealed: () => revealed,
	};
};

export default mountTryAndPlay;
