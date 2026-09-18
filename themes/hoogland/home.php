<?php
get_header(); ?>

<div class='col-md-8'>
<div class='contents home'>
		<?php if ( have_posts() ) : ?>

			<?php
			// Start the loop.
			while ( have_posts() ) : the_post();

				/*
				 * Include the Post-Format-specific template for the content.
				 * If you want to override this in a child theme, then include a file
				 * called content-___.php (where ___ is the Post Format name) and that will be used instead.
				 */
				get_template_part( 'content', "home" );

			// End the loop.
			endwhile;

		echo "<div class='clear'></div>";

			// Previous/next page navigation.
			the_posts_pagination( array(
				'prev_text'          => __( 'Vorige pagina', 'twentyfifteen' ),
				'next_text'          => __( 'Volgende pagina', 'twentyfifteen' ),
				'before_page_number' => '<span class="meta-nav screen-reader-text">' . __( 'Page', 'twentyfifteen' ) . ' </span>',
			) );

		// If no content, include the "No posts found" template.
		else :
			get_template_part( 'content', 'none' );

		endif;
		?>
</div>
</div>

<?php get_footer(); ?>
