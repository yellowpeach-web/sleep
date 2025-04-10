export function initAccordions() {
  document.addEventListener("click", (event) => {
    // Find the closest accordion item clicked
    const section = event.target.closest(".accordion");
    if (!section || !section.closest(".accordion-container")) return;

    const accordionContainer = section.closest(".accordion-container");
    const sections = accordionContainer.querySelectorAll(".accordion");

    // Check if the section is active
    const isActive = section.classList.contains("active");

    // Close all sections
    sections.forEach((s) => {
      s.classList.remove("active");
      s.setAttribute("aria-expanded", "false");
    });

    // If not active, toggle the current section to active
    if (!isActive) {
      section.classList.add("active");
      section.setAttribute("aria-expanded", "true");
    }
  });
}
