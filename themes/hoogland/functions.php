<?php
if ( function_exists('register_sidebar') )
register_sidebar();

// This theme uses post thumbnails
add_theme_support( 'post-thumbnails' );
add_theme_support( 'menus' );

function register_my_menus() {
  register_nav_menus(
    array(  
    	'header_navigation' => __( 'Header Navigation' ), 
    	'expanded_footer' => __( 'Expanded Footer' )
    )
  );
} 
add_action( 'init', 'register_my_menus' );


add_filter('wp_theme_editor_filetypes', function ($types) {
	$types = array('scss','js');
	return $types;
});

?>