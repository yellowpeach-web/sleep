import Macy from "macy";

export function initMasonary() {
  const blogFeedContainer = Macy({
    container: ".blog-feed-container",
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
