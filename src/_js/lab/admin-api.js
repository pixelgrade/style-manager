export const fetchLabConfig = async ( { ajaxUrl, nonce } ) => {
  const response = await window.fetch( ajaxUrl, {
    method: 'POST',
    credentials: 'same-origin',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
    },
    body: new URLSearchParams( {
      action: 'style_manager_lab_config',
      nonce,
    } ),
  } );

  if ( ! response.ok ) {
    throw new Error( `Lab config request failed with ${ response.status }` );
  }

  const payload = await response.json();

  if ( ! payload?.success || ! payload?.data ) {
    throw new Error( 'Lab config payload was not successful' );
  }

  return payload.data;
};
