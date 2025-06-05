<?php
	$searchValue = '';
	if (isset($_GET['s'])) {
		$searchValue = $_GET['s'];
	}
?>
<?php if ((is_singular('sleep_shop')) || is_post_type_archive('sleep_shop')) { ?>
	<form role="search" method="get" id="searchform" action="<?php bloginfo('url'); ?>" >
		<label for="s" class="screen-reader-text"></label>
		<div class="search-field">
			<input type="text" placeholder="Search SleepShop" name="s" id="s" value="<?php echo !empty($searchValue) ? $searchValue : '' ?>" />
			<button type="submit" id="searchsubmit" aria-label="Search submit button">
				<span class="fa fa-search"></span>
			</button>
		</div>
		<input type="hidden" name="post_type" value="sleep_shop" />
	</form>
<?php } else { ?>
	<form role="search" method="get" id="searchform" action="<?php bloginfo('url'); ?>" >
		<label for="s" class="screen-reader-text"></label>
		<div class="search-field">
			<input type="text" placeholder="Search" name="s" id="s" value="<?php echo !empty($searchValue) ? $searchValue : '' ?>" />
			<button type="submit" id="searchsubmit" aria-label="Search submit button">
				<span class="fa fa-search"></span>
			</button>
		</div>
		<input type="hidden" name="post_type" value="post" />
	</form>
<?php } ?>
