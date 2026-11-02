(() => {
        const shouldReduceMotion = window.matchMedia(
          "(prefers-reduced-motion: reduce)",
        ).matches;

        const revealItems = [
          [".career-hero__copy", "left", 0],
          [".career-aside", "left", 0],
          [".career-form-card", "right", 100],
          [".map-card .section-heading", "up", 0],
          [".map-frame", "up", 100],
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

      (() => {
        const form = document.querySelector(".application-form");
        const feedback = document.querySelector("#form-feedback");
        const status = new URLSearchParams(window.location.search).get("status");

        if (feedback && status) {
          const messages = {
            enviado: "Mensagem enviada com sucesso. Nosso time entrará em contato em breve.",
            invalido: "Não foi possível enviar. Revise os campos obrigatórios e tente novamente.",
            erro: "Ocorreu um erro no envio. Tente novamente em alguns instantes.",
          };

          if (messages[status]) {
            feedback.textContent = messages[status];
            feedback.hidden = false;
            feedback.classList.remove("form-feedback--success", "form-feedback--error");
            feedback.classList.add(
              status === "enviado"
                ? "form-feedback--success"
                : "form-feedback--error",
            );
            feedback.setAttribute("role", status === "enviado" ? "status" : "alert");
            feedback.scrollIntoView({ behavior: "smooth", block: "center" });
          }
        }

        form?.addEventListener("submit", () => {
          const submitButton = form.querySelector('button[type="submit"]');

          if (submitButton) {
            submitButton.disabled = true;
            submitButton.textContent = "Enviando...";
          }
        });
      })();
