import Swiper from "swiper";
import { Autoplay, Controller, Navigation, Pagination } from "swiper/modules";
Swiper.use([Autoplay, Navigation, Pagination, Controller]);
export let teamSwiper;
export function initSwipers() {
  const testimonialSwiper = new Swiper(".testimonial-swiper", {
    slidesPerView: "auto",
    centeredSlides: true,
    loop: true,
    grabCursor: true,
    speed: 500,
    breakpoints: {
      0: {
        spaceBetween: 24,
      },
      768: {
        spaceBetween: 40,
      },
    },
    autoplay: {
      delay: 4000,
    },
    pagination: {
      el: ".swiper-pagination",
      clickable: true,
      type: "bullets",
      renderBullet: function (index, className) {
        const totalSlides = this.slides.length;
        return `<span class="${className}" aria-label="View slide ${index + 1} of ${totalSlides}"></span>`;
      },
    },
  });
  const utility = new Swiper(".utility-swiper", {
    slidesPerView: "auto",
    spaceBetween: 24,
    grabCursor: true,
    navigation: {},
    breakpoints: {
      640: {
        spaceBetween: 32,
      },
    },
    pagination: {
      el: ".swiper-pagination",
      clickable: true,
      type: "bullets",
      renderBullet: function (index, className) {
        const totalSlides = this.slides.length;
        return `<span class="${className}" aria-label="View slide ${index + 1} of ${totalSlides}"></span>`;
      },
    },
  });
  const posts = new Swiper(".posts-swiper", {
    slidesPerView: "auto",
    grabCursor: true,
    navigation: {},
    breakpoints: {},
  });
  teamSwiper = new Swiper(".team-swiper", {
    slidesPerView: "auto",
    grabCursor: true,
    navigation: {},
    breakpoints: {},
    pagination: {
      el: ".swiper-pagination",
      clickable: true,
      type: "bullets",
      renderBullet: function (index, className) {
        const totalSlides = this.slides.length;
        return `<span class="${className}" aria-label="View slide ${index + 1} of ${totalSlides}"></span>`;
      },
    },
  });
}
