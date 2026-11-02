(() => {
        const elements = Array.from(document.querySelectorAll(".reveal"));
        const shouldReduceMotion = window.matchMedia(
          "(prefers-reduced-motion: reduce)",
        ).matches;

        elements.forEach((element, index) => {
          element.style.setProperty("--reveal-delay", `${(index % 3) * 70}ms`);
        });

        if (shouldReduceMotion || !("IntersectionObserver" in window)) {
          elements.forEach((element) => element.classList.add("is-visible"));
          return;
        }

        const observer = new IntersectionObserver(
          (entries) => {
            entries.forEach((entry) => {
              if (!entry.isIntersecting) {
                return;
              }

              entry.target.classList.add("is-visible");
              observer.unobserve(entry.target);
            });
          },
          {
            threshold: 0.14,
            rootMargin: "0px 0px -8% 0px",
          },
        );

        elements.forEach((element) => observer.observe(element));
      })();