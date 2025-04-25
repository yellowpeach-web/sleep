import Macy from "macy";

export function initMasonary() {
  const container = document.querySelector(".blog-feed-container");

  if (!container) return;

  const blogFeedContainer = Macy({
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
    },
  });
}
