import { initMasonary } from "./masonary.js";
import { initPostSwiper } from "./swiper.js";

export function initAjaxFilter() {
  const currentPostId = document.body.dataset.currentPostId || null;
  const filterSections = document.querySelectorAll(".ajax-filter-section");
  if (filterSections) {
    filterSections.forEach((section) => {
      const filterButtons = section.querySelectorAll(".filter-button");
      const filterView = section.querySelector(".feed-filter-results");
      if (filterButtons) {
        filterButtons.forEach((button) => {
          button.addEventListener("click", () => {
            const action = "filter_posts";
            const term = button.dataset.filter;
            const formData = new FormData();
            formData.append("term", term);
            formData.append("current_post_id", currentPostId);
            handleActiveButton(filterButtons, button);
            fetch(`/wp/wp-admin/admin-ajax.php?action=${action}`, {
              method: "POST",
              body: formData,
            })
              .then((res) => res.json())
              .then((data) => {
                if (data.success) {
                  if (data.data.html) {
                    filterView.innerHTML = data.data.html;
                    initMasonary();
                    initPostSwiper();
                  }
                }
              });
          });
        });
      }
    });
  }
}

function handleActiveButton(buttons, activeButton) {
  buttons.forEach((button) => {
    if (button.classList.contains("active")) {
      button.classList.remove("active");
    }
    activeButton.classList.add("active");
  });
}
