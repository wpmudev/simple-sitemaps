<?php
/*
Plugin Name: Simple Sitemaps For Multisite
Description: The ultimate search engine plugin - Simply have sitemaps created, submitted and updated for every blog on your site
Plugin URI: http://premium.wpmudev.org/project/sitemaps-and-seo-wordpress-mu-style
Version: 1.1
Author: WPMU DEV
Author URI: http://premium.wpmudev.org/
Network: true
WDP ID: 39
*/

/*
Original plugin Author: Viper007Bond

Copyright 2007-2011 Incsub (http://incsub.com)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License (Version 2 - GPLv2) as published by
the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

//force multisite
if ( !is_multisite() ){
	add_action( 'admin_notices', 'wpmudev_simple_sitemaps_ms_notice', 5 );
  	add_action( 'network_admin_notices', 'wpmudev_simple_sitemaps_ms_notice', 5 );
}

if ( !file_exists( WP_CONTENT_DIR . '/sitemap.php' ) ){
	add_action( 'admin_notices', 'wpmudev_simple_sitemaps_file_notice', 5 );
  	add_action( 'network_admin_notices', 'wpmudev_simple_sitemaps_file_notice', 5 );
}

if (!defined('SIMPLE_SITEMAPS_USE_CACHE')) define('SIMPLE_SITEMAPS_USE_CACHE', true);


class Incsub_SimpleSitemaps {
	var $totalposts = 50; // Number of posts to display

	// Plugin initialization
	function Incsub_SimpleSitemaps() {
		// Delete cached sitemaps on new post or post delete
		add_action( 'publish_post', array(&$this, 'DeleteSitemap'), 15 );
		add_action( 'delete_post', array(&$this, 'DeleteSitemap'), 15 );

		// Ping search engines on new post or post delete
		add_action( 'publish_post', array(&$this, 'PingSearchEngines'), 16 );
    	add_action( 'delete_post', array(&$this, 'PingSearchEngines'), 16 );

		add_action( 'admin_init', array($this, 'init_dashboard') );

		$totalposts = defined('SIMPLE_SITEMAPS_POST_SOFT_LIMIT')
			? SIMPLE_SITEMAPS_POST_SOFT_LIMIT
			: $this->totalposts
		;
    	$this->totalposts = apply_filters('simple_sitemaps-totals_soft_limit', $totalposts);
	}

	public function init_dashboard () {
		$dash = dirname(__FILE__) . '/dash-notice/wpmudev-dash-notification.php';
		if (file_exists($dash)) {
			global $wpmudev_notices;
			if (!is_array($wpmudev_notices)) $wpmudev_notices = array();
			$wpmudev_notices[] = array(
				'id' => 39,
				'name' => 'Simple Sitemaps For Multisite',
				'screens' => array(),
			);
			require_once($dash);
		}
	}

	// Delete this blog's sitemap
	function DeleteSitemap() {
		global $wpdb;
		$filepath = apply_filters('simple_sitemaps-sitemap_location', ABSPATH . 'wp-content/blogs.dir/' . $wpdb->blogid . '/files/sitemap.xml');
		@unlink($filepath);
	}

	function PingSearchEngines() {
		$this->PingGoogle();
		$this->PingBing();
	}

	function PingBing() {
		$bing = 'http://www.bing.com/webmaster/ping.aspx?siteMap=' . urlencode( get_option('siteurl') . '/sitemap.xml' );
		wp_remote_get( $bing );
	}

	// Notify Google of a sitemap change
	function PingGoogle() {
		$pingurl = 'http://www.google.com/webmasters/sitemaps/ping?sitemap=' . urlencode( get_option('siteurl') . '/sitemap.xml' );
		wp_remote_get( $pingurl );
	}


	// Generate the contents of the sitemap and cache it to a file
	function GenerateSitemap( $blogid ) {
		global $wpdb;

		$totalpages = (int)apply_filters('simple_sitemaps-pages_count_override', $this->totalposts);
		$totalposts = (int)apply_filters('simple_sitemaps-posts_count_override', $this->totalposts);

		switch_to_blog( $wpdb->blogid );
		$latestpages = $totalpages ? get_posts( 'numberposts=' . $totalpages . '&post_type=page' ) : array();
		$latestposts = $totalposts ? get_posts( 'numberposts=' . $totalposts . '&orderby=date&order=DESC' ) : array();

		$content  = '<?xml version="1.0" encoding="UTF-8"?' . ">\n";
		$content .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

		$priority = 1;
		$prioritydiff = 1 / $this->totalposts;
		$latestpages = array_reverse($latestpages);
		foreach ( $latestpages as $post ) {
			if (!apply_filters('simple_sitemaps-include_post', true, $post)) continue;
			$content .= "	<url>\n";
			$content .= '		<loc>' . get_permalink( $post->ID ) . "</loc>\n";
			$content .= '		<lastmod>' . mysql2date( 'Y-m-d\TH:i:s', $post->post_modified_gmt ) . "+00:00</lastmod>\n";
			$content .= '		<priority>' . number_format( 1, 1 ) . "</priority>\n";
			$content .= "	</url>\n";
		}
		unset($post);
		foreach ( $latestposts as $post ) {
			if (!apply_filters('simple_sitemaps-include_post', true, $post)) continue;
			$content .= "	<url>\n";
			$content .= '		<loc>' . get_permalink( $post->ID ) . "</loc>\n";
			$content .= '		<lastmod>' . mysql2date( 'Y-m-d\TH:i:s', $post->post_modified_gmt ) . "+00:00</lastmod>\n";
			$content .= '		<priority>' . number_format( $priority, 1 ) . "</priority>\n";
			$content .= "	</url>\n";

			$priority = $priority - $prioritydiff;
		}

		$content = apply_filters('simple_sitemaps-generated_urlset', $content, $this);

		$content .= '</urlset>';

		// Write to the sitemap file
		$result = $this->writefile( ABSPATH . 'wp-content/blogs.dir/' . $wpdb->blogid . '/files/sitemap.xml', $content );

		return ( FALSE === $result ) ? FALSE : $content;
	}


	// Write a file, create directories as needed
	// Written by Trent Tompkins: http://www.php.net/manual/en/function.file-put-contents.php#84180
	function writefile( $filename, $content ) {
		// We don't bother if we don't have to.
		if (!apply_filters('simple_sitemaps-use_cache', SIMPLE_SITEMAPS_USE_CACHE)) return true;

		$filename = apply_filters('simple_sitemaps-sitemap_location', $filename);

		$parts = explode( '/', $filename );
		$file = array_pop( $parts );
		$filename = '';
		foreach ( $parts as $part ) {
			$part = trim($part);
			if ( !@is_dir( $filename .= $part . '/' ) ) {
				@mkdir($filename);
			}
		}
		$filename = untrailingslashit($filename);
		file_put_contents( "{$filename}/{$file}", $content );
	}
}

// Start this plugin after everything else is loaded
add_action( 'plugins_loaded', 'Incsub_SimpleSitemaps' ); function Incsub_SimpleSitemaps() { global $Incsub_SimpleSitemaps; $Incsub_SimpleSitemaps = new Incsub_SimpleSitemaps(); }





// $Id: file_put_contents.php,v 1.27 2007/04/17 10:09:56 arpad Exp $

if (!defined('FILE_USE_INCLUDE_PATH')) {
    define('FILE_USE_INCLUDE_PATH', 1);
}

if (!defined('LOCK_EX')) {
    define('LOCK_EX', 2);
}

if (!defined('FILE_APPEND')) {
    define('FILE_APPEND', 8);
}

/**
 * Replace file_put_contents()
 *
 * @category    PHP
 * @package     PHP_Compat
 * @license     LGPL - http://www.gnu.org/licenses/lgpl.html
 * @copyright   2004-2007 Aidan Lister <aidan@php.net>, Arpad Ray <arpad@php.net>
 * @link        http://php.net/function.file_put_contents
 * @author      Aidan Lister <aidan@php.net>
 * @version     $Revision: 1.27 $
 * @internal    resource_context is not supported
 * @since       PHP 5
 * @require     PHP 4.0.0 (user_error)
 */
function php_compat_file_put_contents($filename, $content, $flags = null, $resource_context = null) {
    // If $content is an array, convert it to a string
    if (is_array($content)) {
        $content = implode('', $content);
    }

    // If we don't have a string, throw an error
    if (!is_scalar($content)) {
        user_error('file_put_contents() The 2nd parameter should be either a string or an array',
            E_USER_WARNING);
        return false;
    }

    // Get the length of data to write
    $length = strlen($content);

    // Check what mode we are using
    $mode = ($flags & FILE_APPEND) ?
                'a' :
                'wb';

    // Check if we're using the include path
    $use_inc_path = ($flags & FILE_USE_INCLUDE_PATH) ?
                true :
                false;

    // Open the file for writing
    if (($fh = @fopen($filename, $mode, $use_inc_path)) === false) {
        user_error('file_put_contents() failed to open stream: Permission denied',
            E_USER_WARNING);
        return false;
    }

    // Attempt to get an exclusive lock
    $use_lock = ($flags & LOCK_EX) ? true : false ;
    if ($use_lock === true) {
        if (!flock($fh, LOCK_EX)) {
            return false;
        }
    }

    // Write to the file
    $bytes = 0;
    if (($bytes = @fwrite($fh, $content)) === false) {
        $errormsg = sprintf('file_put_contents() Failed to write %d bytes to %s',
                        $length,
                        $filename);
        user_error($errormsg, E_USER_WARNING);
        return false;
    }

    // Close the handle
    @fclose($fh);

    // Check all the data was written
    if ($bytes != $length) {
        $errormsg = sprintf('file_put_contents() Only %d of %d bytes written, possibly out of free disk space.',
                        $bytes,
                        $length);
        user_error($errormsg, E_USER_WARNING);
        return false;
    }

    // Return length
    return $bytes;
}

// Define
if (!function_exists('file_put_contents')) {
  function file_put_contents($filename, $content, $flags = null, $resource_context = null) {
      return php_compat_file_put_contents($filename, $content, $flags, $resource_context);
  }
}



///////////////////////////////////////////////////////////////////////////
/* -------------------- File required Notice -------------------- */
if ( !function_exists( 'wpmudev_simple_sitemaps_file_notice' ) ) {
  function wpmudev_simple_sitemaps_file_notice() {
    if ( current_user_can( 'edit_users' ) )
      echo '<div class="error fade"><p>' . __('Simple Sitemaps file "sitemap.php" not found. Please move it to /wp-content/ before activating. <a href="https://premium.wpmudev.org/project/sitemaps-and-seo-wordpress-mu-style/#product-usage" target="_blank">More information &raquo;</a></p></div>');}
}
/* --------------------------------------------------------------------- */

///////////////////////////////////////////////////////////////////////////
/* -------------------- Multisite required Notice -------------------- */
if ( !function_exists( 'wpmudev_simple_sitemaps_ms_notice' ) ) {
  function wpmudev_simple_sitemaps_ms_notice() {
    if ( current_user_can( 'edit_users' ) )
      echo '<div class="error fade"><p>' . __('Simple Sitemaps is only compatible with Multisite installs. </p></div>');}
}
/* --------------------------------------------------------------------- */
?>
