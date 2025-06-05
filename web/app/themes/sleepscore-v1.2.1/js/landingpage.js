// JavaScript Document
var $ = jQuery.noConflict();
jQuery(document).ready(function () {
    $('#producttabs').easyResponsiveTabs({
        type: 'default', //Types: default, vertical, accordion           
        width: 'auto', //auto or any width like 600px
        fit: true, // 100% fit in a container
        removeHashfrmUrl: true,
        closed: 'accordion', // Start closed if in accordion view
        activate: function (event) { // Callback function if tab is switched
        }
    });

    /*$('.recommended_product_slider').slick({
        slidesToShow: 1,
        slidesToScroll: 1,
        arrows: true,
        fade: false,
        infinite: false,
        speed: 1000,
        asNavFor: '.recommended_product_slider_thumb',
        arrows: false,
        responsive: [
            {
                breakpoint: 640,
                    settings: {
                    dots: true
                }
            }
        ]
    });
    $('.recommended_product_slider_thumb').slick({
        slidesToShow: 5,
        slidesToScroll: 1,
        asNavFor: '.recommended_product_slider',
        dots: false,
        centerMode: false,
        focusOnSelect: false,
        responsive: [
            {
                breakpoint: 767,
                    settings: {
                    slidesToShow: 3,
                }
            }
        ] 
    });*/
    
    
    
    var numSlick = 0;
    $('.recommended_product_slider').each(function () {
        numSlick++;
        $(this).addClass('slider-' + numSlick).slick({
           slidesToShow: 1,
        slidesToScroll: 1,
        arrows: true,
        fade: false,
        infinite: false,
        speed: 1000,
        asNavFor: '.recommended_product_slider_thumb.slider-' + numSlick,
        arrows: false,
        responsive: [
            {
                breakpoint: 640,
                    settings: {
                    dots: true
                }
            }
        ]
            
        });
    });

    numSlick = 0;
    $('.recommended_product_slider_thumb').each(function () {
        numSlick++;
        $(this).addClass('slider-' + numSlick).slick({
            slidesToShow: 5,
        slidesToScroll: 1,
        asNavFor: '.recommended_product_slider.slider-' + numSlick,
        dots: false,
        centerMode: false,
        focusOnSelect: false,
        responsive: [
            {
                breakpoint: 767,
                    settings: {
                    slidesToShow: 3,
                }
            }
        ] 
        });
    });
    
    
    

    $('.quation_test_list').slick({
        slidesToShow: 3,
        slidesToScroll: 1,
        arrows: true,
        fade: false,
        infinite: false,
        speed: 1000,
        arrows: false,
        responsive: [
            {
                breakpoint: 640,
                    settings: {
                    centerMode: true,
                    slidesToShow: 1
                }
            }
        ]        
    });
    
    hide_loader();
    $('.quiz_question, .result_wrap, .hide, .progressbar').hide();
    $('.page-template-snoror-quiz .first_main_quiz .loader').hide();
    $('.show').show();

    $('#quickstart').click(function () {
        hide_loader();
        $('.page-template-snoror-quiz .first_main_quiz .loader').show();
        setTimeout(function () {
            $('.main_quiz_sec, .quiz_question').hide();
            $('.page-template-snoror-quiz .first_main_quiz .loader').hide();
            $('form#questions , .q1, .progressbar').show();
            $(".quation_test_list").slick('setPosition');
        }, 2000);
    });

    $('input[type="radio"].nose_option').click(function () {
        var radioValue = $('input[type="radio"].nose_option:checked').val();
        if (radioValue) {
            show_loader();
            setTimeout(function () {
                $('.q1').hide();
                hide_loader();
                $('.q2').show();
                $('.progressbar span').css('width', '50%');
                $(".quation_test_list").slick('setPosition');
            }, 2000);
        }
    });

    $('input[type="radio"].mouth_option').click(function () {
        show_loader();
        setTimeout(function () {
            $('.q2').hide();
            hide_loader();
            $('.q3').show();
            $('.progressbar span').css('width', '70%');
            $(".quation_test_list").slick('setPosition');
        }, 2000);
    });

    $('input[type="radio"].tongue_option').click(function () {
        show_loader();
        setTimeout(function () {
            //$('.q3').hide();
            //hide_loader();
            $('form#questions').submit();
            $(".quation_test_list").slick('setPosition');
        }, 2000);
        //$('.result_wrap, .quiz_result').show();
    });

    $('.back_arrow_q1').click(function () {
        show_loader();
        setTimeout(function () {
            $('form#questions').hide();
            $('.progressbar').hide();
            $('.main_quiz_sec, .quiz_question').show();
            hide_loader();
            $(".quation_test_list").slick('setPosition');
        }, 2000);
    });

    $('.back_arrow_q2').click(function () {
        show_loader();
        setTimeout(function () {
            $('.q2').hide();
            $('.q1').show();
            hide_loader();
            $(".quation_test_list").slick('setPosition');
        }, 2000);
    });

    $('.back_arrow_q3').click(function () {
        show_loader();
        setTimeout(function () {
            $('.q3').hide();
            $('.q2').show();
            hide_loader();
            $(".quation_test_list").slick('setPosition');
        }, 2000);
    });


    function hide_loader() {
        $('.page-template-snoror-quiz .quize_wrap .loader').hide();
        $(window).trigger('resize');
    }
    function show_loader() {
        $('.page-template-snoror-quiz .quize_wrap .loader').show();
        $(window).trigger('resize'); 
    }
    $(window).on('load', function(){
        $('.q1').hide();
        $('.q2').hide();
        $('.q3').hide();
        
        if(ajax_custom_data.mailchimp_sub){
           $('.result_wrap').show();
           show_loader();
        }
        setTimeout(function () {
            
            if(ajax_custom_data.mailchimp_sub){
                //console.log(ajax_custom_data);
                if(ajax_custom_data.mailchimp_sub!=''){
                    $('.mailchimp-from #'+ajax_custom_data.mailchimp_sub).prop( "checked", true );
                }
                hide_loader();
                
                $('.mailchimp-from').fadeIn();
                
                if(ajax_custom_data.submit=="1"){
                    setTimeout(function () {
                        $('.mailchimp-from .close-popup').trigger('click');
                    },15000);
                }
                
                $('.close-popup').click(function () {
                    $('.mailchimp-from').fadeOut();
                });
            }
        }, 1000);
    });
    
    function mailchimp_show(){
        $('.mailchimp-from').fadeIn();
        if(ajax_custom_data.submit=="1"){
            setTimeout(function () {
                $('.mailchimp-from .close-popup').trigger('click');
            },15000);
        }
    }
});