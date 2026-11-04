type Cleanup = () => void;

const SLIDE_MS = 700;
const SLIDE_STAGGER = 80;
const SLIDE_EVERY = 5200;

const ENTER = [
  '.hero-stage',
  '.hero > .dots',
  '.intro-photo',
  '.intro-copy > *',
  '.sheet > :not(.shots)',
  '.shot',
  '.quotes > :not(.quotes-wall)',
  '.quote-card',
  '.closing',
  '.trio img',
  '.intro-main',
  '.intro-card',
  '.fact',
  '.folio-photo',
  '.folio-lead',
  '.prose-section',
  '.prose-photo',
  '.folio-body > .btn',
  '.album > h2',
  '.album-count',
  '.album-grid button',
  '.categories > h2',
  '.faq-band > *',
  '.talk-photo',
  '.talk-copy',
  '.form-intro',
  '.form-block form',
  '.prose-block > *',
].join(',');

function reducedMotion(): boolean {
  return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

function mountEnter(root: HTMLElement): Cleanup {
  if (reducedMotion() || !('IntersectionObserver' in window)) {
    return () => undefined;
  }

  const seen = new Set<HTMLElement>();
  const observer = new IntersectionObserver((entries) => {
    entries
      .filter((entry) => entry.isIntersecting)
      .sort((a, b) => a.boundingClientRect.top - b.boundingClientRect.top || a.boundingClientRect.left - b.boundingClientRect.left)
      .forEach((entry, index) => {
        const node = entry.target as HTMLElement;
        node.style.animationDelay = `${Math.min(index, 7) * 70}ms`;
        node.classList.add('is-in');
        observer.unobserve(node);
      });
  }, { threshold: 0 });

  root.querySelectorAll<HTMLElement>(ENTER).forEach((node) => {
    if (node.classList.contains('visually-hidden') || node.closest('.hp, .lightbox') || node.parentElement?.closest(ENTER)) {
      return;
    }
    seen.add(node);
    node.classList.add('enter');
    observer.observe(node);
  });

  window.requestAnimationFrame(() => {
    seen.forEach((node) => {
      const rect = node.getBoundingClientRect();
      if (!node.classList.contains('is-in') && rect.bottom > 0 && rect.top < window.innerHeight) {
        node.classList.add('is-in');
        observer.unobserve(node);
      }
    });
  });

  return () => {
    observer.disconnect();
    seen.forEach((node) => {
      node.classList.remove('enter', 'is-in');
      node.style.animationDelay = '';
    });
  };
}

function mountHeader(root: HTMLElement): Cleanup {
  const bar = root.querySelector<HTMLElement>('.bar');
  const toggle = root.querySelector<HTMLButtonElement>('.nav-toggle');
  const menu = root.querySelector<HTMLElement>('.menu');
  const cleanups: Cleanup[] = [];

  if (bar) {
    let scrolled = false;
    const onScroll = () => {
      scrolled = scrolled ? window.scrollY > 4 : window.scrollY > 32;
      bar.classList.toggle('is-scrolled', scrolled);
    };
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
    cleanups.push(() => window.removeEventListener('scroll', onScroll));
  }

  if (toggle && menu) {
    const setOpen = (open: boolean) => {
      menu.classList.toggle('is-open', open);
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    };
    const onClick = () => setOpen(!menu.classList.contains('is-open'));
    const onKey = (event: KeyboardEvent) => {
      if (event.key === 'Escape' && menu.classList.contains('is-open')) {
        setOpen(false);
        toggle.focus();
      }
    };
    const onPointer = (event: PointerEvent) => {
      const target = event.target as Node;
      if (menu.classList.contains('is-open') && !menu.contains(target) && !toggle.contains(target)) {
        setOpen(false);
      }
    };
    toggle.addEventListener('click', onClick);
    window.addEventListener('keydown', onKey);
    document.addEventListener('pointerdown', onPointer);
    cleanups.push(() => {
      toggle.removeEventListener('click', onClick);
      window.removeEventListener('keydown', onKey);
      document.removeEventListener('pointerdown', onPointer);
    });
  }

  return () => cleanups.forEach((cleanup) => cleanup());
}

function mountCarousel(section: HTMLElement): Cleanup {
  const stage = section.querySelector<HTMLElement>('.hero-stage');
  const photos = stage ? [...stage.querySelectorAll<HTMLImageElement>('.hero-photo')] : [];
  const count = photos.length;
  if (!stage || count <= 3) {
    return () => undefined;
  }

  const dots = [...section.querySelectorAll<HTMLButtonElement>('[data-dot]')];
  const toggle = section.querySelector<HTMLButtonElement>('[data-toggle]');
  const alts = photos.map((photo) => photo.alt);
  const timers = new Set<number>();
  let index = 0;
  let busy = false;
  let paused = false;
  let stopped = false;
  let dragX: number | null = null;
  let interval = 0;

  const later = (callback: () => void, ms: number) => {
    const id = window.setTimeout(() => {
      timers.delete(id);
      callback();
    }, ms);
    timers.add(id);
  };

  const frame = (start: number) => [0, 1, 2].map((slot) => (start + slot) % count);

  const place = (photo: HTMLImageElement, slot: number, delay: number) => {
    const offstage = slot < 0 || slot > 2;
    photo.style.transitionDelay = `${delay}ms`;
    photo.dataset.slot = String(slot);
    photo.alt = offstage ? '' : alts[photos.indexOf(photo)];
    if (offstage) {
      photo.setAttribute('aria-hidden', 'true');
    } else {
      photo.removeAttribute('aria-hidden');
      photo.loading = 'eager';
    }
  };

  const enterFrom = (photo: HTMLImageElement, start: number, slot: number, delay: number) => {
    photo.classList.add('is-enter');
    place(photo, start, 0);
    void photo.offsetWidth;
    window.requestAnimationFrame(() => {
      photo.classList.remove('is-enter');
      place(photo, slot, delay);
    });
  };

  const show = (next: number, forward: boolean) => {
    const target = (next + count) % count;
    if (target === index || busy) {
      return;
    }

    if (reducedMotion()) {
      photos.forEach((photo) => place(photo, 3, 0));
      frame(target).forEach((photoIndex, slot) => place(photos[photoIndex], slot, 0));
    } else {
      const previous = frame(index);
      const upcoming = frame(target);
      const steps = (target - index + count) % count;
      const adjacent = steps === 1 || steps === count - 1;
      const exitSlot = forward ? -1 : 3;
      const settled = new Set<number>();

      previous.forEach((photoIndex, oldSlot) => {
        const photo = photos[photoIndex];
        const newSlot = upcoming.indexOf(photoIndex);
        const delay = (forward ? oldSlot : 2 - oldSlot) * SLIDE_STAGGER;
        const wraps = newSlot !== -1 && !adjacent && (forward ? newSlot > oldSlot : newSlot < oldSlot);

        if (wraps) {
          const ghost = photo.cloneNode() as HTMLImageElement;
          ghost.removeAttribute('fetchpriority');
          stage.appendChild(ghost);
          window.requestAnimationFrame(() => {
            ghost.style.transitionDelay = `${delay}ms`;
            ghost.dataset.slot = String(exitSlot);
            ghost.alt = '';
            ghost.setAttribute('aria-hidden', 'true');
          });
          later(() => ghost.remove(), SLIDE_MS + SLIDE_STAGGER * 2 + 40);

          return;
        }

        settled.add(photoIndex);
        place(photo, newSlot === -1 ? exitSlot : newSlot, delay);
      });

      upcoming.forEach((photoIndex, slot) => {
        if (settled.has(photoIndex)) {
          return;
        }
        enterFrom(photos[photoIndex], forward ? 3 : -1, slot, (forward ? slot : 2 - slot) * SLIDE_STAGGER);
      });

      busy = true;
      later(() => {
        busy = false;
      }, SLIDE_MS + SLIDE_STAGGER * 2 + 40);
    }

    index = target;
    dots.forEach((dot, dotIndex) => {
      if (dotIndex === index) {
        dot.setAttribute('aria-current', 'true');
      } else {
        dot.removeAttribute('aria-current');
      }
    });
  };

  const goTo = (next: number) => {
    const steps = (next - index + count) % count;
    show(next, steps <= count / 2);
  };

  const tick = () => {
    if (!paused && !stopped && document.visibilityState !== 'hidden' && !reducedMotion()) {
      show(index + 1, true);
    }
  };

  interval = window.setInterval(tick, SLIDE_EVERY);

  const onDot = (event: Event) => {
    const dot = (event.currentTarget as HTMLElement).dataset.dot;
    if (dot !== undefined) {
      goTo(Number(dot));
    }
  };
  const onToggle = () => {
    stopped = !stopped;
    toggle?.setAttribute('aria-pressed', stopped ? 'true' : 'false');
    toggle?.setAttribute('aria-label', stopped ? 'Retomar a troca automática' : 'Pausar a troca automática');
  };
  const pause = () => {
    paused = true;
  };
  const resume = () => {
    paused = false;
  };
  const onPointerDown = (event: PointerEvent) => {
    dragX = event.clientX;
    stage.setPointerCapture(event.pointerId);
  };
  const onPointerUp = (event: PointerEvent) => {
    if (dragX === null) {
      return;
    }
    const delta = event.clientX - dragX;
    dragX = null;
    if (Math.abs(delta) >= 48) {
      show(index + (delta < 0 ? 1 : -1), delta < 0);
    }
  };
  const onPointerCancel = () => {
    dragX = null;
  };

  dots.forEach((dot) => dot.addEventListener('click', onDot));
  toggle?.addEventListener('click', onToggle);
  section.addEventListener('mouseenter', pause);
  section.addEventListener('mouseleave', resume);
  section.addEventListener('focusin', pause);
  section.addEventListener('focusout', resume);
  stage.addEventListener('pointerdown', onPointerDown);
  stage.addEventListener('pointerup', onPointerUp);
  stage.addEventListener('pointercancel', onPointerCancel);

  return () => {
    window.clearInterval(interval);
    timers.forEach((id) => window.clearTimeout(id));
    dots.forEach((dot) => dot.removeEventListener('click', onDot));
    toggle?.removeEventListener('click', onToggle);
    section.removeEventListener('mouseenter', pause);
    section.removeEventListener('mouseleave', resume);
    section.removeEventListener('focusin', pause);
    section.removeEventListener('focusout', resume);
    stage.removeEventListener('pointerdown', onPointerDown);
    stage.removeEventListener('pointerup', onPointerUp);
    stage.removeEventListener('pointercancel', onPointerCancel);
  };
}

function mountFacts(section: HTMLElement): Cleanup {
  const cards = [...section.querySelectorAll<HTMLElement>('.fact-card')];
  const dots = [...section.querySelectorAll<HTMLButtonElement>('[data-dot]')];
  const next = section.querySelector<HTMLButtonElement>('[data-next]');
  const stack = section.querySelector<HTMLElement>('.fact-stack');
  if (cards.length < 2) {
    return () => undefined;
  }

  let index = 0;
  const show = (target: number) => {
    index = (target + cards.length) % cards.length;
    stack?.setAttribute('aria-live', 'polite');
    cards.forEach((card, cardIndex) => {
      const current = cardIndex === index;
      card.classList.toggle('is-current', current);
      card.toggleAttribute('inert', !current);
      if (current) {
        card.removeAttribute('aria-hidden');
      } else {
        card.setAttribute('aria-hidden', 'true');
      }
    });
    dots.forEach((dot, dotIndex) => {
      if (dotIndex === index) {
        dot.setAttribute('aria-current', 'true');
      } else {
        dot.removeAttribute('aria-current');
      }
    });
  };
  const onDot = (event: Event) => show(Number((event.currentTarget as HTMLElement).dataset.dot));
  const onNext = () => show(index + 1);

  dots.forEach((dot) => dot.addEventListener('click', onDot));
  next?.addEventListener('click', onNext);

  return () => {
    dots.forEach((dot) => dot.removeEventListener('click', onDot));
    next?.removeEventListener('click', onNext);
  };
}

function mountAlbum(section: HTMLElement): Cleanup {
  const dialog = section.querySelector<HTMLDialogElement>('dialog');
  const buttons = [...section.querySelectorAll<HTMLButtonElement>('.album-grid button')];
  if (!dialog || buttons.length === 0 || typeof dialog.showModal !== 'function') {
    return () => undefined;
  }

  const title = dialog.getAttribute('aria-label') ?? '';
  const image = document.createElement('img');
  image.alt = title;
  dialog.prepend(image);
  let index = 0;

  const show = (target: number) => {
    index = (target + buttons.length) % buttons.length;
    const thumb = buttons[index].querySelector('img');
    image.src = buttons[index].dataset.full ?? '';
    image.alt = thumb?.alt || title;
    if (thumb?.width && thumb.height) {
      image.width = Number(thumb.getAttribute('width')) || thumb.width;
      image.height = Number(thumb.getAttribute('height')) || thumb.height;
    }
    if (!dialog.open) {
      dialog.showModal();
    }
  };

  const onOpen = (event: Event) => show(Number((event.currentTarget as HTMLElement).dataset.index));
  const onDialogClick = (event: MouseEvent) => {
    const target = event.target as HTMLElement;
    if (target === dialog || target.closest('[data-close]')) {
      dialog.close();
      return;
    }
    const step = target.closest<HTMLElement>('[data-step]')?.dataset.step;
    if (step) {
      show(index + Number(step));
    }
  };
  const onKey = (event: KeyboardEvent) => {
    if (event.key === 'ArrowRight') {
      show(index + 1);
    }
    if (event.key === 'ArrowLeft') {
      show(index - 1);
    }
  };
  const onClose = () => {
    buttons[index]?.focus({ preventScroll: true });
  };

  buttons.forEach((button) => button.addEventListener('click', onOpen));
  dialog.addEventListener('click', onDialogClick);
  dialog.addEventListener('keydown', onKey);
  dialog.addEventListener('close', onClose);

  return () => {
    buttons.forEach((button) => button.removeEventListener('click', onOpen));
    dialog.removeEventListener('click', onDialogClick);
    dialog.removeEventListener('keydown', onKey);
    dialog.removeEventListener('close', onClose);
    image.remove();
  };
}

const MESSAGES: Record<string, string> = {
  nome: 'Me conta o seu nome.',
  email: 'Preciso de um e-mail para te responder.',
  mensagem: 'Escreve um pouco sobre o momento que você quer registrar.',
};

const EMAIL_INVALID = 'Esse e-mail parece incompleto.';

type Control = HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement;

function mountContact(form: HTMLFormElement): Cleanup {
  const live = form.querySelector<HTMLElement>('.form-live');
  const submit = form.querySelector<HTMLButtonElement>('button[type="submit"]');
  const submitLabel = submit?.querySelector<HTMLElement>('[data-label]');
  const idleLabel = submitLabel?.textContent ?? '';
  const controls = () => [...form.querySelectorAll<Control>('.fields [name]')];

  const status = (message: string, error: boolean) => {
    if (!live) {
      return;
    }
    live.replaceChildren();
    if (message) {
      const node = document.createElement('p');
      node.className = error ? 'form-status is-error' : 'form-status';
      node.textContent = message;
      live.appendChild(node);
    }
  };

  const clearErrors = () => {
    controls().forEach((control) => {
      control.removeAttribute('aria-invalid');
      control.removeAttribute('aria-describedby');
    });
    form.querySelectorAll('.field-error').forEach((node) => node.remove());
  };

  const reject = (errors: Record<string, string>) => {
    clearErrors();
    let first: Control | null = null;
    controls().forEach((control) => {
      const message = errors[control.name];
      if (!message) {
        return;
      }
      const error = document.createElement('span');
      error.className = 'field-error';
      error.id = `erro-${control.name}`;
      error.textContent = message;
      control.setAttribute('aria-invalid', 'true');
      control.setAttribute('aria-describedby', error.id);
      control.closest('label')?.appendChild(error);
      first ??= control;
    });
    status(form.dataset.invalid ?? '', true);
    (first as Control | null)?.focus();
  };

  const validate = (): Record<string, string> => {
    const errors: Record<string, string> = {};
    controls().forEach((control) => {
      const value = control instanceof HTMLInputElement && control.type === 'checkbox' ? (control.checked ? '1' : '') : control.value.trim();
      const label = control.dataset.label ?? control.name;
      if (control.required && value === '') {
        errors[control.name] = MESSAGES[control.name] ?? `Preencha: ${label}.`;
      } else if (control instanceof HTMLInputElement && control.type === 'email' && value !== '' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
        errors[control.name] = EMAIL_INVALID;
      }
    });

    return errors;
  };

  const setBusy = (busy: boolean) => {
    form.toggleAttribute('aria-busy', busy);
    if (submit) {
      submit.disabled = busy;
    }
    if (submitLabel) {
      submitLabel.textContent = busy ? 'Enviando' : idleLabel;
    }
  };

  const onSubmit = async (event: SubmitEvent) => {
    event.preventDefault();
    const local = validate();
    if (Object.keys(local).length > 0) {
      reject(local);
      return;
    }
    clearErrors();
    status('', false);
    setBusy(true);
    try {
      const response = await fetch(form.action, {
        method: 'POST',
        body: new FormData(form),
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      });
      if (response.ok) {
        status(form.dataset.sent ?? '', false);
        form.reset();
        return;
      }
      if (response.status === 422) {
        const body = (await response.json()) as { errors?: Record<string, string[]> };
        const server: Record<string, string> = {};
        Object.entries(body.errors ?? {}).forEach(([name, messages]) => {
          if (messages[0]) {
            server[name] = messages[0];
          }
        });
        if (Object.keys(server).length > 0) {
          reject(server);
          return;
        }
      }
      status(form.dataset.failed ?? '', true);
    } catch {
      status(form.dataset.failed ?? '', true);
    } finally {
      setBusy(false);
    }
  };

  if (new URLSearchParams(window.location.search).get('status') === 'enviado') {
    status(form.dataset.sent ?? '', false);
  }

  const handler = (event: Event) => void onSubmit(event as SubmitEvent);
  form.addEventListener('submit', handler);

  return () => form.removeEventListener('submit', handler);
}

export function mountBehaviors(root: HTMLElement = document.body, { enter = true }: { enter?: boolean } = {}): Cleanup {
  const cleanups: Cleanup[] = [mountHeader(root)];
  const main = root.querySelector<HTMLElement>('#conteudo');

  root.querySelectorAll<HTMLElement>('[data-carousel]').forEach((section) => cleanups.push(mountCarousel(section)));
  root.querySelectorAll<HTMLElement>('[data-facts]').forEach((section) => cleanups.push(mountFacts(section)));
  root.querySelectorAll<HTMLElement>('[data-album]').forEach((section) => cleanups.push(mountAlbum(section)));
  root.querySelectorAll<HTMLFormElement>('form[data-contact]').forEach((form) => cleanups.push(mountContact(form)));
  if (main && enter) {
    cleanups.push(mountEnter(main));
  }

  return () => cleanups.forEach((cleanup) => cleanup());
}
