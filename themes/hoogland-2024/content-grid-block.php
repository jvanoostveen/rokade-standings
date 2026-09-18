<div class='col-md-4 col-sm-4 hidden-xs small-block'>

	<?php the_post_thumbnail( 'full' ); ?>
	
	<div class='fade-over'></div>

	<h1>
		<a href='<?php echo get_permalink(); ?>'>	
			<?php the_title(); ?>
		</a>
	</h1>

	<span class='date'>
		<?php the_time('d-m-Y'); ?>
	</span>

</div><!-- #post-## -->