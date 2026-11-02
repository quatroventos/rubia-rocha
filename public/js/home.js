(() => {
        const branchBlocks = document.querySelectorAll("[data-branches]");

        branchBlocks.forEach((block) => {
          const tabs = Array.from(
            block.querySelectorAll("[data-branch-target]"),
          );
          const panels = Array.from(block.querySelectorAll(".branch-panel"));

          const activateBranch = (tab) => {
            const targetId = tab.dataset.branchTarget;

            tabs.forEach((item) => {
              const isActive = item === tab;
              item.classList.toggle("is-active", isActive);
              item.setAttribute("aria-selected", String(isActive));
              item.tabIndex = isActive ? 0 : -1;
            });

            panels.forEach((panel) => {
              const isActive = panel.id === targetId;
              panel.classList.toggle("is-active", isActive);
              panel.hidden = !isActive;
            });
          };

          tabs.forEach((tab, index) => {
            tab.addEventListener("click", () => activateBranch(tab));

            tab.addEventListener("keydown", (event) => {
              const direction =
                event.key === "ArrowDown" || event.key === "ArrowRight"
                  ? 1
                  : event.key === "ArrowUp" || event.key === "ArrowLeft"
                    ? -1
                    : 0;

              if (!direction) {
                return;
              }

              event.preventDefault();
              const nextTab =
                tabs[(index + direction + tabs.length) % tabs.length];
              nextTab.focus();
              activateBranch(nextTab);
            });
          });
        });
      })();

      (() => {
        const hero = document.querySelector(".hero");
        const parallaxQuery = window.matchMedia(
          "(min-width: 760px) and (prefers-reduced-motion: no-preference)",
        );

        if (!hero) {
          return;
        }

        let animationFrame = 0;

        const updateParallax = () => {
          animationFrame = 0;

          if (!parallaxQuery.matches) {
            hero.style.removeProperty("--hero-cloud-parallax-y");
            hero.style.removeProperty("--hero-content-parallax-y");
            return;
          }

          const heroRect = hero.getBoundingClientRect();

          if (heroRect.bottom <= 0 || heroRect.top >= window.innerHeight) {
            return;
          }

          const progress = Math.min(
            1,
            Math.max(0, window.scrollY / hero.offsetHeight),
          );
          const cloudOffset = progress * 130;
          const contentOffset = progress * 34;

          hero.style.setProperty(
            "--hero-cloud-parallax-y",
            `${cloudOffset}px`,
          );
          hero.style.setProperty(
            "--hero-content-parallax-y",
            `${contentOffset}px`,
          );
        };

        const requestParallaxUpdate = () => {
          if (!animationFrame) {
            animationFrame = window.requestAnimationFrame(updateParallax);
          }
        };

        window.addEventListener("scroll", requestParallaxUpdate, {
          passive: true,
        });
        window.addEventListener("resize", requestParallaxUpdate);
        parallaxQuery.addEventListener?.("change", requestParallaxUpdate);
        requestParallaxUpdate();
      })();

      (() => {
        const shouldReduceMotion = window.matchMedia(
          "(prefers-reduced-motion: reduce)",
        ).matches;

        const revealItems = [
          [".intro-card", "scale", 0],
          [".intro-card h2", "up", 70],
          [".intro-card p", "up", 130],
          [".intro-card li", "up", 170],
          [".portfolio-video__copy", "left", 0],
          [".portfolio-video__frame", "right", 100],
          [".solutions__heading h2", "up", 0],
          [".solutions__heading p", "up", 60],
          [".solutions__visual img", "left", 110],
          [".solution-card", "right", 90],
          [".image-strip", "scale", 0],
          [".management-section__content", "left", 0],
          [".management-section__image", "right", 90],
          [".accent-block", "scale", 180],
          [".dark-feature__card", "scale", 0],
          [".dark-feature__media", "left", 0],
          [".dark-feature__content", "right", 100],
          [".office-locations .section-heading", "up", 0],
          [".office-locations__city-list", "left", 100],
          [".office-locations__panel", "right", 140],
          [".office-location", "up", 80],
          [".national-coverage__map", "left", 0],
          [".national-coverage__content", "right", 120],
          [".national-coverage__card li", "up", 180],
          [".final-cta h2", "up", 0],
          [".final-cta .button", "up", 160],
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

            const typeClass = revealClassByType[type];
            if (typeClass) {
              element.classList.add(typeClass);
            }

            element.style.setProperty(
              "--reveal-delay",
              `${baseDelay + index * 60}ms`,
            );
            elements.push(element);
          });
        });

        const resetRevealDelay = (element) => {
          const delay = Number.parseFloat(
            getComputedStyle(element).getPropertyValue("--reveal-delay"),
          );

          window.setTimeout(
            () => element.style.setProperty("--reveal-delay", "0ms"),
            (Number.isFinite(delay) ? delay : 0) + 700,
          );
        };

        const showElement = (element) => {
          element.classList.add("is-visible");
          resetRevealDelay(element);
        };

        if (shouldReduceMotion || !("IntersectionObserver" in window)) {
          elements.forEach((element) => {
            element.classList.add("is-visible");
            element.style.setProperty("--reveal-delay", "0ms");
          });
          return;
        }

        const observer = new IntersectionObserver(
          (entries) => {
            entries.forEach((entry) => {
              if (!entry.isIntersecting) {
                return;
              }

              showElement(entry.target);
              observer.unobserve(entry.target);
            });
          },
          {
            threshold: 0.16,
            rootMargin: "0px 0px -8% 0px",
          },
        );

        elements.forEach((element) => observer.observe(element));
      })();