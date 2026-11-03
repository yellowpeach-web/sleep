export function initStatsCounter() {
  const counters = document.querySelectorAll(".stat-number");
  if (!counters.length) return;

  const DURATION = 1500;
  const DECIMAL_DELAY = 100;
  const reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  function formatNumber(num, showDecimal) {
    return num.toLocaleString(undefined, {
      minimumFractionDigits: showDecimal ? 1 : 0,
      maximumFractionDigits: showDecimal ? 1 : 0,
    });
  }

  function isInView(el) {
    const rect = el.getBoundingClientRect();
    if (rect.width === 0 || rect.height === 0) return false;

    const viewHeight = window.innerHeight || document.documentElement.clientHeight;
    return rect.top < viewHeight && rect.bottom > 0;
  }

  function animateCounter(counter) {
    if (counter.dataset.counted === "true") return;

    const target = parseFloat(counter.getAttribute("data-target-number"));
    if (!Number.isFinite(target)) {
      counter.dataset.counted = "true";
      return;
    }

    counter.dataset.counted = "true";

    const isFloat = !Number.isInteger(target);
    const parent = counter.parentElement;
    const append = parent?.querySelector(".append");

    const showFinal = () => {
      counter.textContent = formatNumber(target, isFloat);
      append?.classList.add("active");
    };

    // Values under 5 barely move, so keep the server-rendered number.
    if (reduceMotion || target < 5) {
      showFinal();
      return;
    }

    parent?.classList.add("is-counting");
    counter.textContent = formatNumber(0, false);

    const startTime = performance.now();

    function update(now) {
      const elapsed = now - startTime;
      const progress = Math.min(elapsed / DURATION, 1);
      const currentValue = target * progress;

      counter.textContent = formatNumber(currentValue, false);

      if (progress < 1) {
        requestAnimationFrame(update);
        return;
      }

      counter.textContent = formatNumber(target, false);
      append?.classList.add("active");

      if (isFloat) {
        window.setTimeout(() => {
          counter.textContent = formatNumber(target, true);
        }, DECIMAL_DELAY);
      }
    }

    requestAnimationFrame(update);
  }

  function scan() {
    let pending = false;

    counters.forEach((counter) => {
      if (counter.dataset.counted === "true") return;

      if (isInView(counter)) {
        animateCounter(counter);
      } else {
        pending = true;
      }
    });

    if (!pending) {
      window.removeEventListener("scroll", onScroll);
      window.removeEventListener("resize", scan);
      document.removeEventListener("sleep:stats-refresh", scan);
    }
  }

  let scrollQueued = false;

  function onScroll() {
    if (scrollQueued) return;
    scrollQueued = true;
    window.requestAnimationFrame(() => {
      scrollQueued = false;
      scan();
    });
  }

  if ("IntersectionObserver" in window) {
    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (!entry.isIntersecting || entry.intersectionRatio <= 0) return;
          animateCounter(entry.target);
          observer.unobserve(entry.target);
        });
      },
      { threshold: 0 }
    );

    counters.forEach((counter) => observer.observe(counter));
  }

  window.addEventListener("scroll", onScroll, { passive: true });
  window.addEventListener("resize", scan);
  document.addEventListener("sleep:stats-refresh", scan);
  scan();
}
