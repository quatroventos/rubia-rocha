(() => {
  const header = document.querySelector(".site-header");
  const toggle = document.querySelector(".site-menu-toggle");
  const nav = document.querySelector("#site-nav");

  if (!header || !toggle || !nav) {
    return;
  }

  const desktopQuery = window.matchMedia("(min-width: 760px)");
  const OPEN_DELAY = 60;
  const CLOSE_DELAY = 240;
  let openTimer = null;
  let closeTimer = null;

  const syncHeaderOffset = () => {
    document.documentElement.style.setProperty(
      "--site-header-height",
      `${header.offsetHeight}px`,
    );
  };

  const submenuItems = () =>
    Array.from(nav.querySelectorAll(".site-nav__item.has-children"));

  const setExpanded = (item, isOpen) => {
    item.classList.toggle("is-open", isOpen);
    const btn = item.querySelector("[data-submenu-toggle]");
    if (btn) {
      btn.setAttribute("aria-expanded", String(isOpen));
    }
  };

  const closeAllSubmenus = (except = null) => {
    submenuItems().forEach((item) => {
      if (item !== except) {
        setExpanded(item, false);
      }
    });
  };

  const clearTimers = () => {
    window.clearTimeout(openTimer);
    window.clearTimeout(closeTimer);
    openTimer = null;
    closeTimer = null;
  };

  const openSubmenu = (item) => {
    clearTimers();
    closeAllSubmenus(item);
    setExpanded(item, true);
  };

  const closeSubmenu = (item) => {
    clearTimers();
    setExpanded(item, false);
  };

  const setMenuState = (isOpen) => {
    header.classList.toggle("is-nav-open", isOpen);
    document.body.classList.toggle("is-nav-locked", isOpen && !desktopQuery.matches);
    toggle.setAttribute("aria-expanded", String(isOpen));
    toggle.setAttribute("aria-label", isOpen ? "Fechar menu" : "Abrir menu");
    if (!isOpen) {
      closeAllSubmenus();
    }
    requestAnimationFrame(syncHeaderOffset);
  };

  syncHeaderOffset();

  toggle.addEventListener("click", () => {
    setMenuState(toggle.getAttribute("aria-expanded") !== "true");
  });

  nav.addEventListener("click", (event) => {
    const submenuToggle = event.target.closest("[data-submenu-toggle]");
    if (submenuToggle) {
      event.preventDefault();
      event.stopPropagation();
      const item = submenuToggle.closest(".site-nav__item");
      if (!item) {
        return;
      }

      // Desktop: hover opens/closes. Click only opens (keyboard/assistive) —
      // never toggles closed, which was fighting the hover state.
      if (desktopQuery.matches) {
        openSubmenu(item);
        return;
      }

      const willOpen = !item.classList.contains("is-open");
      if (willOpen) {
        openSubmenu(item);
      } else {
        closeSubmenu(item);
      }
      requestAnimationFrame(syncHeaderOffset);
      return;
    }

    if (event.target.closest(".site-nav__submenu-link") || event.target.closest(".site-nav__link")) {
      if (!desktopQuery.matches) {
        setMenuState(false);
      } else {
        closeAllSubmenus();
      }
    }
  });

  submenuItems().forEach((item) => {
    item.addEventListener("pointerenter", () => {
      if (!desktopQuery.matches) {
        return;
      }
      window.clearTimeout(closeTimer);
      closeTimer = null;
      window.clearTimeout(openTimer);
      openTimer = window.setTimeout(() => openSubmenu(item), OPEN_DELAY);
    });

    item.addEventListener("pointerleave", () => {
      if (!desktopQuery.matches) {
        return;
      }
      window.clearTimeout(openTimer);
      openTimer = null;
      window.clearTimeout(closeTimer);
      closeTimer = window.setTimeout(() => closeSubmenu(item), CLOSE_DELAY);
    });

    item.addEventListener("focusout", (event) => {
      if (item.contains(event.relatedTarget)) {
        return;
      }
      if (desktopQuery.matches) {
        closeSubmenu(item);
      }
    });
  });

  document.addEventListener("pointerdown", (event) => {
    if (!desktopQuery.matches) {
      return;
    }
    if (event.target.closest(".site-nav__item.has-children")) {
      return;
    }
    closeAllSubmenus();
  });

  window.addEventListener("keydown", (event) => {
    if (event.key === "Escape") {
      closeAllSubmenus();
      setMenuState(false);
    }
  });

  const onViewportChange = () => {
    clearTimers();
    closeAllSubmenus();
    if (desktopQuery.matches) {
      setMenuState(false);
    }
    syncHeaderOffset();
  };

  if (typeof desktopQuery.addEventListener === "function") {
    desktopQuery.addEventListener("change", onViewportChange);
  } else {
    desktopQuery.addListener(onViewportChange);
  }

  window.addEventListener("resize", syncHeaderOffset);
  window.addEventListener("load", syncHeaderOffset);
})();
