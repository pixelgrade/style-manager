<?php
/**
 * Extra File
 *
 * Contains extra functions
 *
 * @package Style-Manager
 */

/**
 * Helper function to prefix options, metas IDs.
 *
 * @param string $option The option ID to prefix.
 * @param string $separator Optional. The separator to use between prefix and the rest.
 * @return string
 */
function sm_prefix( $option, $separator = '_' ) {
	return Style_Manager_Metaboxes::instance()->prefix( $option, $separator );
}

/**
 * Helper function to get/return the Style_Manager_Settings object
 *
 * @param object $parent Main Style_Manager instance.
 * @return Style_Manager_Settings
 */
function sm_settings( $parent = null ) {
	return Style_Manager_Settings::instance( $parent );
}

/**
 * Wrapper function around cmb2_get_option
 * @since  0.1.0
 * @param  string  $key Options array key
 * @return mixed        Option value
 */
function sm_get_setting( $key ) {
	$instance = sm_settings();
	return cmb2_get_option( $instance->key, $instance->prefix( $key ) );
}

/**
 * Wrapper function around cmb2_update_option
 * @since  0.1.0
 * @param  string  $key Options array key
 * @param  mixed   $value      Value to update data with
 * @param  boolean $single     Whether data should not be an array
 * @return bool           Success/Failure
 */
function sm_update_setting( $key, $value, $single = true ) {
	$instance = sm_settings();
	return cmb2_update_option( $instance->key, $instance->prefix( $key ), $value, $single );
}

/**
 * Decode a hashed ID
 *
 * @param int $ID
 *
 * @return string|bool
 */
function sm_hash_encode_ID( $ID ) {
	if ( null === $ID ) {
		return false;
	}

	/** @var Style_Manager_Plugin $local_plugin */
	$local_plugin = Style_Manager();
	//we expect the hash parameter to be our hashed ID
	//encode the ID
	return $local_plugin->hash_encode_ID( $ID );
}

/**
 * Decode a hashed ID
 *
 * @param string $hash
 *
 * @return bool|int Returns false if the ID couldn't be decoded, else returns int
 */
function sm_hash_decode_ID( $hash ) {
	/** @var Style_Manager_Plugin $local_plugin */
	$local_plugin = Style_Manager();
	//we expect the hash parameter to be our hashed ID
	//decode the hashed ID
	return $local_plugin->hash_decode_ID( $hash );
}

function sm_array_sort( $array, $on, $order = SORT_ASC ) {
	$new_array = array();
    $sortable_array = array();

    if ( count( $array ) > 0 ) {
	    foreach ( $array as $k => $v ) {
		    if ( is_array( $v ) ) {
			    foreach ( $v as $k2 => $v2 ) {
				    if ( $k2 == $on ) {
					    $sortable_array[ $k ] = $v2;
				    }
			    }
		    } else {
			    $sortable_array[ $k ] = $v;
		    }
	    }

	    switch ( $order ) {
		    case SORT_ASC:
			    asort( $sortable_array );
			    break;
		    case SORT_DESC:
			    arsort( $sortable_array );
			    break;
	    }

	    foreach ( $sortable_array as $k => $v ) {
		    $new_array[ $k ] = $array[ $k ];
	    }
    }

    return $new_array;
}


/**
 * Reads the requested portion of a file and sends its contents to the client with the appropriate headers.
 *
 * This HTTP_RANGE compatible read file function is necessary for allowing streaming media to be skipped around in.
 *
 * @param string $location
 * @param string $filename
 * @param string $mimeType
 * @return void
 *
 * @link https://groups.google.com/d/msg/jplayer/nSM2UmnSKKA/Hu76jDZS4xcJ
 * @link http://php.net/manual/en/function.readfile.php#86244
 */
function sm_smartReadFile($location, $filename, $mimeType = 'application/octet-stream')
{
	if (!file_exists($location))
	{
		header ("HTTP/1.1 404 Not Found");
		return;
	}

	$size	= filesize($location);
	$time	= date('r', filemtime($location));

	$fm		= @fopen($location, 'rb');
	if (!$fm)
	{
		header ("HTTP/1.1 505 Internal server error");
		return;
	}

	$begin	= 0;
	$end	= $size - 1;

	if (isset($_SERVER['HTTP_RANGE']))
	{
		if (preg_match('/bytes=\h*(\d+)-(\d*)[\D.*]?/i', $_SERVER['HTTP_RANGE'], $matches))
		{
			$begin	= intval($matches[1]);
			if (!empty($matches[2]))
			{
				$end	= intval($matches[2]);
			}
		}
	}

	if (isset($_SERVER['HTTP_RANGE']))
	{
		header('HTTP/1.1 206 Partial Content');
	}
	else
	{
		header('HTTP/1.1 200 OK');
	}

	header("Content-Type: $mimeType");
	header('Cache-Control: public, must-revalidate, max-age=0');
	header('Pragma: no-cache');
	header('Accept-Ranges: bytes');
	header('Content-Length:' . (($end - $begin) + 1));
	if (isset($_SERVER['HTTP_RANGE']))
	{
		header("Content-Range: bytes $begin-$end/$size");
	}
	header("Content-Disposition: inline; filename=$filename");
	header("Content-Transfer-Encoding: binary");
	header("Last-Modified: $time");

	$cur	= $begin;
	fseek($fm, $begin, 0);

	while(!feof($fm) && $cur <= $end && (connection_status() == 0))
	{
		print fread($fm, min(1024 * 16, ($end - $cur) + 1));
		$cur += 1024 * 16;
	}
}

function sm_get_string_between( $string, $start, $end = null ){
	$string = ' ' . $string;
    $ini = strpos($string, $start);
    if ($ini == 0) return '';
    $ini += strlen($start);

    // If we couldn't find the end marker or it is null we will return everything 'till the end
    if ( null === $end || false === strpos($string, $end, $ini) ) {
		return substr($string, $ini);
    } else {
	    $len = strpos( $string, $end, $ini ) - $ini;

	    return substr( $string, $ini, $len );
    }
}

/**
 * Get the complete current URL including query args
 * @return string
 */
function sm_get_current_url() {
	//@todo we should do this is more standard WordPress way
	$url  = @( $_SERVER["HTTPS"] != 'on' ) ? 'http://'.$_SERVER["SERVER_NAME"] :  'https://'.$_SERVER["SERVER_NAME"];
	$url .= $_SERVER["REQUEST_URI"];
	return $url;
}

function sm_to_bool( $value ) {
	if ( empty( $value ) ) {
		return false;
	}

	//see this for more info: http://stackoverflow.com/questions/7336861/how-to-convert-string-to-boolean-php
	return filter_var( $value, FILTER_VALIDATE_BOOLEAN );
}

/**
 * Return a boolean value for the current state of a checkbox (it usually has yes or no value)
 *
 * @param $post_id    int
 * @param $meta_key   string
 *
 * @return boolean
 */
function sm_meta_to_bool( $post_id, $meta_key ) {

	$result = get_post_meta( $post_id, $meta_key, true );

	return sm_to_bool( $result );
}

/**
 * Check if the $haystack contains any of the needles.
 *
 * @param string $haystack
 * @param array $needles
 *
 * @return bool
 */
function sm_string_contains_any( $haystack, $needles ) {
	foreach ( $needles as $needle ) {
		if ( false !== strpos( $haystack, $needle ) ) {
			return true;
		}
	}

	return false;
}
