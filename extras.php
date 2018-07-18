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
	return StyleManager_Metaboxes::getInstance()->prefix( $option, $separator );
}

/**
 * Helper function to get/return the StyleManager_Settings object
 *
 * @return StyleManager_Settings
 */
function sm_settings() {
	return StyleManager_Settings::getInstance();
}

/**
 * Wrapper function around cmb2_get_option.
 *
 * @since 1.0.0
 *
 * @param  string  $setting Option key without any prefixing.
 * @param mixed $default Optional. The default value to retrieve in case the option was not found.
 * @return mixed        Option value
 */
function sm_get_setting( $setting, $default = false ) {
	$instance = sm_settings();
	return cmb2_get_option( $instance->key, $instance->prefix( $setting ), $default );
}

/**
 * Wrapper function around cmb2_update_option.
 *
 * @since  1.0.0
 *
 * @param  string  $setting Option key without any prefixing.
 * @param  mixed   $value      Value to update data with.
 * @param  boolean $single     Whether data should not be an array.
 * @return bool           Success/Failure
 */
function sm_update_setting( $setting, $value, $single = true ) {
	$instance = sm_settings();
	return cmb2_update_option( $instance->key, $instance->prefix( $setting ), $value, $single );
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

/**
 * Wrapper for _doing_it_wrong.
 *
 * Taken from WooCommerce - see wc_doing_it_wrong().
 *
 * @since  1.0.0
 * @param string $function Function used.
 * @param string $message Message to log.
 * @param string $version Version the message was added in.
 */
function sm_doing_it_wrong( $function, $message, $version ) {
	// @codingStandardsIgnoreStart
	$message .= ' Backtrace: ' . wp_debug_backtrace_summary();

	if ( is_ajax() ) {
		do_action( 'doing_it_wrong_run', $function, $message, $version );
		error_log( "{$function} was called incorrectly. {$message}. This message was added in version {$version}." );
	} else {
		_doing_it_wrong( $function, $message, $version );
	}
	// @codingStandardsIgnoreEnd
}

/**
 * Autoloads the files in a theme's directory.
 *
 * We do not support child themes at this time.
 *
 * @param string $path The path of the theme directory to autoload files from.
 * @param int    $depth The depth to which we should go in the directory. A depth of 0 means only the files directly in that
 *                     directory. Depth of 1 means also the first level subdirectories, and so on.
 *                     A depth of -1 means load everything.
 * @param string $method The method to use to load files. Supports require, require_once, include, include_once.
 *
 * @return false|int False on failure, otherwise the number of files loaded.
 */
function sm_autoload_dir( $path, $depth = 0, $method = 'require_once' ) {
	// If the $path starts with the absolute path to the WP install or the plugin directory, not good
	if ( strpos( $path, ABSPATH ) === 0 && strpos( $path, plugin_dir_path( __FILE__ ) ) !== 0 ) {
		sm_doing_it_wrong( __FUNCTION__, esc_html__( 'Please provide only paths in the Style Manager for autoloading.', 'style_manager' ), null );
		return false;
	}

	if ( ! in_array( $method, array( 'require', 'require_once', 'include', 'include_once' ) ) ) {
		sm_doing_it_wrong( __FUNCTION__, esc_html__( 'We support only require, require_once, include, and include_once.', 'style_manager' ), null );
		return false;
	}

	// If we have a relative path, make it absolute.
	if ( strpos( $path, plugin_dir_path( __FILE__ ) ) !== 0 ) {
		// Delete any / at the beginning.
		$path = ltrim( $path, '/' );

		// Add the current plugin path
		$path = trailingslashit( plugin_dir_path( __FILE__ ) ) . $path;
	}

	// Start the counter
	$counter = 0;

	$iterator = new DirectoryIterator( $path );
	// First we will load the files in the directory
	foreach ( $iterator as $file_info ) {
		if ( ! $file_info->isDir() && ! $file_info->isDot() && 'php' == strtolower( $file_info->getExtension() ) ) {
			switch ( $method ) {
				case 'require':
					require $file_info->getPathname();
					break;
				case 'require_once':
					require_once $file_info->getPathname();
					break;
				case 'include':
					include $file_info->getPathname();
					break;
				case 'include_once':
					include_once $file_info->getPathname();
					break;
				default:
					break;
			}

			$counter ++;
		}
	}

	// Now we load files in subdirectories if that's the case
	if ( $depth > 0 || -1 === $depth ) {
		if ( $depth > 0 ) {
			$depth --;
		}
		$iterator->rewind();
		foreach ( $iterator as $file_info ) {
			if ( $file_info->isDir() && ! $file_info->isDot() ) {
				$counter += sm_autoload_dir( $file_info->getPathname(), $depth, $method );
			}
		}
	}

	return $counter;
}

/**
 * Does the same thing the JS encodeURIComponent() does
 *
 * @param string $str
 *
 * @return string
 */
function sm_encodeURIComponent( $str ) {
	//if we get an array we just let it be
	if ( is_string( $str ) ) {
		$revert = array( '%21' => '!', '%2A' => '*', '%27' => "'", '%28' => '(', '%29' => ')' );

		$str = strtr( rawurlencode( $str ), $revert );
	} else {
		var_dump( 'boooom' );
		die;
	}

	return $str;
}

/**
 * Does the same thing the JS decodeURIComponent() does
 *
 * @param string $str
 *
 * @return string
 */
function sm_decodeURIComponent( $str ) {
	// If we get an array we just let it be
	if ( is_string( $str ) ) {
		$revert = array( '!' => '%21', '*' => '%2A', "'" => '%27', '(' => '%28', ')' => '%29' );
		$str    = rawurldecode( strtr( $str, $revert ) );
	}

	return $str;
}

/**
 * Checks whether an array is associative or not
 *
 * @param array $array
 *
 * @return bool
 */
function sm_is_assoc( $array ) {

	if ( ! is_array( $array ) ) {
		return false;
	}

	// Keys of the array
	$keys = array_keys( $array );

	// If the array keys of the keys match the keys, then the array must
	// not be associative (e.g. the keys array looked like {0:0, 1:1...}).
	return array_keys( $keys ) !== $keys;
}