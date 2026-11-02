(() => {
        const branchBlocks = document.querySelectorAll("[data-branches]");

        branchBlocks.forEach((block) => {
          const select = block.querySelector("[data-branch-select]");
          const panels = Array.from(block.querySelectorAll(".branch-panel"));

          const activateBranch = (targetId) => {
            panels.forEach((panel) => {
              const isActive = panel.id === targetId;
              panel.classList.toggle("is-active", isActive);
              panel.hidden = !isActive;
            });
          };

          if (!select) {
            return;
          }

          select.addEventListener("change", () => {
            activateBranch(select.value);
          });

          activateBranch(select.value);
        });
      })();