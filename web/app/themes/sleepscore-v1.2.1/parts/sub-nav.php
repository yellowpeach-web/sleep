<nav class="fixed-sub-nav">
    <div class="container-lg">
        <button type="button" class="ss-cat-nav-branding sub-nav-toggle" aria-label="Toggle sub-navigation">
            <span class="square-24 fa fa-chevron-down"></span>
        </button>
        <div class="search-form-wrapper">
            <?php get_search_form(); ?>
        </div>
        <div class="sub-nav-content" id="sub-nav-content">
            <div class="sub-nav-container">
            <ul class="navbar-nav mr-auto">
                <?php if (have_rows('landing_page_menu_repeater', 'option')): ?>
                    <?php while (have_rows('landing_page_menu_repeater', 'option')): the_row(); ?>
                    <li class="nav-item">
                        <a class="nav-link <?php the_sub_field('menu_classes', 'option'); ?>" href="<?php the_sub_field('menu_link', 'option'); ?>">
                            <?php the_sub_field('menu_text', 'option'); ?>
                        </a>
                    </li>
                    <?php endwhile; ?>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
