<article class='col-md-6 col-sm-6 home'>

	<h1><a href='<?php echo get_permalink(); ?>'>	<?php
			the_title();
		?></a></h1>

		<span class='date'>
		Geplaatst op <?php the_time('d-m-Y'); ?> door <?php the_author(); ?>
		</span>

	<div class="entry-content">
		<?php echo get_the_post_thumbnail( $page->ID, 'thumbnail' ); ?>
		<?php
			/* translators: %s: Name of current post */
			$content = wp_trim_words(get_the_content(), 100, '...');			
			echo $content;

			wp_link_pages( array(
				'before'      => '<div class="page-links"><span class="page-links-title">' . __( 'Pages:', '' ) . '</span>',
				'after'       => '</div>',
				'link_before' => '<span>',
				'link_after'  => '</span>',
				'pagelink'    => '<span class="screen-reader-text">' . __( 'Page', '' ) . ' </span>%',
				'separator'   => '<span class="screen-reader-text">, </span>',
			) );
		?>
	</div><!-- .entry-content -->

</article><!-- #post-## -->
