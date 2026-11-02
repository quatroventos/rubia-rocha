(() => {
  const revealItems = document.querySelectorAll(".reveal");
  const revealNow = (element) => {
    element.classList.add("is-visible");
  };

  if (!("IntersectionObserver" in window)) {
    revealItems.forEach(revealNow);
    return;
  }

  const observer = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (!entry.isIntersecting) {
          return;
        }

        revealNow(entry.target);
        observer.unobserve(entry.target);
      });
    },
    { threshold: 0.16, rootMargin: "0px 0px -8% 0px" },
  );

  revealItems.forEach((element, index) => {
    element.style.setProperty(
      "--reveal-delay",
      `${Math.min(index * 55, 220)}ms`,
    );
    observer.observe(element);
  });
})();
