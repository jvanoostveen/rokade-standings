<?php
add_action('wp_enqueue_scripts', 'my_theme_enqueue_styles', 11);

function my_theme_enqueue_styles() {
    wp_enqueue_style('child-style', get_stylesheet_uri());
}
