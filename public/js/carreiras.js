(() => {
        const shouldReduceMotion = window.matchMedia(
          "(prefers-reduced-motion: reduce)",
        ).matches;

        const revealItems = [
          [".career-opportunity-layout", "up", 0],
          [".site-footer__brand", "left", 0],
          [".site-footer__nav", "right", 100],
        ];

        const revealClassByType = {
          left: "reveal--left",
          right: "reveal--right",
          up: "",
        };

        const elements = [];

        revealItems.forEach(([selector, type, delay]) => {
          document.querySelectorAll(selector).forEach((element, index) => {
            element.classList.add("reveal");
            const revealClass = revealClassByType[type];
            if (revealClass) {
              element.classList.add(revealClass);
            }
            element.style.transitionDelay = `${delay + index * 70}ms`;
            elements.push(element);
          });
        });

        if (shouldReduceMotion || !("IntersectionObserver" in window)) {
          elements.forEach((element) => element.classList.add("is-visible"));
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
          { threshold: 0.16 },
        );

        elements.forEach((element) => observer.observe(element));
      })();