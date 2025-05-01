export function initStatsCounter() {
  const counters = document.querySelectorAll(".stat-number");

  const DURATION = 1500;
  const DECIMAL_DELAY = 100;

  function animateCounter(counter) {
    const target = parseFloat(counter.getAttribute("data-target-number"));
    const isFloat = !Number.isInteger(target);
    const append = counter.parentElement?.querySelector(".append");

    setTimeout(() => {
      append?.classList.add("active");
    }, DURATION + DECIMAL_DELAY - 100);

    if (target < 5) {
      setTimeout(() => {
        counter.innerText = formatNumber(target, isFloat);
      }, DURATION);
      return;
    }

    const startTime = performance.now();

    function formatNumber(num, showDecimal) {
      return num.toLocaleString(undefined, {
        minimumFractionDigits: showDecimal ? 1 : 0,
        maximumFractionDigits: showDecimal ? 1 : 0,
      });
    }

    function update(now) {
      const elapsed = now - startTime;
      const progress = Math.min(elapsed / DURATION, 1);
      const currentValue = target * progress;

      const showDecimal = false; // don't show decimals during animation
      counter.innerText = formatNumber(currentValue, showDecimal);

      if (progress < 1) {
        requestAnimationFrame(update);
      } else {
        // Animation complete: show rounded number first
        counter.innerText = formatNumber(target, false);

        // Then apply decimal after a short delay if needed
        if (isFloat) {
          setTimeout(() => {
            counter.innerText = formatNumber(target, true);
          }, DECIMAL_DELAY);
        }
      }
    }

    requestAnimationFrame(update);
  }

  const observer = new IntersectionObserver(
    (entries, obs) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          animateCounter(entry.target);
          obs.unobserve(entry.target);
        }
      });
    },
    { threshold: 0.5 }
  );

  counters.forEach((counter) => observer.observe(counter));
}
