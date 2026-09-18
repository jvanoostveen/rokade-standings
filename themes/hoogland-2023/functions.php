<?php
add_action('wp_enqueue_scripts', 'my_theme_enqueue_styles', 11);

function my_theme_enqueue_styles() {
    wp_enqueue_style('child-style', get_stylesheet_uri());
}

function register_custom_sidebar() {
    register_sidebar(array(
        'name' => __('Custom Sidebar', ''),
        'id' => 'custom-sidebar',
        'description' => __('This is a custom sidebar.', 'text-domain'),
        'before_widget' => '<div id="%1$s" class="widget %2$s">',
        'after_widget' => '</div>',
        'before_title' => '<h2 class="widget-title">',
        'after_title' => '</h2>',
    ));
}
add_action('widgets_init', 'register_custom_sidebar');