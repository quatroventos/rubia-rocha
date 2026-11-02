(() => {
        const shouldReduceMotion = window.matchMedia(
          "(prefers-reduced-motion: reduce)",
        ).matches;

        const revealItems = [
          [".expertise-hero__title", "left", 0],
          [".expertise-hero__copy", "right", 100],
          [".expertise-card", "up", 80],
          [".site-footer__brand", "left", 0],
          [".site-footer__nav", "right", 100],
        ];

        const revealClassByType = {
          left: "reveal--left",
          right: "reveal--right",
          scale: "reveal--scale",
          up: "",
        };

        const elements = [];

        revealItems.forEach(([selector, type, baseDelay]) => {
          document.querySelectorAll(selector).forEach((element, index) => {
            element.classList.add("reveal");
            const revealClass = revealClassByType[type];
            if (revealClass) {
              element.classList.add(revealClass);
            }
            element.style.setProperty(
              "--reveal-delay",
              `${baseDelay + index * 70}ms`,
            );
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