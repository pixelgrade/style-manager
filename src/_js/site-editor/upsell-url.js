/**
 * The Plus upsell URL with feature attribution, so the landing page knows
 * which gated feature the visitor came from and can highlight/personalize
 * accordingly.
 *
 * Parameter contract is canonical in
 * pixelgrade-plus/docs/plus-gating-copy.md (mirrored by Nova Blocks'
 * plusUpsellUrl in plus-gate/index.js): utm_source = the plugin,
 * utm_medium = the link surface ('plus-gate' trial banner or 'save-plus'
 * save affordance), utm_campaign = 'try-and-play', utm_content = the feature
 * context (the Try & Play surface id, or the save-affordance context).
 * Applied on top of the (filterable) base URL from the payload.
 *
 * @param {Object} plusPayload           The localized `plus` payload.
 * @param {Object} [options]
 * @param {string} [options.medium]  Link surface: 'plus-gate' | 'save-plus'.
 * @param {string} [options.content] Feature context (surface/section id).
 *
 * @return {string} The decorated URL, or '' when no base URL is configured.
 */
export const plusUpsellUrl = ( plusPayload, { medium = 'plus-gate', content = '' } = {} ) => {
	const base = plusPayload && plusPayload.upsellUrl;

	if ( ! base ) {
		return '';
	}

	const params = new URLSearchParams( {
		utm_source: 'style-manager',
		utm_medium: medium,
		utm_campaign: 'try-and-play',
	} );

	if ( content ) {
		params.set( 'utm_content', content );
	}

	return base + ( base.includes( '?' ) ? '&' : '?' ) + params.toString();
};

export default plusUpsellUrl;
