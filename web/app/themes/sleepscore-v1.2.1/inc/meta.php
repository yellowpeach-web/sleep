	<div class="meta">
		<?php /*<span>By: <span class="author"><?php the_author(); ?></span>
		&nbsp;|&nbsp;*/?>

		<?php // display last update if post has been updated.
			$u_time = get_the_time('U');
			$u_modified_time = get_the_modified_time('U');
			// if ($u_modified_time >= $u_time + 86400) {
			// echo '<span>';
			// the_modified_time('F jS, Y');
			// echo "</span> "; } else {
			echo '<span>';
			the_time('F jS, Y');
			echo "</span> ";
			// }
		?>
	</div>
