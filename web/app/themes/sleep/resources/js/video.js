export function initVideos() {
  const videoViews = document.querySelectorAll(".video-view");

  videoViews.forEach((view) => {
    const video = view.querySelector(".yp-video");
    const playButton = view.querySelector(".video-play-button");
    if (!video) return;

    view.addEventListener("click", () => {
      if (video.paused) {
        video.play();
      }
    });

    video.addEventListener("play", () => {
      video.setAttribute("controls", true);
      playButton.classList.add("active");
    });

    video.addEventListener("pause", () => {
      video.removeAttribute("controls");
      playButton.classList.remove("active");
    });
  });
}
