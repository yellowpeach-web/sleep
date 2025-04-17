window.$ = jQuery;
import { initStatsCounter } from "./stat-ticker.js";
import { initAccordions } from "./accordion";
import { navVisibility } from "./nav.js";
import { initHoverImageCursor } from "./image-cursor.js";
// import Swiper from 'swiper';
// import AOS from 'aos';
$(document).ready(function () {
  initAccordions();
  initStatsCounter();
  initHoverImageCursor();
  navVisibility();
});
