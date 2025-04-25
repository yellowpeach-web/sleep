export function initHamburger() {
  const hamburger = document.querySelector(".yp-hamburger");
  const menuModal = document.querySelector(".menu-modal");

  if (hamburger && menuModal) {
    hamburger.addEventListener("click", () => {
      const isExpanded = hamburger.getAttribute("aria-expanded") === "true";
      hamburger.setAttribute("aria-expanded", !isExpanded);
      hamburger.classList.toggle("active");
      menuModal.classList.toggle("active");
    });
  }
}
