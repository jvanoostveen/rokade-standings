<!-- content-full.php -->
<div class='full-image'>

<?php the_post_thumbnail( 'full' ); ?>
	<div class='fade'></div>
	<div class='top-contents'>
		<div class='container'>
			<div class='col-md-8 col-md-offset-2'>
				<?php
					the_title( '<h1>', '</h1>' );
				?>

				<span class='date'>
					Geplaatst op <?php the_time('d-m-Y'); ?> door <?php the_author(); ?>
				</span>
			</div>
		</div>
	</div>
</div>

<div class='container'>


<article class='detail'>


	<div class="generic col-md-8 col-md-offset-2" style='padding:0 0 30px 0;'>
		<?php
			/* translators: %s: Name of current post */
			the_content( sprintf(
				__( 'Lees verder %s', '' ),
				the_title( '<span class="screen-reader-text">', '</span>', false )
			) );

			wp_link_pages( array(
				'before'      => '<div class="page-links"><span class="page-links-title">' . __( 'Pages:', '' ) . '</span>',
				'after'       => '</div>',
				'link_before' => '<span>',
				'link_after'  => '</span>',
				'pagelink'    => '<span class="screen-reader-text">' . __( 'Page', '' ) . ' </span>%',
				'separator'   => '<span class="screen-reader-text">, </span>',
			) );

		if ( comments_open() || get_comments_number() ) :
				comments_template();
			endif;
		?>
	</div><!-- .entry-content -->

	<div class='clear'></div>

</article><!-- #post-## -->

<style>
.widget {
	display:none;
}
</style>

</div>
