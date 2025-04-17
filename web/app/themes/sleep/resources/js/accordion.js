export function initAccordions() {
  document.addEventListener("click", (event) => {
    // Find the closest accordion item clicked
    const section = event.target.closest(".accordion");
    if (!section || !section.closest(".accordion-container")) return;

    const accordionContainer = section.closest(".accordion-container");

    // Check if the section is active
    const isActive = section.classList.contains("active");

    // Toggle the active state
    if (isActive) {
      section.classList.remove("active");
      section.setAttribute("aria-expanded", "false");
    } else {
      section.classList.add("active");
      section.setAttribute("aria-expanded", "true");
    }
  });
}
