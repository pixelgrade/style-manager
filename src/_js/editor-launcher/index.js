/**
 * Lightweight Style Manager launcher for post editor screens.
 *
 * This bundle only opens the current entry in the Site Editor with the Style
 * Manager sidebar visible. The full design controls stay in the Site Editor.
 */
import './style.scss';

import { Button } from '@wordpress/components';
import { createElement, Fragment, useState } from '@wordpress/element';
import { dispatch, select } from '@wordpress/data';
import { registerPlugin } from '@wordpress/plugins';
import { PluginSidebar, PluginSidebarMoreMenuItem } from '@wordpress/editor';

const data = 'undefined' !== typeof window ? window._styleManagerEditorLauncher || {} : {};

function getCopy() {
	return data.copy || {};
}

function hasUnsavedEditorChanges() {
	let editor;

	try {
		editor = select( 'core/editor' );
	} catch ( error ) {
		return false;
	}

	if ( ! editor ) {
		return false;
	}

	const isDirty = 'function' === typeof editor.isEditedPostDirty && editor.isEditedPostDirty();
	const hasChangedContent = 'function' === typeof editor.hasChangedContent && editor.hasChangedContent();

	return Boolean( isDirty || hasChangedContent );
}

async function saveCurrentEntryIfNeeded() {
	if ( ! hasUnsavedEditorChanges() ) {
		return true;
	}

	let editorDispatch;

	try {
		editorDispatch = dispatch( 'core/editor' );
	} catch ( error ) {
		return false;
	}

	if ( ! editorDispatch || 'function' !== typeof editorDispatch.savePost ) {
		return false;
	}

	try {
		await editorDispatch.savePost();
	} catch ( error ) {
		return false;
	}

	return ! hasUnsavedEditorChanges();
}

function StyleManagerLauncher() {
	const copy = getCopy();
	const [ isSaving, setIsSaving ] = useState( false );
	const [ saveFailed, setSaveFailed ] = useState( false );

	if ( ! data.targetUrl || ! PluginSidebar || ! PluginSidebarMoreMenuItem ) {
		return null;
	}

	const icon = data.icon || 'admin-customizer';
	const title = copy.title || '';
	const menuLabel = copy.menuLabel || title;
	const buttonLabel = isSaving ? copy.savingLabel : copy.buttonLabel;

	const openStyleManager = async () => {
		setSaveFailed( false );
		setIsSaving( true );

		const canNavigate = await saveCurrentEntryIfNeeded();

		setIsSaving( false );

		if ( ! canNavigate ) {
			setSaveFailed( true );
			return;
		}

		window.location.assign( data.targetUrl );
	};

	return createElement(
		Fragment,
		null,
		createElement(
			PluginSidebarMoreMenuItem,
			{ target: 'pixelgrade-style-manager-launcher', icon },
			menuLabel
		),
		createElement(
			PluginSidebar,
			{
				name: 'pixelgrade-style-manager-launcher',
				title,
				icon,
			},
			createElement(
				'div',
				{ className: 'style-manager-editor-launcher' },
				createElement(
					'div',
					{ className: 'style-manager-editor-launcher__card' },
					copy.eyebrow && createElement(
						'p',
						{ className: 'style-manager-editor-launcher__eyebrow' },
						copy.eyebrow
					),
					copy.heading && createElement(
						'h2',
						{ className: 'style-manager-editor-launcher__title' },
						copy.heading
					),
					copy.description && createElement(
						'p',
						{ className: 'style-manager-editor-launcher__description' },
						copy.description
					),
					createElement(
						Button,
						{
							variant: 'primary',
							icon,
							isBusy: isSaving,
							disabled: isSaving,
							onClick: openStyleManager,
						},
						buttonLabel
					),
					saveFailed && copy.saveError && createElement(
						'p',
						{ className: 'style-manager-editor-launcher__error' },
						copy.saveError
					)
				)
			)
		)
	);
}

if ( data.targetUrl ) {
	registerPlugin( 'pixelgrade-style-manager-launcher', {
		render: StyleManagerLauncher,
		icon: data.icon || 'admin-customizer',
	} );
}
