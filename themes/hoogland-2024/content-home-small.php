<div class='col-md-4 small-grid'>

	<?php the_post_thumbnail( 'full' ); ?>

	<h1>
		<a href='<?php echo get_permalink(); ?>'>	
			<?php the_title(); ?>
		</a>
	</h1>

	<span class='date'>
		<?php the_time('d-m-Y'); ?>
	</span>

</div><!-- #post-## -->