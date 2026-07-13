/* global wp */
/**
 * Style Manager's "Fonts" section for the Pixelgrade Design hub's Site Setup tab.
 *
 * Registered through the JS extension point the hub exposes on `wp.hooks`
 * (`pixelgrade.adminHub.setupSections`), and backed by Style Manager's own
 * `style_manager/v1/local-fonts` REST endpoints so this bundle never
 * duplicates the underlying local-fonts (self-hosted cloud fonts) logic --
 * the same endpoints the Settings screen equivalent could use.
 *
 * Deliberately dependency-light: everything comes off the `wp` global
 * (already loaded by the hub itself), no `@wordpress/*` package imports.
 */
import { FAMILY_STATUS, getFamilyStatusKey, sortFamilies } from './helpers';

const { addFilter } = wp.hooks;
const { createElement: el, useEffect, useState } = wp.element;
const { Button, Card, CardBody, Notice, Spinner, ToggleControl } = wp.components;
const { __ } = wp.i18n;

const REST_PATH = '/style_manager/v1/local-fonts';

/**
 * Literal `__()` calls per status key -- string extraction tools need the
 * arguments to be literal, not built or looked up dynamically.
 */
const FAMILY_STATUS_LABELS = {
	[ FAMILY_STATUS.HEALTHY ]: () => __( 'on your site', '__plugin_txtd' ),
	[ FAMILY_STATUS.FAILED ]: () => __( 'download hiccup, we’ll retry automatically', '__plugin_txtd' ),
	[ FAMILY_STATUS.NOT_DOWNLOADED ]: () => __( 'downloading soon', '__plugin_txtd' ),
};

const getFamilyStatusLabel = ( family ) => FAMILY_STATUS_LABELS[ getFamilyStatusKey( family ) ]();

const fetchStatus = () => wp.apiFetch( { path: REST_PATH } );

const saveEnabled = ( enabled ) => wp.apiFetch( {
	path: `${ REST_PATH }/settings`,
	method: 'POST',
	data: { enabled },
} );

const mirrorFonts = () => wp.apiFetch( {
	path: `${ REST_PATH }/mirror`,
	method: 'POST',
} );

/**
 * The compact family -> status list. Used families sort first (see
 * ./helpers), then alphabetically; an empty result gets a one-line fallback.
 */
const FamilyStatusList = ( { families } ) => {
	if ( ! families.length ) {
		return el(
			'p',
			{ className: 'sm-design-hub-fonts__empty' },
			__( 'No cloud fonts in use yet. Pick one and it’s saved to your site automatically.', '__plugin_txtd' )
		);
	}

	return el(
		'ul',
		{ className: 'sm-design-hub-fonts__list' },
		sortFamilies( families ).map( ( family ) => el(
			'li',
			{ key: family.family, className: 'sm-design-hub-fonts__list-item' },
			el( 'span', { className: 'sm-design-hub-fonts__family' }, family.display || family.family ),
			' — ',
			el(
				'span',
				{ className: 'sm-design-hub-fonts__status' },
				getFamilyStatusLabel( family )
			)
		) )
	);
};

/**
 * The Fonts section: loads its own status from REST on mount, and drives the
 * "host locally" toggle and the manual "mirror now" action straight against
 * the same endpoints the Settings screen equivalent uses.
 */
const FontsSection = () => {
	const [ state, setState ] = useState( { status: 'loading', data: null } );
	const [ savingToggle, setSavingToggle ] = useState( false );
	const [ mirroring, setMirroring ] = useState( false );
	const [ showSuccessNotice, setShowSuccessNotice ] = useState( false );

	const load = () => {
		setState( { status: 'loading', data: null } );
		fetchStatus()
			.then( ( data ) => setState( { status: 'ready', data } ) )
			.catch( () => setState( { status: 'error', data: null } ) );
	};

	useEffect( () => {
		load();
		// Only on mount -- `load` is stable enough for this component's lifetime.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [] );

	if ( 'loading' === state.status ) {
		return el( Card, {}, el( CardBody, {}, el( Spinner ) ) );
	}

	if ( 'error' === state.status ) {
		return el(
			Card,
			{},
			el(
				CardBody,
				{},
				el( 'p', {}, __( 'Something went wrong loading your fonts.', '__plugin_txtd' ) ),
				el( Button, { variant: 'secondary', onClick: load }, __( 'Retry', '__plugin_txtd' ) )
			)
		);
	}

	const { enabled, families, usedUnhealthyCount } = state.data;

	const handleToggle = ( nextEnabled ) => {
		setSavingToggle( true );
		saveEnabled( nextEnabled )
			.then( ( data ) => {
				setState( { status: 'ready', data } );
				setSavingToggle( false );
			} )
			.catch( () => setSavingToggle( false ) );
	};

	const handleMirror = () => {
		setMirroring( true );
		setShowSuccessNotice( false );
		mirrorFonts()
			.then( ( data ) => {
				setState( { status: 'ready', data } );
				setMirroring( false );
				if ( 0 === data.usedUnhealthyCount ) {
					setShowSuccessNotice( true );
				}
			} )
			.catch( () => setMirroring( false ) );
	};

	return el(
		Card,
		{},
		el(
			CardBody,
			{},
			el( 'h2', {}, __( 'Fonts', '__plugin_txtd' ) ),
			el(
				'p',
				{ className: 'sm-design-hub-fonts__description' },
				__(
					'Every cloud font you use is saved to your site and served from right here. Your visitors never connect to Pixelgrade, and your typography is yours to keep — it works forever, with or without us.',
					'__plugin_txtd'
				)
			),
			el( ToggleControl, {
				label: __( 'Keep fonts on this site', '__plugin_txtd' ),
				checked: !! enabled,
				disabled: savingToggle,
				onChange: handleToggle,
			} ),
			el( FamilyStatusList, { families: families || [] } ),
			!! enabled && usedUnhealthyCount > 0 && el(
				Button,
				{
					variant: 'primary',
					isBusy: mirroring,
					disabled: mirroring,
					onClick: handleMirror,
				},
				__( 'Save fonts to this site', '__plugin_txtd' )
			),
			showSuccessNotice && el(
				Notice,
				{
					status: 'success',
					isDismissible: true,
					onRemove: () => setShowSuccessNotice( false ),
				},
				__( 'Done — all your fonts now live on your site.', '__plugin_txtd' )
			)
		)
	);
};

addFilter(
	'pixelgrade.adminHub.setupSections',
	'style-manager/fonts',
	( sections ) => [
		...( Array.isArray( sections ) ? sections : [] ),
		{ id: 'fonts', order: 20, component: FontsSection },
	]
);

export { FontsSection };
