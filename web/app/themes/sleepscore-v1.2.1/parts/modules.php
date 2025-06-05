<?php
	if(have_rows('page_modules')):
		while(have_rows('page_modules')): the_row();
			if(get_row_layout() == "video_cta"):
				get_template_part('video_cta');
			elseif(get_row_layout() == 'bg_image_with_content_and_cta'):
				get_template_part('parts/bg-img-cta');
			elseif(get_row_layout() == 'bootstap_column_flexible_layout'):
				get_template_part('parts/bootstrap-column');
			elseif(get_row_layout() == 'content_bg_img_color'):
				get_template_part('parts/content-bg-img-color');
			elseif(get_row_layout() == 'executive_team'):
				echo '<div class="container">';
				get_template_part('parts/executive-team');
				echo '</div>';
			elseif(get_row_layout() == 'tab_content'):
				get_template_part('parts/tab-content');
			endif;
		endwhile;
	endif;
?>