/**
 * Pure helpers for the Design hub "Fonts" section -- kept dependency-free and
 * separate from the React component so they can be unit tested directly.
 */

/**
 * Status keys a family entry can resolve to. Kept separate from the actual
 * (translatable) copy so the labels stay literal `__()` calls at the call
 * site -- translation string extraction needs literal arguments, not ones
 * built or looked up dynamically.
 */
export const FAMILY_STATUS = {
	HEALTHY: 'healthy',
	FAILED: 'failed',
	NOT_DOWNLOADED: 'not_downloaded',
};

/**
 * Resolve one cloud font family entry to a status key.
 *
 * @param {Object}  family
 * @param {boolean} family.healthy Whether the family is mirrored locally and serving fine.
 * @param {string}  family.status  Raw mirror status ('', 'ok', 'failed', ...).
 *
 * @return {string} One of the `FAMILY_STATUS` keys.
 */
export const getFamilyStatusKey = ( family ) => {
	const { healthy, status } = family || {};

	if ( healthy ) {
		return FAMILY_STATUS.HEALTHY;
	}

	if ( 'failed' === status ) {
		return FAMILY_STATUS.FAILED;
	}

	return FAMILY_STATUS.NOT_DOWNLOADED;
};

/**
 * Sort families: used families first, then alphabetically by display name.
 *
 * @param {Array} families
 *
 * @return {Array} A new, sorted array -- the input is never mutated.
 */
export const sortFamilies = ( families ) => {
	if ( ! Array.isArray( families ) ) {
		return [];
	}

	return [ ...families ].sort( ( a, b ) => {
		if ( !! a.used !== !! b.used ) {
			return a.used ? -1 : 1;
		}

		const aLabel = a.display || a.family || '';
		const bLabel = b.display || b.family || '';

		return aLabel.localeCompare( bLabel );
	} );
};
