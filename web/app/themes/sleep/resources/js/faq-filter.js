export function filterFaqs() {
  const faqSection = document.querySelectorAll(".faq-section");

  faqSection.forEach((section) => {
    const allFaqs = section.querySelectorAll("#all-faqs > li");
    const leftCol = section.querySelector('.accordion-container[data-col="left"]');
    const rightCol = section.querySelector('.accordion-container[data-col="right"]');
    const singleCol = section.querySelector(".accordion-container.faq-single-col");
    const filterButtons = section.querySelectorAll(".faq-filter-button");

    if (!leftCol || !rightCol || !singleCol) return;

    function renderFaqs(items) {
      leftCol.innerHTML = "";
      rightCol.innerHTML = "";
      singleCol.innerHTML = "";

      items.forEach((item, index) => {
        const clone = item.cloneNode(true);
        const cloneForMobile = item.cloneNode(true);

        if (index % 2 === 0) {
          leftCol.appendChild(clone);
        } else {
          rightCol.appendChild(clone);
        }

        singleCol.appendChild(cloneForMobile);
      });
    }

    function applyFilter(filterValue = null) {
      let items;
      if (filterValue) {
        items = Array.from(allFaqs).filter((item) => item.dataset.category === filterValue);
      } else {
        items = Array.from(allFaqs);
      }

      renderFaqs(items);

      filterButtons.forEach((btn) => btn.classList.toggle("active", btn.dataset.filter === filterValue));
    }

    // 🟢 Look for default filter based on .active class
    const defaultActive = section.querySelector(".faq-filter-button.active");
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
