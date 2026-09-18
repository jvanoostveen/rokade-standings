<div class='col-md-8 big-grid'>

	<?php the_post_thumbnail( 'full' ); ?>
	
	<div class='fade-over'></div>

	<h1>
		<a href='<?php echo get_permalink(); ?>'>	
			<?php the_title(); ?>
		</a>
	</h1>

	<span class='date'>
		Geplaatst op <?php the_time('d-m-Y'); ?>
	</span>

</div><!-- #post-## -->
