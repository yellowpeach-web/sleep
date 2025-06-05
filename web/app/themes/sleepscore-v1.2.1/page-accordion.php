<?php
    /*
        Template Name: Accordion
    */
?>
<?php get_header(); ?>
<main class="container">
    <h1><?php the_title(); ?></h1> 
    <div id="accordion" class="accordion">
        <?php if(have_rows('faq_accordion_repeater')): ?>
            <?php $i = 1; ?>
            <?php while(have_rows('faq_accordion_repeater')): the_row(); ?>
                <div class="item">
                    <div class="accordion-header degular-typeface-semibold" data-toggle="collapse" data-target="#collapse-<?php echo $i; ?>">
                        <span>
                            <?php the_sub_field('question'); ?>
                        </span>
                        <span class="fa fa-plus"></span>
                        <span class="fa fa-minus"></span>
                    </div>
                    <div id="collapse-<?php echo $i; ?>" class="collapse accordion-content">
                        <?php the_sub_field('answer'); ?>
                    </div>
                </div>
                <?php $i++; ?>
            <?php endwhile; ?>
        <?php endif; ?>
    </div>
</main>
<style>
    .accordion .accordion-header {
        display: flex;
        width: 100%;
        align-items: center;
        justify-content: space-between;
    }
    .accordion .item {
        border-top: 1px solid #fff;
        padding: 0 10px;
    }
    .accordion .accordion-header {
        font-weight: 700;
        font-size: 1.25rem;
        padding: 10px;
    }
    .accordion .accordion-header:hover {
        cursor: pointer;
    }
    .accordion .accordion-content {
        padding: 0 10px;
    }
    .accordion .fa-minus {
        display: none;
    }
    .accordion ul {
        margin-bottom: 0;
        margin-left: 5px;
    }
</style>
<script>
    $(document).ready(function() {
        $('.collapse').on('show.bs.collapse', function(e) {
            $(this).parent().find('.fa-minus').show();
            $(this).parent().find('.fa-plus').hide();
        });
        $('.collapse').on('hide.bs.collapse', function(e) {
            $(this).parent().find('.fa-minus').hide();
            $(this).parent().find('.fa-plus').show();
        });

    });
</script>
<?php get_template_part('parts/pre-footer-banner'); ?>
<?php get_footer(); ?>
