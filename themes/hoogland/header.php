<!DOCTYPE html>
<html <?php language_attributes(); ?> class="no-js">
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<title>Schaken in Hoogland</title>
	<meta name="viewport" content="width=device-width">
	<link rel="profile" href="http://gmpg.org/xfn/11">
	<link rel="pingback" href="<?php bloginfo( 'pingback_url' ); ?>">
	<!--[if lt IE 9]>
	<script src="<?php echo esc_url( get_template_directory_uri() ); ?>/js/html5.js"></script>
	<![endif]-->

	<!-- CSS -->
	<link rel="stylesheet" href="<?php echo esc_url( get_template_directory_uri() ); ?>/style.css" />
	<script src="<?php echo esc_url( get_template_directory_uri() ); ?>/js/jquery-3.1.1.min.js"></script>
	<script src="<?php echo esc_url( get_template_directory_uri() ); ?>/scripts.php"></script>

	<?php wp_head(); ?>
</head>

<body>

	<header>
		<div class='container'>
			<div class='col-lg-3 col-md-4 logo'>
				<a href='https://www.schakeninhoogland.nl/'><img src='<?php echo esc_url( get_template_directory_uri() ); ?>/img/logo.png' alt='Schaakvereniging Hoogland' class='logo-img'>

				<h1><span>Schaakvereniging</span> Hoogland</h1> </a>
			</div>

			<div class='col-lg-9 col-md-8'>
				<?php wp_nav_menu( array( 'theme_location' => 'header_navigation' ) ); //header_navigation ?>
				

			</div>
			
			<!--<div class='col-lg-4 hidden-md hidden-sm hidden-xs search-header'>
				<?php get_search_form(); ?>
			</div>-->
		</div>
	</header>

	<?php
		if ( is_front_page() && is_home() ) : 
	?>



		<div class='container home-blocks'>



		<?php  if ( have_posts() ) : ?>

			<?php

	
			$i = 0;
			// Start the loop.
			while ( have_posts() ) : the_post();

				$i++;
				
				if($i < 4) {
					get_template_part( 'content', "grid-block" );
				}

			//	if($i==1) {
			//		get_template_part( 'content', "grid-large" );
			//	} elseif($i < 4) {
			//		get_template_part( 'content', "grid-small" );
			//	}

			// End the loop.
			endwhile;

			// Previous/next page navigation.

		// If no content, include the "No posts found" template.
		else :
			get_template_part( 'content', 'none' );

		endif;
		?>

		</div>

	<?php 
		else : 
	?>
		<!-- NIET HOME / BACKLINK? -->
	<?php 
		endif; 
	?>

	<div class='container content-container'>	


