export function navVisibility() {
  let lastScrollY = window.scrollY;
  const navbar = document.getElementById("wrapper-navbar");
  const menuModal = document.querySelector(".menu-modal");

  if (!navbar) return;

  const navbarHeight = navbar.offsetHeight;

  window.addEventListener("scroll", () => {
    const currentScrollY = window.scrollY;

    // Check if menuModal is active
    const isMenuActive = menuModal?.classList.contains("active");

    if (!isMenuActive && currentScrollY > navbarHeight) {
      if (currentScrollY > lastScrollY) {
        navbar.style.transform = "translateY(-100%)";
      } else {
        navbar.style.transform = "translateY(0)";
      }
    }

    lastScrollY = currentScrollY;
  });
}
