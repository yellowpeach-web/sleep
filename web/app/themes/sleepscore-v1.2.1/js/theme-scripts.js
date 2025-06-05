// write cookie for refer
function setCookie(cname, cvalue, exdays) {
	"use strict";
	var d = new Date();
	d.setTime(d.getTime() + (exdays * 24 * 60 * 60 * 1000));
	var expires = "expires=" + d.toUTCString();
	document.cookie = cname + "=" + cvalue + ";" + expires + ";path=/";
}

function getCookie(cname) {
	"use strict";
	var name = cname + "=";
	var ca = document.cookie.split(';');
	for (var i = 0; i < ca.length; i++) {
		var c = ca[i];
		while (c.charAt(0) === ' ') {
			c = c.substring(1);
		}
		if (c.indexOf(name) === 0) {
			return c.substring(name.length, c.length);
		}
	}
	return "";
}

function checkCookie() {
	"use strict";

	var auRefID = getCookie("referID");

	if (phpAuRefID !== "") {
		document.getElementById("refer").value = phpAuRefID;
		setCookie("referID", phpAuRefID, 360);
	} else {
		if (window.location.pathname == '/refer') {
			document.getElementById("refer").value = auRefID;
		}
	}
}
$(document).ready(function () {
	"use strict";
	// ======================================= mobile nav button trigger animation
	$('.navbar-toggler').click(function () {
		$('.mid-bar').toggle();
		$('.top-bar').toggleClass('rotate45');
		$('.bottom-bar').toggleClass('rotate-45');
		$('body').toggleClass('mobile-nav');
		//$('#logo').fadeToggle('slow');
	});
	// ======================================= Close Navbar on outside click
	$('main').click(function () {
		if ($('#primary-nav').hasClass('show')) {
			$('.navbar-toggler').addClass('collapsed');
			$('#primary-nav').removeClass('show');
			$('.mid-bar').toggle();
			$('.top-bar').toggleClass('rotate45');
			$('.bottom-bar').toggleClass('rotate-45');
			$('body').toggleClass('mobile-nav').toggleClass('scroll-lock');
		    $('html').toggleClass('scroll-lock');
		}
	});
	// ======================================= SleepShop Category Nav
	$('#ss-nav-trigger').click(function (e) {
		e.preventDefault();
		$('#ss-cat-nav-wrap').slideToggle();
		$('.square-24.fa-chevron-down').toggleClass('flip');
		if ($('.subnav-full').is(':visible')) {
			$('.subnav-full').hide();
		} else {
			$('.subnav-full').show();
		}
	});
	// ======================================= Animate Scroll click
	$('a.scroll-link[href^="#"]').on('click', function (e) {
		e.preventDefault();

		var target = this.hash;
		var $newtarget = $(target);
		var offset = -110;
		$('html, body').stop().animate({
			'scrollTop': $newtarget.offset().top + offset
		}, 900, 'swing');
	});
	// ======================================= # in url scroll position
	// if(window.location.hash) {
	// 	var target = location.hash;

	// 	var $newtarget = $(target);
	// 	var offset = -110;
	// 	$('html, body').stop().animate({
	// 		'scrollTop': $newtarget.offset().top + offset
	// 	}, 900, 'swing');
	//  }

	// ============================================================================ responsive iframes
	$('iframe.responsive').wrap('<div class="embed-responsive embed-responsive-16by9"/>');
	$('iframe.responsive').addClass('embed-responsive');

	// Responsive iFrame
	$('main iframe, .article-content iframe').wrap('<div class="embed-responsive embed-responsive-16by9"/>');
	$('main iframe, .article-content iframe').addClass('embed-responsive');

	// Sweepstakes checkbox
	$('#mce-group-4687-0, #mce-group-4687-1').change(function () {
		if (($('#mce-group-4687-0').is(":checked")) && ($('#mce-group-4687-1').is(":checked"))) {
			$('#mc-embedded-subscribe').removeAttr("disabled").removeClass("disabled-button");
		} else {
			$('#mc-embedded-subscribe').attr("disabled", "disabled").addClass("disabled-button");
		}
	});
	// $('#mce-error-response').change(function(){
	// 	$('#mce-error-response').addClass('hero-headline royal-blue');
	// });
	// Show More Category Articles
	$('.show-more').click(function () {
		var txt = $('#show-more').is(':visible') ? 'Show more' : 'Show Less';
		$('.show-more').text(txt);
		$('#show-more').slideToggle();
	});
	// Show ALL Category Articles
	$('.show-all').click(function () {
		var txt = $('#show-all').is(':visible') ? 'Show all' : 'Hide all';
		$('.show-all').text(txt);
		$('#show-all').slideToggle();
	});

	// FAQ Accordion
	$('.question').click(function () {
		if ($(this).hasClass('open')) {
			$(this).removeClass('open').next('.answer').slideUp();
			$(this).find('.fa').removeClass('fa-minus').addClass('fa-plus');
		} else {
			$(this).addClass('open').next('.answer').slideDown();
			$(this).find('.fa').removeClass('fa-plus').addClass('fa-minus');
		}
	});
	// Trigger ACF Gallery Carousel
	$(".carousel-inner div:first-of-type").addClass("active");

	// Trigger OWl Carousel
	$(".owl-carousels").owlCarousel({
		items: 1,
		loop: true,
		dots: false,
		nav: false,
		margin: 0,
		autoplay: true,
		autoplayTimeout: 8000,
		smartSpeed: 2000
	});

	// Accordion read mores
	/* $('.exec-read-more').click(function(){
		var txt = $('.executive-bio').is(':visible') ? 'Read More' : 'Read Less';
		$('.exec-read-more').text(txt);
		$('.executive-bio').slideToggle();
	}); */

	// Landing Page menu active class
	$('body.category-tired-during-the-day').find('.tired-during-the-day').addClass('active-menu');
	$('body.category-trouble-falling-asleep').find('.trouble-falling-asleep').addClass('active-menu');
	$('body.category-trouble-staying-asleep').find('.trouble-staying-asleep').addClass('active-menu');
	$('body.category-i-snore-my-partner-snores').find('.i-snore-my-partner-snores').addClass('active-menu');
	$('body.category-general').find('.general').addClass('active-menu');

	// Tab content
	$('.tab-content').slice(1).hide();
	$('.tab-menu li').eq(0).addClass('active');
	$('.tab-menu li a').click(function (e) {
		e.preventDefault();
		var content = $(this).attr('href');
		$(this).parent().addClass('active');
		$(this).parent().siblings().removeClass('active');
		$(content).show();
		$(content).siblings('.tab-content').hide();
	});

	// Mega Menu (Mobile)
	// if (window.innerWidth <= 991) {

		$("#primary-nav ul.navbar-nav li > .level-chevron").on('touchstart click', function (e) {
			e.preventDefault();
			$(this).siblings('.sub-div').addClass('active');
			$('body.home.mobile-nav').css('max-height', 'calc(100vh - 70px)')
		});

		$(".back-arrow").on("click", function (e) {
			e.preventDefault();
			$('.sub-div').removeClass('active');
			$('body.home.mobile-nav').removeAttr("style")
		})
	// }

	// Footer Menu
	// jQuery("body").on("click", "footer nav.mobile .sub-menu", function (e) {
	// 	if (jQuery(this).children("ul").length > 0) {
	// 		e.preventDefault();
	// 		if (jQuery(this).hasClass("active")) {
	// 			jQuery(this).removeClass("active");
	// 		} else {
	// 			jQuery(this).addClass("active");
	// 		}
	// 		jQuery(this).children("ul").stop().slideToggle();
	// 	}
	// });
	jQuery('footer nav.mobile .sub-menu').on('click', function (e) {
		e.preventDefault();
		jQuery(this).toggleClass('active');
		jQuery(this).children('ul').slideToggle('slow');
		jQuery(this).find('a').on('click', function (event) {
			event.stopPropagation();
		});
	});
	// Header Search (Mobile)
	jQuery(".searchsubmit").bind("touchstart click", function (r) {
		jQuery(this).toggleClass("hold");
		jQuery(".search-field").toggleClass("expand");
		if (jQuery(this).hasClass("hold")) {
			return false;
		}
	})


}); // Doc Ready

jQuery(window).on("load resize", function () {

	// Footer Menu [START]
	if (window.innerWidth < 991) {
		jQuery("footer nav").attr("class", "row mobile");
		jQuery('body footer nav.mobile div').each(function () {
			jQuery(this).find('ul').parent().addClass("sub-menu");
			if (jQuery(this).hasClass('active')) {
				jQuery(this).find('ul').slideDown('slow');
			}
		})
		$(".navbar-toggler").on("click", function (e) {
			e.preventDefault();
			// $(".sub-div").hide();
		})
	} else {
		jQuery("footer nav").attr("class", "row desktop");
		jQuery('body footer nav.desktop div').removeClass("sub-menu");
	}
	// Footer Menu [END]
});

$(document).ready(function () {
	if (window.innerWidth > 1024) {
		/*$("#menu-primary li a").on("mouseover",function(){
			if(!$(this).parent().hasClass("active")){
				$(this).parent().addClass("active");
			}
		});*/
		$("#menu-primary li a").on("click", function (e) {
			$("#menu-primary li").not($(this).parent()).removeClass('active');
			if ($(this).parent().hasClass("active")) {
				$(this).parent().removeClass("active");
			} else {
				$(this).parent().addClass("active");
			}
		})
	}
});

// WL Logic ============================================================================
var SLEEPSCORE = (function () {

	const wlSources = ['mrfm-app', 'rmdg-app'];

	var parseQueryString = function () {
		var str;
		if (window.location.search && window.location.search.length > 0) {
			str = window.location.search;
		} else if (window.location.hash && window.location.hash.length > 0) {
			str = window.location.hash;
		} else {
			str = '';
		}

		var objURL = {};

		str.replace(
			new RegExp('([^?=&]+)(=([^&]*))?', 'g'),
			($0, $1, $2, $3) => objURL[$1] = $3
		);
		return objURL;
	};

	var isValidHttpUrl = function (string) {
		var url;
		try {
			url = new URL(string);
		} catch (_) {
			return false;
		}
		return url.protocol === "http:" || url.protocol === "https:";
	};

	var toggleOff = function (element, params) {
		if (element.length > 1) {
			if (element && params && wlSources.includes(params.utm_source)) {
				element.forEach((prop) => {
					prop.style.display = 'none';
				});
				return element;
			}
		} else {
			if (element && params && wlSources.includes(params.utm_source)) {
				return element.style.display = 'none';
			}
		}
	};

	var createAffLinks = function (context, links) {

		// Page starting point.
		var pageUrlParsed = parseQueryString(context);

		if (pageUrlParsed && ((pageUrlParsed.aff && pageUrlParsed.utm_source) || pageUrlParsed.layoutOptions)) {

			// Loop through page links and append affiliate link.
			for (var i = 0; i < links.length; i++) {

				// Rewrite only valid links.
				if (typeof (links[i].href) !== 'undefined' && isValidHttpUrl(links[i].href)) {

					var updatedLink = '';
					var currentLink = links[i].href;
					var params = parseQueryString();

					// If affiliate link already exists, concat both parameters on update.
					if (params.aff && params.utm_source) {
						currentLink += (currentLink.match(/[\?]/g) ? '&' : '?') + 'aff=' + pageUrlParsed.aff + '&utm_source=' + pageUrlParsed.utm_source;
						updatedLink = currentLink;
					}

					// if layout options exist
					if (params.layoutOptions) {
						currentLink += (currentLink.match(/[\?]/g) ? '&' : '?') + 'layoutOptions=' + pageUrlParsed.layoutOptions;
						updatedLink = currentLink;
					}

					// Reset the current link with updated affilation.
					links[i].setAttribute('href', updatedLink);

				}

			}

		}

	};

	return {
		wl: {
			createAffLinks: function (context, links) {
				createAffLinks(context, links);
			},
			parseQueryString: parseQueryString(),
			toggleOff: function (element, params) {
				toggleOff(element, params);
			}
		}
	};
})();

let scrollDistance = 60;
const headerEl = document.querySelector('.header-main');
const announcmentBarEl = document.querySelector('.announcement-banner');
const subnavEl = document.querySelector('.fixed-sub-nav');
const mainEl = document.querySelector('body > main');

function checkNavbarSticky() {

	if (!headerEl || !mainEl) return;

	const className = 'fixed';

	if (window.scrollY <= scrollDistance) {
		headerEl.classList.remove(className);
		if (subnavEl) {
			subnavEl.classList.remove(className);
		}
		mainEl.setAttribute('style', `margin-top: 0`);
	} else {
		headerEl.classList.add(className);

		let marginTop = headerEl.getBoundingClientRect().height;

		if (subnavEl) {
			marginTop = headerHeight + subnavEl.getBoundingClientRect().height;
			subnavEl.classList.add(className);
		}

		mainEl.setAttribute('style', `margin-top: ${marginTop}px`);
	}

}



function updateHeader() {

	let marginTop = headerEl.getBoundingClientRect().height;

	if (subnavEl) {
		subnavEl.setAttribute('style', `top: ${marginTop}px`);
	}

	if (announcmentBarEl) {

		scrollDistance = announcmentBarEl.getBoundingClientRect().height;
		window.addEventListener('resize', () => {
			scrollDistance = announcmentBarEl.getBoundingClientRect().height;
		});

	} 
	else if(headerEl) {

		const mainEl = document.querySelector('body > main');
		mainEl.setAttribute('style', `margin-top: ${marginTop}px`);

		if (subnavEl) {
			subnavEl.classList.add('fixed');
		}

		headerEl.classList.add('fixed');

	}

	
}

document.addEventListener('DOMContentLoaded', function (event) {
	// Get current context and links on the current page.
	var context = window.location.search;

	// Get params to pass into display methods later on.
	var params = SLEEPSCORE.wl.parseQueryString;
	if (params.lang === 'de') {
		// German Cookie Consent Banner
		window.cookieconsent_options = { "message": "Wir verwenden Cookies zur Personalisierung deiner Erfahrung. Indem du auf der Website bleibst, stimmst du unserer Verwendung von Cookies und unserer Datenschutzerklärung zu.", "dismiss": "Verstanden!", "learnMore": "Mehr erfahren", "link": "/cookies-und-vergleichbare-technologien/?lang=de", "theme": "dark-bottom" };
	}

	// Target app store article midway banners.
	var midwaybanner = document.querySelector('#midway-banner-outer-app-version');
	if (midwaybanner) {
		SLEEPSCORE.wl.toggleOff(midwaybanner, params);
	}
	var midway2banner = document.querySelector('#midway-banner-outer-621');
	if (midway2banner) {
		SLEEPSCORE.wl.toggleOff(midway2banner, params);
	}

	// Target app store article bottom banners.
	var bottombanner = document.querySelector('#download-now');
	if (bottombanner) {
		SLEEPSCORE.wl.toggleOff(bottombanner, params);
	}

	// Target global footer app store elements.
	var globalAppFooter = document.querySelector('.footer-bottom .get-the-app');
	if (globalAppFooter) {
		SLEEPSCORE.wl.toggleOff(globalAppFooter, params);
	}

	// Get links on the current page.
	var links = document.getElementsByTagName('a');
	if ((context && context !== undefined) && (links && links.length > 0)) {
		SLEEPSCORE.wl.createAffLinks(context, links);
	}

	// App buttons anywhere.
	var appButtons = document.querySelectorAll('.app-button');
	if (appButtons) {
		SLEEPSCORE.wl.toggleOff(appButtons, params);
	}
	
	const menuBtns = document.querySelectorAll('#primary-nav .btn-icon');
	menuBtns.forEach((btn) => {

		const submenu = btn.nextElementSibling;
		submenu.classList.add('collapse');
		$(btn).on('click', function() {

			const $parent = $(this).parent();

			// close open menus
			const $showLi = $('#primary-nav li.show').not($parent);
			if ($showLi.length > 0) {
				$showLi.removeClass('show');
				$showLi.find('.sub-menu').collapse('hide');
			}
			
			$(submenu).collapse('toggle');
			$(this).parent().toggleClass('show');

		});

	});

	updateHeader();

	if (announcmentBarEl) {
		document.addEventListener('scroll', () => {
			checkNavbarSticky();
		});
		checkNavbarSticky();
	}
	
});
