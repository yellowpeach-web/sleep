export function initStatsCounter() {
  const counters = document.querySelectorAll(".stat-number");

  function animateCounter(counter) {
    let rateCounter = 0;

    function formatNumber(number) {
      return number.toLocaleString(); // Automatically formats with appropriate commas
    }

    function updateCounter() {
      const targetNumber = +counter.getAttribute("data-target-number");

      if (targetNumber === 0) {
        counter.innerText = 0;
        return;
      }
      if (targetNumber <= 30) {
        counter.innerText = formatNumber(targetNumber);
      }

      const current = +counter.innerText;
      let increment = targetNumber / 30;

      let rateOfChange = 50;
      if (current < targetNumber) {
        counter.innerText = Math.ceil(current + increment);
        rateCounter++;
        if (rateCounter > 10) {
          rateOfChange = 40;
        }
        if (rateCounter > 20) {
          rateOfChange = 30;
        }
        setTimeout(updateCounter, rateOfChange);
      } else {
        counter.innerText = formatNumber(targetNumber);
      }
    }

    updateCounter();
  }

  const observer = new IntersectionObserver(
    (entries, observer) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          animateCounter(entry.target);
          observer.unobserve(entry.target);
        }
      });
    },
    { threshold: 0.5 }
  );

  if (counters) {
    counters.forEach((counter) => observer.observe(counter));
  }
}
