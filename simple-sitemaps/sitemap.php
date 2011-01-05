<?php
/*
Simple Sitemaps For Multisite
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