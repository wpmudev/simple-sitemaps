<?php
/*
Plugin Name:  Simple Sitemaps For WPMU
Description:  On-demand sitemaps for WPMU.
Version:      1.0.3
Author:       Viper007Bond (Incsub)
Author URI:   http://incsub.com/
*/

/* 
Copyright 2007-2009 Incsub (http://incsub.com)

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

require( '../wp-load.php' );

$cachefile = dirname(__FILE__) . '/blogs.dir/' . $wpdb->blogid . '/files/sitemap.xml';

if ( file_exists( $cachefile ) ) {
	header( 'Content-type: text/xml; charset=utf-8' );
	echo file_get_contents( $cachefile );

	echo "\n<!-- Sitemap was loaded from a cached file -->";
} else {

	if ( !class_exists('Incsub_SimpleSitemaps') )
		exit('Plugin missing.');

	$content = $Incsub_SimpleSitemaps->GenerateSitemap( $wpdb->blogid );

	header( 'Content-type: text/xml; charset=utf-8' );
	echo $content;

	echo "\n<!-- Sitemap was generated for this view -->";
}

?>