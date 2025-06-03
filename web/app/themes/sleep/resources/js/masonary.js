import Macy from "macy";

let macyInstances = [];

export function initMasonary() {
  const containers = document.querySelectorAll(".blog-feed-container");
  if (!containers.length) return;

  // Clean up existing instances
  macyInstances.forEach((instance) => instance.remove());
  macyInstances = [];

  containers.forEach((container) => {
    container.style.opacity = "0";

    requestAnimationFrame(() => {
      setTimeout(() => {
        const instance = Macy({
          container: container,
          margin: {
            x: 32,
            y: 32,
          },
          columns: 3,
          breakAt: {
            1280: {
              columns: 2,
            },
            768: {
              columns: 1,
              margin: {
                x: 32,
                y: 24,
              },
            },
          },
        });

        macyInstances.push(instance);
        // Delay for none image cards.
        setTimeout(() => {
          instance.recalculate(true);
          container.style.opacity = "1";
        }, 100);
      }, 100);
    });
  });
}
