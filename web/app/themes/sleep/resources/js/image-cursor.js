export function initHoverImageCursor() {
  const serviceListItems = document.querySelectorAll(".service-list-item");

  if (serviceListItems) {
    serviceListItems.forEach((item) => {
      const hoverImage = item.querySelector(".hover-image");
      item.addEventListener("mouseenter", () => {
        hoverImage.classList.add("active");
      });
      item.addEventListener("mousemove", (e) => {
        const imgWidth = hoverImage.offsetWidth;
        const imgHeight = hoverImage.offsetHeight;
        hoverImage.style.top = `${e.clientY - imgHeight + 10}px`;
        hoverImage.style.left = `${e.clientX - imgWidth / 2}px`;
      });
      item.addEventListener("mouseleave", () => {
        hoverImage.classList.remove("active");
      });
      window.addEventListener("scroll", () => {
        hoverImage.classList.remove("active");
      });
    });
  }
}
