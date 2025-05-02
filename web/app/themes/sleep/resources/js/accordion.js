export function initAccordions() {
  document.addEventListener("click", (event) => {
    const section = event.target.closest(".accordion");
    if (!section) return;

    const container = section.closest(".accordion-container");
    if (!container) return;

    const isActive = section.classList.contains("active");

    if (!isActive) {
      const currentlyActive = document.querySelector(".accordion.active");
      if (currentlyActive && currentlyActive !== section) {
        currentlyActive.classList.remove("active");
        currentlyActive.setAttribute("aria-expanded", "false");
      }

      // Open the clicked one
      section.classList.add("active");
      section.setAttribute("aria-expanded", "true");
    } else {
      // Just close the clicked one
      section.classList.remove("active");
      section.setAttribute("aria-expanded", "false");
    }
  });
}
