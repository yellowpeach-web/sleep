import { teamSwiper } from "./swiper";
export function filterData() {
  const faqSection = document.querySelectorAll(".filter-data-section");

  faqSection.forEach((section) => {
    const data = section.querySelectorAll("#all-data > li");
    const leftCol = section.querySelector('.data-container[data-col="left"]');
    const rightCol = section.querySelector('.data-container[data-col="right"]');
    const singleCol = section.querySelector(".data-container.single-col");
    const swiperCol = section.querySelector(".data-container.swiper-col");

    const filterButtons = section.querySelectorAll(".filter-button");

    if (!leftCol || !rightCol || !singleCol) return;

    function renderFaqs(items) {
      leftCol.innerHTML = "";
      rightCol.innerHTML = "";
      singleCol.innerHTML = "";
      if (swiperCol) swiperCol.innerHTML = "";

      items.forEach((item, index) => {
        const clone = item.cloneNode(true);
        const cloneForMobile = item.cloneNode(true);

        if (index % 2 === 0) {
          leftCol.appendChild(clone);
        } else {
          rightCol.appendChild(clone);
        }
        singleCol.appendChild(cloneForMobile);
        if (swiperCol) {
          const swiperSlide = document.createElement("div");
          swiperSlide.classList.add("swiper-slide");
          swiperSlide.appendChild(cloneForMobile.cloneNode(true));
          swiperCol.appendChild(swiperSlide);
          if (typeof teamSwiper !== "undefined" && teamSwiper?.slideTo) {
            teamSwiper.update();
            teamSwiper.slideTo(0, 0);
          }
        }
      });
    }

    function applyFilter(filterValue = null) {
      let items;
      if (filterValue) {
        items = Array.from(data).filter((item) => item.dataset.term === filterValue);
      } else {
        items = Array.from(data);
      }

      renderFaqs(items);

      filterButtons.forEach((btn) => btn.classList.toggle("active", btn.dataset.filter === filterValue));
    }

    // set default filter based on .active class
    const defaultActive = section.querySelector(".filter-button.active");
    const defaultFilter = defaultActive?.dataset.filter || null;
    applyFilter(defaultFilter);

    // Setup filter buttons
    filterButtons.forEach((button) => {
      button.addEventListener("click", () => {
        applyFilter(button.dataset.filter);
      });
    });
  });
}
