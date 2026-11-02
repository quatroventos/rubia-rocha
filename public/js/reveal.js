(() => {
  const items = document.querySelectorAll(".reveal");
  if (!items.length) {
    return;
  }

  if (window.matchMedia("(prefers-reduced-motion: reduce)").matches) {
    items.forEach((el) => el.classList.add("is-visible"));
    return;
  }

  if (!("IntersectionObserver" in window)) {
    items.forEach((el) => el.classList.add("is-visible"));
    return;
  }

  const observer = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          entry.target.classList.add("is-visible");
          observer.unobserve(entry.target);
        }
      });
    },
    { threshold: 0.12, rootMargin: "0px 0px -8% 0px" },
  );

  items.forEach((el, index) => {
    el.style.setProperty("--reveal-delay", `${Math.min(index % 6, 5) * 40}ms`);
    observer.observe(el);
  });
})();
