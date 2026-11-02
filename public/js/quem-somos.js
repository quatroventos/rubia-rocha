(() => {
        const shouldReduceMotion = window.matchMedia(
          "(prefers-reduced-motion: reduce)",
        ).matches;

        const revealItems = [
          [".about-hero .section-heading", "left", 0],
          [".about-hero__copy", "right", 120],
          [".about-strip__image", "scale", 0],
          [".culture-section__image", "left", 0],
          [".culture-section__content", "right", 110],
          [".mission-card", "up", 0],
          [".values-section__title", "up", 0],
          [".value-card", "up", 80],
          [".leaders h2", "up", 0],
          [".leader-card", "up", 90],
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