export function initPortraitVideoModal() {
  const playButtons = document.querySelectorAll(".portrait-play-button");
  const modal = document.getElementById("portrait-video-modal");

  if (!modal || playButtons.length === 0) return;

  const video = modal.querySelector("video");
  const source = video.querySelector("source");
  const closeBtn = modal.querySelector(".portrait-modal-close");

  playButtons.forEach((button) => {
    button.addEventListener("click", () => {
      const videoUrl = button.dataset.video;
      const type = button.dataset.type;

      source.src = videoUrl;
      source.type = type;
      video.load();
      video.play();
      modal.classList.remove("hidden");
    });
  });

  closeBtn.addEventListener("click", () => {
    video.pause();
    video.currentTime = 0;
    modal.classList.add("hidden");
  });
}
