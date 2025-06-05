<?php
/*
* Template Name: Snoror Quiz Template
*/
get_header(); ?>
<?php
	require_once('inc/layout-options.php');
	$layoutOptions = new LayoutOptions();
?>
<?php /* main Quiz Section Starts Here */ ?>
<?php 
    if(isset($_GET) && ((!empty($_GET['nose_option']) || $_GET['nose_option']==='0') && (!empty($_GET['mouth_option']) || $_GET['mouth_option']==='0') && (!empty($_GET['tongue_option']) ) || $_GET['tongue_option']==='0')):
        $class_show = ' show';
        $class_hide = ' hide';
        $width = '100%';
    else:
        $width = '25%';
        $class_show = '';
        $class_hide = '';
    endif;

    $quiz_information_content = get_field('quiz_information_content');
    $quiz_information_button_title = get_field('quiz_information_button_title');
?>
<section class="main_quiz_sec <?php echo $class_hide; ?> first_main_quiz">
    <div class="main">
    <?php echo !empty($quiz_information_content) ? '<div class="quiz_head_text">'.wpautop(do_shortcode($quiz_information_content)).'</div>' : ''; ?>    
        <?php if(have_rows('quiz_information_categories', get_the_ID())): ?>
        <ul class="category_list">
            <?php 
                while (have_rows('quiz_information_categories', get_the_ID())): the_row();
                $title = get_sub_field('title');
                $image = get_sub_field('image');
            ?>
            <li>
                <?php if(!empty($image) && !empty($image['sizes'])): ?>
                    <figure><img src="<?php echo $image['sizes']['medium']; ?>" alt="<?php echo seoalttag($image['title']); ?>" /></figure>
                <?php endif; ?>
                <?php echo !empty($title) ? '<h3>'.$title.'</h3>' : ''; ?>
            </li>
            <?php endwhile; ?>
        </ul>
        <?php endif; ?>
        <?php if(!empty($quiz_information_button_title)): ?>
        <div class="quiz_btns">
            <a id="quickstart" href="javascript:void(0);" title="<?php echo seotitletag($quiz_information_button_title); ?>" class="button big_btn"><?php echo $quiz_information_button_title; ?></a>
        </div>
        <?php endif; ?>
    </div>
    <div class="loader text-center"> <img src="<?php echo get_template_directory_uri().'/images/list-load.svg'; ?>" alt="loading..."> </div>
</section>
<?php /* main Quiz Section Ends Here */ ?>
<?php 
$nose_snorer_content        = get_field('nose_snorer_content');
$nose_snorer_content_bottom = get_field('nose_snorer_content_bottom');
$nose_snorer_option_content = get_field('nose_snorer_option_content');

$mouth_snorer_content        = get_field('mouth_snorer_content');
$mouth_snorer_content_bottom = get_field('mouth_snorer_content_bottom');
$mouth_snorer_option_content = get_field('mouth_snorer_option_content');

$tongue_snorer_content        = get_field('tongue_snorer_content');
$tongue_snorer_content_bottom = get_field('tongue_snorer_content_bottom');
$tongue_snorer_option_content = get_field('tongue_snorer_option_content');

$quize_results_head_content = get_field('quize_results_head_content');
$quize_results_content = get_field('quize_results_content');

$store_banner_title = get_field('store_banner_title');
$linkdata = get_link_data(get_field('store_banner_button_link'));
$button_title = (isset($linkdata) && !empty($linkdata['title'])) ? $linkdata['title'] : '';

$products_selections_title = get_field('products_selections_title');

$mailchimp_form_shortcode = get_field('mailchimp_form_shortcode');
$mailchimp_thank_you_content = get_field('mailchimp_thank_you_content');
?>
<div class="quize_wrap">
    <div class="progressbar <?php echo $class_show; ?>">
        <span style="width:<?php echo $width; ?>" class="<?php echo $class_show; ?>">Progress Bar</span>
    </div>
    <form id="questions" method="get" autocomplete="off" novalidate="novalidate">
        <section class="main_quiz_sec quiz_question q1">
            <div class="main">
                <a href="javascript:void(0);" class="back_arrow_q1 back_arrow">&lt; Back</a>
                <div class="quiz_head_text">
                    <?php echo wpautop(do_shortcode($nose_snorer_content)); ?>
                </div>

                <?php if(have_rows('nose_snorer_quiz_questions', get_the_ID())): ?>
                <ul class="quation_test_list">
                    <?php
                        $i=1;
                        while (have_rows('nose_snorer_quiz_questions', get_the_ID())): the_row();
                        $title = get_sub_field('title');
                        $image = get_sub_field('image');
                    ?>
                    <li>
                        <?php if(!empty($image) && !empty($image['sizes'])): ?>
                            <figure><img src="<?php echo $image['url']; ?>" alt="<?php echo seoalttag($image['title']); ?>" /></figure>
                        <?php endif; ?>
                        <?php echo !empty($title) ? '<p>'.$i.'. '.$title.'</p>' : ''; ?>
                    </li>
                    <?php 
                    $i++;
                    endwhile; ?>
                </ul>
                <?php endif; ?>
                <?php if(!empty($nose_snorer_content_bottom)): ?>
                <div class="quiz_qustion_content">
                    <?php echo !empty($nose_snorer_content_bottom) ? wpautop(do_shortcode($nose_snorer_content_bottom)) : '' ; ?>
                </div>
                <?php endif; ?>
                <div class="quiz_btns">
                    <?php echo !empty($nose_snorer_option_content) ? '<h3>'.$nose_snorer_option_content.'</h3>' : '' ; ?>
                    <input id="nose_option_yes" class="nose_option" type="radio" name="nose_option" value="1" required>
                    <label class="button" for="nose_option_yes">Yes</label>
                    <input id="nose_option_no" class="nose_option" type="radio" name="nose_option" value="0" required>
                    <label class="button" for="nose_option_no">No</label>
                </div>
            </div>
        </section>

        <section class="main_quiz_sec quiz_question q2">
            
            <div class="main">
                <a href="javascript:void(0);" class="back_arrow_q2 back_arrow">&lt; Back</a>
                <div class="quiz_head_text">
                    <?php echo wpautop(do_shortcode($mouth_snorer_content)); ?>
                </div>

                <?php if(have_rows('mouth_snorer_quiz_questions', get_the_ID())): ?>
                <ul class="quation_test_list">
                    <?php
                        $i=1;
                        while (have_rows('mouth_snorer_quiz_questions', get_the_ID())): the_row();
                        $title = get_sub_field('title');
                        $image = get_sub_field('image');
                    ?>
                    <li>
                        <?php if(!empty($image) && !empty($image['sizes'])): ?>
                            <figure><img src="<?php echo $image['url']; ?>" alt="<?php echo seoalttag($image['title']); ?>" /></figure>
                        <?php endif; ?>
                        <?php echo !empty($title) ? '<p>'.$i.'. '.$title.'</p>' : ''; ?>
                    </li>
                    <?php
                    $i++;
                    endwhile; ?>
                </ul>
                <?php endif; ?>
                <?php if(!empty($mouth_snorer_content_bottom)): ?>
                <div class="quiz_qustion_content">
                    <?php echo !empty($mouth_snorer_content_bottom) ? wpautop(do_shortcode($mouth_snorer_content_bottom)) : '' ; ?>
                </div>
                <?php endif; ?>
                <div class="quiz_btns">
                    <?php echo !empty($mouth_snorer_option_content) ? '<h3>'.$mouth_snorer_option_content.'</h3>' : '' ; ?>
                    <input id="mouth_option_yes" class="mouth_option" type="radio" name="mouth_option" value="1" required>
                    <label class="button" for="mouth_option_yes">Yes</label>
                    <input id="mouth_option_no" class="mouth_option" type="radio" name="mouth_option" value="0" required>
                    <label class="button" for="mouth_option_no">No</label>
                </div>
            </div>
        </section>

        <section class="main_quiz_sec quiz_question q3">
            
            <div class="main">
                <a href="javascript:void(0);" class="back_arrow_q3 back_arrow">&lt; Back</a>
                <div class="quiz_head_text">
                    <?php echo wpautop(do_shortcode($tongue_snorer_content)); ?>
                </div>

                <?php if(have_rows('tongue_snorer_quiz_questions', get_the_ID())): ?>
                <ul class="quation_test_list">
                    <?php
                        $i=1;
                        while (have_rows('tongue_snorer_quiz_questions', get_the_ID())): the_row();
                        $title = get_sub_field('title');
                        $image = get_sub_field('image');
                    ?>
                    <li>
                        <?php if(!empty($image) && !empty($image['sizes'])): ?>
                            <figure><img src="<?php echo $image['url']; ?>" alt="<?php echo seoalttag($image['title']); ?>" /></figure>
                        <?php endif; ?>
                        <?php echo !empty($title) ? '<p>'.$i.'. '.$title.'</p>' : ''; ?>
                    </li>
                    <?php 
                    $i++;
                    endwhile; ?>
                </ul>
                <?php endif; ?>
                <?php if(!empty($tongue_snorer_content_bottom)): ?>
                <div class="quiz_qustion_content">
                    <?php echo !empty($tongue_snorer_content_bottom) ? wpautop(do_shortcode($tongue_snorer_content_bottom)) : '' ; ?>
                </div>
                <?php endif; ?>
                <div class="quiz_btns">
                    <?php echo !empty($tongue_snorer_option_content) ? '<h3>'.$tongue_snorer_option_content.'</h3>' : '' ; ?>
                    <input id="tongue_option_yes" class="tongue_option" type="radio" name="tongue_option" value="1" required>
                    <label class="button" for="tongue_option_yes">Yes</label>
                    <input id="tongue_option_no" class="tongue_option" type="radio" name="tongue_option" value="0" required>
                    <label class="button" for="tongue_option_no">No</label>
                </div>
            </div>
        </section>
		
		<?php if ($_GET['layoutOptions']): ?>
            <input type="hidden" name="layoutOptions" value="<?php echo $_GET['layoutOptions'] ?>" />
        <?php endif ?>
		
    </form>
    
    <div class="result_wrap <?php echo $class_show; ?>">    
        <section class="main_quiz_sec qustion_result_sec quiz_result">
            <div class="main">
                    <?php 
                        //echo wp_get_referer();
                        $referer = '';
                        $result_data = $result_products = $result_content = $result_images = $results_content_bottom = array();
                        $submit = (isset($_GET['submit'])) ?  $_GET['submit'] : ''; 
                        if(wp_get_referer() || $submit==1):
                            $referer = '1';
                        endif;
                        
                        if(have_rows('products_selections', get_the_ID())):
                            
                            while (have_rows('products_selections', get_the_ID())): the_row();
                                $nose_snoring = $mouth_snoring = $tongue_snoring = '0';
                                

                                $nose_snoring = (get_sub_field('nose_snoring') == 1 ) ? get_sub_field('nose_snoring') : 0;
                                $mouth_snoring = (get_sub_field('mouth_snoring') == 1 ) ? get_sub_field('mouth_snoring') : 0;
                                $tongue_snoring = (get_sub_field('tongue_snoring') == 1 ) ? get_sub_field('tongue_snoring') : 0;
                                
                                
                                
                                //$select_products = get_sub_field('select_products');
                                $select_products = get_sub_field('select_products_shopify');
                                
                                if(!empty($referer)):
                                    $content = get_sub_field('content');
                                else:
                                    $content = get_sub_field('no_quize_content');
                                endif;
                                
                                $images = get_sub_field('images');
                                $results_content = get_sub_field('results_content');

                                $result_data[] = "$nose_snoring$mouth_snoring$tongue_snoring";
                                $result_products["$nose_snoring$mouth_snoring$tongue_snoring"] = $select_products;
                                $result_content["$nose_snoring$mouth_snoring$tongue_snoring"] = $content;
                                $result_images["$nose_snoring$mouth_snoring$tongue_snoring"] = $images;
                                $results_content_bottom["$nose_snoring$mouth_snoring$tongue_snoring"] = $results_content;
                            endwhile;
                        endif;
                        
                        $nose_option   = (isset($_GET['nose_option']))   ?  $_GET['nose_option'] : ''; 
                        $mouth_option  = (isset($_GET['mouth_option']))  ?  $_GET['mouth_option'] : '';
                        $tongue_option = (isset($_GET['tongue_option'])) ?  $_GET['tongue_option'] : ''; 
                        
                        
                        
                        $result_opt = $nose_option.$mouth_option.$tongue_option;
                        if(array_key_exists ($result_opt,$result_products)):
                    ?>
                            <?php if(isset($result_content[$result_opt]) && !empty($result_content[$result_opt])):?>
                                <div class="quiz_head_text">
                                    <?php echo wpautop(do_shortcode($result_content[$result_opt])); ?>
                                </div>
                            <?php endif; ?>
                
                            <?php if(isset($result_images[$result_opt]) && !empty($result_images[$result_opt])):?>
                                <ul class="category_list">
                                    <?php foreach ($result_images[$result_opt] as $key => $image):  ?>
                                        <?php if (!empty($image) && !empty($image['sizes'])): ?>
                                            <li>
                                                <figure><img src="<?php echo $image['sizes']['medium']; ?>" alt="<?php echo seoalttag($image['title']); ?>" /></figure>
                                            </li>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                
                
                            <?php if(isset($results_content_bottom[$result_opt]) && !empty($results_content_bottom[$result_opt])):?>
                                <div class="qustion_result_content">
                                    <?php echo wpautop(do_shortcode($results_content_bottom[$result_opt])); ?>
                                </div>
                            <?php endif; ?>
                
                    <?php endif; ?>
                
                    <div class="recommended_product_wrap">
                        <?php
                        if(array_key_exists ($result_opt,$result_products)):
                            if(isset($result_products[$result_opt]) && !empty($result_products[$result_opt])):
                                $products = $result_products[$result_opt];
                                echo '<ul class="recommended_product_list">';
                                foreach ($products as $product):
                                    echo get_shopify_product_html($product);
                                endforeach;
                                echo '</ul>';
                            endif;
                        endif;
                    ?>
                </div>
            </div>
        </section>
        
        <?php 
        if(wp_get_referer() || $submit=='1'):
            if (!empty($mailchimp_form_shortcode)):
                ?>
                <section class="mailchimp-from">
                    <div class="main">
                        <div class="form_wrap">
                            <?php if($submit==1): ?>
                                <form>
                                    <div class="mc4wp-form-fields">
                                        <a href="javascript:void(0);" title="Close" class="close-popup close-popup-link">X</a>
                                        <?php echo wpautop($mailchimp_thank_you_content); ?>
                                    </div>
                                </form>
                            <?php
                            else:
                                echo do_shortcode($mailchimp_form_shortcode);
                            endif;
                            ?>
                        </div>
                    </div>
                </section>
    <?php endif;
           endif; ?>
        
        
        <?php if( ((isset($button_title) && !empty($button_title)) || !empty($store_banner_title)) && $layoutOptions->isVisible('storeBanner') ): ?>
        <section class="store_banner_sec">
            <div class="main">
                <?php echo !empty($store_banner_title) ? '<h2>'.$store_banner_title.'</h2>' : ''; ?>
                <?php if (isset($button_title) && !empty($button_title)): ?>
                    <a href="<?php echo $linkdata['url']; ?>" target="<?php echo $linkdata['target']; ?>" title="<?php echo seotitletag($button_title); ?>" class="button"><?php echo $button_title; ?></a>
                <?php endif; ?>
            </div>
        </section>
        <?php endif; ?>    
    </div>
    <div class="loader text-center"> <img src="<?php echo get_template_directory_uri().'/images/list-load.svg'; ?>" alt="loading..."> </div>
</div>    
<?php if($submit=='1'): ?>
<script type="text/javascript">
    jQuery(document).ready(function($) {
        $('.mailchimp-from').fadeIn();
        $('.close-popup').click(function () {
            $('.mailchimp-from').fadeOut();
        });
        if(ajax_custom_data.submit=="1"){
            setTimeout(function () {
                $('.mailchimp-from .close-popup').trigger('click');
            },5000);
        }
    });
</script>
<?php endif; ?>
<?php get_footer(); ?>