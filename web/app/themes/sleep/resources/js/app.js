window.$ = jQuery;
import { initStatsCounter } from "./stat-ticker.js";
import { initAccordions } from "./accordion";
import { navVisibility } from "./nav.js";
import { initHoverImageCursor } from "./image-cursor.js";
import { initSwipers } from "./swiper";
import { filterFaqs } from "./faq-filter.js";
import { initMasonary } from "./masonary.js";
// import AOS from 'aos';
$(document).ready(function () {
  initAccordions();
  initStatsCounter();
  initHoverImageCursor();
  navVisibility();
  initSwipers();
  filterFaqs();
  initMasonary();
});
