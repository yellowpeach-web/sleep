import { teamSwiper } from "./swiper";
export function filterData() {
  const dataSection = document.querySelectorAll(".filter-data-section");

  dataSection.forEach((section) => {
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

export function simpleFilterData() {
  const sections = document.querySelectorAll(".simple-data-filter");

  sections.forEach((section) => {
    const filterButtons = section.querySelectorAll(".filter-button");
    const views = section.querySelectorAll(".simple-data-view");

    if (!filterButtons.length || !views.length) return;

    function activateFilter(filterValue) {
      views.forEach((view) => {
        if (view.dataset.filter === filterValue) {
          view.style.display = "flex";
        } else {
          view.style.display = "none";
        }
      });

      filterButtons.forEach((btn) => {
        if (btn.dataset.filter === filterValue) {
          btn.classList.add("active");
        } else {
          btn.classList.remove("active");
        }
      });
    }

    // on mount...
    const firstButton = filterButtons[0];
    if (firstButton) {
      activateFilter(firstButton.dataset.filter);
    }

    // on clicks...
    filterButtons.forEach((button) => {
      button.addEventListener("click", (e) => {
        e.preventDefault();
        const filterValue = button.dataset.filter;
        activateFilter(filterValue);
      });
    });
  });
}
