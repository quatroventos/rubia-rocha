const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

function reveal() {
  const nodes = document.querySelectorAll('.prose-panel.is-reveal, .plan-tier, .plan-base, .is-word-reveal, .loop');
  if (!nodes.length || !('IntersectionObserver' in window)) {
    nodes.forEach((node) => node.classList.add('is-in'));
    return;
  }

  const observer = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      if (!entry.isIntersecting) {
        return;
      }
      entry.target.classList.add('is-in');
      observer.unobserve(entry.target);
    });
  }, { threshold: 0.4 });

  nodes.forEach((node) => observer.observe(node));
}

function header() {
  const bar = document.querySelector('.site-header');
  const toggle = document.querySelector('.nav-toggle');
  const mobile = document.querySelector('.mobile-nav');
  const mega = document.querySelector('.mega');

  if (bar) {
    const onScroll = () => bar.classList.toggle('is-solid', window.scrollY > 24 || mobile?.classList.contains('is-open') || mega?.classList.contains('is-open'));
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
  }

  const openMega = (href) => {
    if (!mega) {
      return;
    }
    document.querySelectorAll('.main-nav > ul > li').forEach((item) => {
      const link = item.querySelector(':scope > a');
      item.classList.toggle('is-open', Boolean(href) && link?.getAttribute('href') === href);
    });
    mega.classList.toggle('is-open', Boolean(href));
    mega.querySelectorAll('.mega-col').forEach((col) => {
      const hub = col.querySelector('.mega-hub');
      col.classList.toggle('is-active', hub?.getAttribute('href') === href);
    });
    mega.setAttribute('aria-hidden', href ? 'false' : 'true');
    bar?.classList.toggle('is-solid', Boolean(href) || window.scrollY > 24);
  };

  document.querySelectorAll('.main-nav > ul > li').forEach((item) => {
    const link = item.querySelector(':scope > a');
    const href = link?.getAttribute('href');
    const hasCaret = item.querySelector('.caret');
    item.addEventListener('mouseenter', () => openMega(hasCaret ? href : null));
    item.addEventListener('focusin', () => openMega(hasCaret ? href : null));
  });

  bar?.addEventListener('mouseleave', () => openMega(null));
  bar?.addEventListener('focusout', (event) => {
    if (!bar.contains(event.relatedTarget)) {
      openMega(null);
    }
  });

  const setMobile = (open) => {
    if (!toggle || !mobile) {
      return;
    }
    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    mobile.classList.toggle('is-open', open);
    mobile.setAttribute('aria-hidden', open ? 'false' : 'true');
    document.body.style.overflow = open ? 'hidden' : '';
    mobile.querySelectorAll('a, button').forEach((node) => {
      if (open) {
        node.removeAttribute('tabindex');
      } else {
        node.tabIndex = -1;
      }
    });
    bar?.classList.toggle('is-solid', open || window.scrollY > 24);
  };

  toggle?.addEventListener('click', () => setMobile(!mobile.classList.contains('is-open')));

  mobile?.querySelectorAll('.row-link > button').forEach((button) => {
    button.addEventListener('click', () => {
      const expanded = button.getAttribute('aria-expanded') === 'true';
      const panel = document.getElementById(button.getAttribute('aria-controls') || '');
      button.setAttribute('aria-expanded', expanded ? 'false' : 'true');
      if (panel) {
        panel.hidden = expanded;
      }
    });
  });

  const langMenu = document.querySelector('.lang-float .lang-menu');

  document.addEventListener('click', (event) => {
    if (langMenu?.open && !langMenu.contains(event.target)) {
      langMenu.open = false;
    }
  });

  document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') {
      return;
    }
    if (langMenu?.open) {
      langMenu.open = false;
      langMenu.querySelector('summary')?.focus();
    }
    openMega(null);
    setMobile(false);
  });
}

const CONSENT_COOKIE = 'qv_consent';
let trackingActive = false;

function readConsent() {
  const match = document.cookie.match(new RegExp(`(?:^|; )${CONSENT_COOKIE}=([^;]+)`));

  return match ? decodeURIComponent(match[1]) : null;
}

function writeConsent(value) {
  const secure = location.protocol === 'https:' ? '; secure' : '';
  document.cookie = `${CONSENT_COOKIE}=${value}; path=/; max-age=${60 * 60 * 24 * 180}; samesite=lax${secure}`;
}

// Scripts inseridos via template/innerHTML não executam: cada <script> é recriado.
function revive(script) {
  const fresh = document.createElement('script');
  for (const attribute of script.attributes) {
    if (attribute.name === 'type' && attribute.value === 'text/plain') {
      continue;
    }
    if (attribute.name === 'data-consent') {
      continue;
    }
    fresh.setAttribute(attribute.name === 'data-src' ? 'src' : attribute.name, attribute.value);
  }
  fresh.text = script.text;

  return fresh;
}

function activateTracking() {
  if (trackingActive) {
    return;
  }
  trackingActive = true;

  document.querySelectorAll('script[type="text/plain"][data-consent]').forEach((script) => script.replaceWith(revive(script)));
  document.querySelectorAll('template[data-consent]').forEach((template) => {
    const fragment = template.content.cloneNode(true);
    fragment.querySelectorAll('script').forEach((script) => script.replaceWith(revive(script)));
    template.replaceWith(fragment);
  });
}

function forgetTracking() {
  try {
    ['qv_vid', 'rba_vid'].forEach((key) => localStorage.removeItem(key));
    ['qv_sid', 'qv_sid_at', 'rba_sid', 'rba_sid_at'].forEach((key) => sessionStorage.removeItem(key));
  } catch {
    // Armazenamento bloqueado pelo navegador: nada a limpar.
  }
}

function consent() {
  const notice = document.querySelector('[data-cookie-notice]');
  const current = readConsent();

  if (current === 'all') {
    activateTracking();
  } else if (current === null && notice) {
    notice.hidden = false;
  }

  notice?.querySelectorAll('[data-cookie-choice]').forEach((button) => {
    button.addEventListener('click', () => {
      const choice = button.dataset.cookieChoice === 'all' ? 'all' : 'essential';
      writeConsent(choice);
      notice.hidden = true;
      if (choice === 'all') {
        activateTracking();
      } else {
        forgetTracking();
      }
    });
  });

  document.querySelectorAll('[data-cookie-prefs]').forEach((button) => {
    button.addEventListener('click', () => {
      if (!notice) {
        return;
      }
      notice.hidden = false;
      notice.querySelector('[data-cookie-choice]')?.focus();
    });
  });
}

function lens() {
  document.querySelectorAll('.hero.is-lens').forEach((hero) => {
    hero.addEventListener('pointermove', (event) => {
      const box = hero.getBoundingClientRect();
      hero.style.setProperty('--lens-x', `${event.clientX - box.left}px`);
      hero.style.setProperty('--lens-y', `${event.clientY - box.top}px`);
    });
  });
}

function tilt() {
  document.querySelectorAll('.case-card.is-tilt').forEach((card) => {
    card.addEventListener('pointermove', (event) => {
      if (event.pointerType !== 'mouse') {
        return;
      }
      const box = card.getBoundingClientRect();
      card.style.setProperty('--tilt-x', ((event.clientX - box.left) / box.width - 0.5).toFixed(3));
      card.style.setProperty('--tilt-y', ((event.clientY - box.top) / box.height - 0.5).toFixed(3));
    });
    card.addEventListener('pointerleave', () => {
      card.style.removeProperty('--tilt-x');
      card.style.removeProperty('--tilt-y');
    });
  });
}

function rotating() {
  if (reduced) {
    return;
  }

  document.querySelectorAll('.rot').forEach((rot) => {
    const items = [...rot.children];
    if (items.length < 2) {
      return;
    }
    let active = Math.max(0, items.findIndex((item) => item.classList.contains('is-active')));
    window.setInterval(() => {
      const past = active;
      active = (active + 1) % items.length;
      items.forEach((item, index) => {
        item.classList.toggle('is-active', index === active);
        item.classList.toggle('is-past', index === past);
      });
    }, 2800);
  });
}

function billing(root) {
  root.querySelectorAll('.plan-billing:not(.is-tabs)').forEach((group) => {
    group.addEventListener('click', (event) => {
      const button = event.target.closest('[data-billing]');
      if (!button || !group.contains(button)) {
        return;
      }
      const mode = button.dataset.billing;
      const scope = group.closest('section') || root;
      scope.querySelectorAll('[data-billing]').forEach((node) => {
        if (node.tagName === 'BUTTON') {
          node.setAttribute('aria-pressed', node.dataset.billing === mode ? 'true' : 'false');
          return;
        }
        node.hidden = node.dataset.billing !== mode;
      });
      const live = group.parentElement?.querySelector('[aria-live]') || group.nextElementSibling;
      if (live) {
        live.textContent = mode === 'year' ? 'Preços em cobrança anual' : 'Preços em cobrança mensal';
      }
    });
  });
}

function panels(root) {
  root.querySelectorAll('.plan-billing.is-tabs').forEach((group) => {
    group.addEventListener('click', (event) => {
      const button = event.target.closest('[data-panel]');
      if (!button || !group.contains(button)) {
        return;
      }
      const id = button.dataset.panel;
      const scope = group.closest('section') || root;
      group.querySelectorAll('[data-panel]').forEach((node) => {
        node.setAttribute('aria-pressed', node.dataset.panel === id ? 'true' : 'false');
      });
      scope.querySelectorAll('[data-plan-panel]').forEach((panel) => {
        panel.hidden = panel.dataset.planPanel !== id;
      });
    });
  });
}

function plans() {
  billing(document);
  panels(document);
}

function cases() {
  const grid = document.querySelector('.cases-grid');
  const tools = document.querySelector('.cases-band-tools');
  if (!grid || !tools) {
    return;
  }

  const cards = [...grid.querySelectorAll('.case-card')];
  const empty = grid.nextElementSibling?.classList.contains('cases-empty') ? grid.nextElementSibling : null;
  const input = tools.querySelector('input[type="search"]');
  const buttons = [...tools.querySelectorAll('[data-category]')];
  let category = '';

  const normalize = (value) => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim();

  const apply = () => {
    const term = normalize(input?.value || '');
    let shown = 0;
    cards.forEach((card) => {
      const matchesCategory = !category || card.dataset.category === category;
      const matchesTerm = !term || normalize(card.dataset.search || '').includes(term);
      const visible = matchesCategory && matchesTerm;
      card.hidden = !visible;
      if (visible) {
        shown += 1;
      }
    });
    if (empty) {
      empty.hidden = shown > 0;
    }
    buttons.forEach((button) => {
      const id = button.dataset.category || '';
      const count = cards.filter((card) => {
        const matchesCategory = !id || card.dataset.category === id;
        const matchesTerm = !term || normalize(card.dataset.search || '').includes(term);
        return matchesCategory && matchesTerm;
      }).length;
      const badge = button.querySelector('.cases-filter-n');
      if (badge) {
        badge.textContent = String(count);
      }
      button.setAttribute('aria-pressed', id === category ? 'true' : 'false');
    });
  };

  buttons.forEach((button) => {
    button.addEventListener('click', () => {
      category = button.dataset.category || '';
      apply();
    });
  });
  input?.addEventListener('input', apply);
  empty?.querySelector('[data-cases-clear]')?.addEventListener('click', () => {
    category = '';
    if (input) {
      input.value = '';
    }
    apply();
  });
}

function lightbox() {
  document.querySelectorAll('.case-prints').forEach((gallery) => {
    const figures = [...gallery.querySelectorAll('figure')];
    if (!figures.length) {
      return;
    }

    const dialog = document.createElement('div');
    dialog.className = 'case-lightbox';
    dialog.hidden = true;
    dialog.setAttribute('role', 'dialog');
    dialog.setAttribute('aria-modal', 'true');
    dialog.innerHTML = '<figure><img alt=""><figcaption></figcaption></figure><div class="case-lightbox-nav"><button type="button" data-prev>Anterior</button><span data-count></span><button type="button" data-next>Próximo</button></div><button class="case-lightbox-close" type="button">Fechar</button>';
    gallery.after(dialog);

    const image = dialog.querySelector('img');
    const caption = dialog.querySelector('figcaption');
    const count = dialog.querySelector('[data-count]');
    let index = 0;

    const show = (next) => {
      index = (next + figures.length) % figures.length;
      const source = figures[index].querySelector('img');
      const label = figures[index].querySelector('figcaption');
      image.src = source?.src || '';
      image.alt = source?.alt || '';
      caption.textContent = label?.textContent || '';
      caption.hidden = !caption.textContent;
      count.textContent = `${index + 1} / ${figures.length}`;
      dialog.hidden = false;
      document.body.style.overflow = 'hidden';
    };

    const close = () => {
      dialog.hidden = true;
      document.body.style.overflow = '';
    };

    figures.forEach((figure, figureIndex) => {
      figure.querySelector('button')?.addEventListener('click', () => show(figureIndex));
    });
    dialog.querySelector('[data-prev]')?.addEventListener('click', (event) => {
      event.stopPropagation();
      show(index - 1);
    });
    dialog.querySelector('[data-next]')?.addEventListener('click', (event) => {
      event.stopPropagation();
      show(index + 1);
    });
    dialog.querySelector('.case-lightbox-close')?.addEventListener('click', (event) => {
      event.stopPropagation();
      close();
    });
    dialog.addEventListener('click', close);
    dialog.querySelector('figure')?.addEventListener('click', (event) => event.stopPropagation());
    document.addEventListener('keydown', (event) => {
      if (dialog.hidden) {
        return;
      }
      if (event.key === 'Escape') {
        close();
      }
      if (event.key === 'ArrowRight') {
        show(index + 1);
      }
      if (event.key === 'ArrowLeft') {
        show(index - 1);
      }
    });
  });
}

function slots(mask) {
  return mask.split('#').length - 1;
}

function formatPhone(raw, masks) {
  const max = slots(masks[masks.length - 1]);
  const digits = raw.replace(/\D/g, '').slice(0, max);
  if (!digits) {
    return '';
  }
  const mask = masks.find((candidate) => slots(candidate) >= digits.length) || masks[masks.length - 1];
  let out = '';
  let index = 0;
  for (const char of mask) {
    if (index >= digits.length) {
      break;
    }
    out += char === '#' ? digits[index++] : char;
  }
  return out;
}

function phoneComplete(digits, masks) {
  return masks.some((mask) => slots(mask) === digits.length);
}

function fieldError(field, message) {
  let node = field.parentElement?.querySelector('.field-error');
  if (!message) {
    node?.remove();
    field.removeAttribute('aria-invalid');
    return;
  }
  field.setAttribute('aria-invalid', 'true');
  if (!node) {
    node = document.createElement('span');
    node.className = 'field-error';
    field.parentElement?.append(node);
  }
  node.textContent = message;
}

// Eventos para o GTM. Sem nome, telefone ou e-mail: só o tipo de conversão e onde aconteceu.
function pushEvent(name, params = {}) {
  window.dataLayer = window.dataLayer || [];
  window.dataLayer.push({ event: name, page_path: window.location.pathname, ...params });
}

function whatsappLocation(link) {
  if (link.classList.contains('wa-float')) {
    return 'flutuante';
  }
  if (link.closest('.lead-back')) {
    return 'lead_obrigado';
  }
  if (link.closest('.form-status')) {
    return 'erro_formulario';
  }
  if (link.closest('header, .mega, .mobile-nav')) {
    return 'cabecalho';
  }
  if (link.closest('footer')) {
    return 'rodape';
  }
  return 'conteudo';
}

function whatsappClicks() {
  document.addEventListener('click', (event) => {
    const link = event.target instanceof Element ? event.target.closest('a[href*="wa.me/"], a[href*="api.whatsapp.com/"], a[href*="whatsapp.com/send"]') : null;
    if (link) {
      pushEvent('whatsapp_click', { link_location: whatsappLocation(link) });
    }
  });
}

function leadForms() {
  document.querySelectorAll('.lead-form').forEach((form) => {
    const card = form.closest('.lead-card');
    const select = form.querySelector('.phone-country select');
    const tel = form.querySelector('input[type="tel"]');
    const statusSlot = form.querySelector('.form-status-slot');
    const button = form.querySelector('button[type="submit"]');

    const masksOf = () => (select?.selectedOptions[0]?.dataset.masks || '(##) #####-####').split('|');

    tel?.addEventListener('input', () => {
      tel.value = formatPhone(tel.value, masksOf());
      fieldError(tel, '');
    });
    select?.addEventListener('change', () => {
      if (tel) {
        tel.value = formatPhone(tel.value, masksOf());
      }
    });

    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      if (!card || !button) {
        return;
      }
      const name = form.querySelector('input[name="nome"]');
      const digits = (tel?.value || '').replace(/\D/g, '');
      fieldError(tel, phoneComplete(digits, masksOf()) ? '' : (form.dataset.phoneInvalid || 'Informe o DDD e o número completo.'));
      if (!name?.value.trim() || !phoneComplete(digits, masksOf())) {
        return;
      }

      const dial = select?.selectedOptions[0]?.dataset.dial || '55';
      const whatsapp = `+${dial} ${tel.value}`;
      button.disabled = true;
      const label = button.textContent;
      button.textContent = form.dataset.sending || 'Enviando';

      const showStatus = (kind, text, withWhatsApp = false) => {
        if (!statusSlot) {
          return;
        }
        statusSlot.innerHTML = '';
        if (!text) {
          return;
        }
        statusSlot.append(formStatus(form, kind, text, withWhatsApp));
      };

      try {
        const response = await fetch('/api/contato/lead', {
          method: 'POST',
          headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
          body: JSON.stringify({
            nome: name.value.trim(),
            whatsapp,
            pagina: window.location.pathname,
            empresa_site: form.querySelector('input[name="empresa_site"]')?.value || '',
          }),
        });
        const payload = await response.json();
        if (!response.ok) {
          const errors = payload.errors || {};
          fieldError(name, errors.nome?.[0] || '');
          fieldError(tel, errors.whatsapp?.[0] || '');
          const invalid = response.status === 422 && payload.status !== 'erro';
          showStatus('error', invalid ? (form.dataset.invalid || 'Revise os campos destacados.') : (form.dataset.error || 'Não foi possível enviar agora. Tente o WhatsApp.'), !invalid);
          return;
        }

        const first = name.value.trim().split(/\s+/)[0];
        const title = card.querySelector('.lead-back .lead-form-title');
        const text = card.querySelector('.lead-back-text');
        const link = card.querySelector('.lead-back a');
        if (title) {
          const template = title.dataset.template || title.dataset.fallback || '';
          title.textContent = template.replace('{name}', first);
          title.focus({ preventScroll: true });
        }
        if (text) {
          const template = text.dataset.template || text.dataset.fallback || '';
          text.textContent = template.replace('{phone}', whatsapp);
        }
        if (link && card.dataset.whatsapp) {
          const template = link.dataset.waTemplate || 'Olá, sou {name}. Acabei de enviar meu interesse pelo site.';
          const url = new URL(card.dataset.whatsapp);
          url.searchParams.set('text', template.replace('{name}', first));
          link.href = url.toString();
        }
        card.classList.add('is-sent');
        pushEvent('generate_lead', { form_type: 'lead_rapido' });
        form.closest('.lead-front')?.setAttribute('inert', '');
        card.querySelector('.lead-back')?.removeAttribute('inert');
        showStatus('', '');
        form.reset();
      } catch {
        showStatus('error', form.dataset.error || 'Não foi possível enviar agora. Tente o WhatsApp.', true);
      } finally {
        button.disabled = false;
        button.textContent = label;
      }
    });
  });
}

function whatsappHref(form) {
  return form.closest('[data-whatsapp]')?.dataset.whatsapp || form.dataset.whatsapp || document.querySelector('a.wa-float')?.href || '';
}

function formStatus(form, kind, text, withWhatsApp) {
  const paragraph = document.createElement('p');
  paragraph.className = `form-status is-${kind}`;
  paragraph.textContent = text;
  const href = withWhatsApp ? whatsappHref(form) : '';
  if (href) {
    const link = document.createElement('a');
    link.href = href;
    link.target = '_blank';
    link.rel = 'noreferrer';
    link.textContent = form.dataset.waLabel || 'Falar pelo WhatsApp agora';
    paragraph.append(' ', link);
  }
  return paragraph;
}

function contactForms() {
  document.querySelectorAll('form.site-form').forEach((form) => {
    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      const button = form.querySelector('button[type="submit"]');
      const slot = form.querySelector('.form-status-slot');
      const data = Object.fromEntries(new FormData(form).entries());
      if (button) {
        button.disabled = true;
      }
      const show = (kind, text, withWhatsApp = false) => {
        if (!slot) {
          return;
        }
        slot.innerHTML = '';
        slot.append(formStatus(form, kind, text, withWhatsApp));
      };
      try {
        const response = await fetch(form.dataset.endpoint || '/api/contato', {
          method: 'POST',
          headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
          body: JSON.stringify(data),
        });
        const payload = await response.json();
        if (!response.ok) {
          if (response.status === 422 && payload.status !== 'erro') {
            show('error', form.dataset.invalid || 'Revise os campos destacados.');
          } else {
            show('error', form.dataset.error || 'Não foi possível enviar agora. Tente o WhatsApp.', true);
          }
          return;
        }
        show('ok', form.dataset.sent || 'Mensagem enviada. Retornamos em breve.');
        if (payload.status === 'enviado') {
          pushEvent('generate_lead', { form_type: 'contato', form_destination: String(data.destino || '') });
          form.reset();
        }
      } catch {
        show('error', form.dataset.error || 'Não foi possível enviar agora. Tente o WhatsApp.', true);
      } finally {
        if (button) {
          button.disabled = false;
        }
      }
    });
  });
}

function years() {
  document.querySelectorAll('.yc').forEach((root) => {
    const year = root.querySelector('.yc-year');
    const items = [...root.querySelectorAll('.yc-list > li')];
    if (!year || items.length < 2) {
      return;
    }
    const values = items.map((item) => Number(item.querySelector('.mono')?.textContent || '0'));
    const update = () => {
      const rect = year.getBoundingClientRect();
      const anchor = rect.top + rect.height / 2;
      const centers = items.map((item) => {
        const box = item.getBoundingClientRect();
        return box.top + box.height / 2;
      });
      let value = values[0];
      if (anchor >= centers[centers.length - 1]) {
        value = values[values.length - 1];
      } else if (anchor > centers[0]) {
        const index = centers.findIndex((center, i) => anchor >= center && anchor < centers[i + 1]);
        if (index >= 0) {
          const t = (anchor - centers[index]) / (centers[index + 1] - centers[index]);
          const eased = reduced ? Math.round(t) : t * t * (3 - 2 * t);
          value = Math.round(values[index] + (values[index + 1] - values[index]) * eased);
        }
      }
      year.textContent = String(value);
    };
    update();
    window.addEventListener('scroll', update, { passive: true });
    window.addEventListener('resize', update);
  });
}

function orbit() {
  const root = document.querySelector('.loop');
  if (!root || reduced) {
    root?.classList.add('is-in');
    return;
  }

  const ring = root.querySelector('.loop-ring');
  const gx = root.querySelector('.gx');
  const spin = root.querySelector('.gx-orbit');
  const tail = root.querySelector('.gx-tail');
  const faces = [...root.querySelectorAll('.gx-face')];
  const steps = [...root.querySelectorAll('.loop-steps > li')];
  if (!ring || !gx || !spin || !tail) {
    return;
  }

  const tilt = 62;
  const state = {
    angle: -45,
    speed: 0,
    dragging: false,
    grab: 0,
    lastPointer: 0,
    lastTime: 0,
    rx: tilt,
    ry: 0,
    vrx: 0,
    vry: 0,
    targetRx: tilt,
    targetRy: 0,
    active: -1,
    startAt: 0,
  };
  let frame = 0;
  let prev = 0;

  const pointerAngle = (event) => {
    const box = ring.getBoundingClientRect();
    const scale = 400 / box.width;
    const u = (event.clientX - (box.left + box.width / 2)) * scale;
    const v = ((event.clientY - (box.top + box.height / 2)) * scale) / Math.cos((state.rx * Math.PI) / 180);
    return (Math.atan2(u, -v) * 180) / Math.PI;
  };

  const unwrap = (next, ref) => next + 360 * Math.round((ref - next) / 360);

  const setActive = (index) => {
    if (index === state.active) {
      return;
    }
    state.active = index;
    steps.forEach((step, i) => step.classList.toggle('is-dim', i !== index));
    faces[index]?.animate(
      [{ scale: 1 }, { scale: 1.8 }, { scale: 1 }],
      { duration: 520, easing: 'cubic-bezier(0.2, 0.7, 0.2, 1)' },
    );
  };

  const tick = (now) => {
    frame = requestAnimationFrame(tick);
    const dt = Math.min((now - (prev || now)) / 1000, 0.05);
    prev = now;
    if (!root.classList.contains('is-in')) {
      return;
    }
    if (!state.startAt) {
      state.startAt = now + 1400;
    }
    const running = now >= state.startAt;
    if (!state.dragging) {
      const base = running ? 30 : 0;
      state.speed += (base - state.speed) * (1 - Math.exp(-dt * 0.9));
      state.angle += state.speed * dt;
    }
    state.vrx += (140 * (state.targetRx - state.rx) - 13 * state.vrx) * dt;
    state.vry += (140 * (state.targetRy - state.ry) - 13 * state.vry) * dt;
    state.rx += state.vrx * dt;
    state.ry += state.vry * dt;
    spin.setAttribute('transform', `rotate(${state.angle.toFixed(2)} 200 200)`);
    gx.style.setProperty('--rx', `${state.rx.toFixed(2)}deg`);
    gx.style.setProperty('--ry', `${state.ry.toFixed(2)}deg`);
    const len = Math.min(0.06 + Math.abs(state.speed) / 2400, 0.5);
    tail.style.strokeDasharray = `${len} ${1 - len}`;
    tail.style.strokeDashoffset = state.speed >= 0 ? `${-(0.75 - len)}` : '-0.75';
    tail.style.opacity = String(Math.min(0.35 + Math.abs(state.speed) / 900, 0.85));
    if (running || state.dragging) {
      const pos = (((state.angle - 315) % 360) + 360 + 2) % 360;
      setActive(Math.floor(pos / 90) % 4);
    }
  };

  ring.addEventListener('pointerdown', (event) => {
    state.dragging = true;
    ring.setPointerCapture(event.pointerId);
    ring.classList.add('is-grabbing');
    const at = pointerAngle(event);
    state.grab = state.angle - at;
    state.lastPointer = at;
    state.lastTime = performance.now();
    state.speed = 0;
  });
  ring.addEventListener('pointermove', (event) => {
    const box = ring.getBoundingClientRect();
    const nx = ((event.clientX - box.left) / box.width - 0.5) * 2;
    const ny = ((event.clientY - box.top) / box.height - 0.5) * 2;
    state.targetRx = tilt - Math.max(-1, Math.min(1, ny)) * 12;
    state.targetRy = Math.max(-1, Math.min(1, nx)) * 16;
    if (!state.dragging) {
      return;
    }
    const now = performance.now();
    const at = unwrap(pointerAngle(event), state.lastPointer);
    const dt = Math.max((now - state.lastTime) / 1000, 0.001);
    const instant = (at - state.lastPointer) / dt;
    state.speed = state.speed * 0.6 + instant * 0.4;
    state.angle = at + state.grab;
    state.lastPointer = at;
    state.lastTime = now;
  });
  const release = (event) => {
    if (!state.dragging) {
      return;
    }
    state.dragging = false;
    ring.classList.remove('is-grabbing');
    if (ring.hasPointerCapture(event.pointerId)) {
      ring.releasePointerCapture(event.pointerId);
    }
    if (performance.now() - state.lastTime > 80) {
      state.speed *= 0.2;
    }
    state.speed = Math.max(-2200, Math.min(2200, state.speed));
    state.vry += state.speed * 0.02;
  };
  ring.addEventListener('pointerup', release);
  ring.addEventListener('pointercancel', release);
  ring.addEventListener('pointerleave', () => {
    state.targetRx = tilt;
    state.targetRy = 0;
  });

  const observer = new IntersectionObserver(([entry]) => {
    if (entry.isIntersecting && !frame) {
      prev = 0;
      frame = requestAnimationFrame(tick);
    } else if (!entry.isIntersecting && frame) {
      cancelAnimationFrame(frame);
      frame = 0;
    }
  });
  observer.observe(root);
}

const PAUSE_ICON = '<path d="M5.5 3.5v9M10.5 3.5v9" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />';
const PLAY_ICON = '<path d="M5 3.5v9l7-4.5z" fill="currentColor" stroke="currentColor" stroke-width="1.2" stroke-linejoin="round" />';

/* Carrossel de logos do HTML pré-renderizado (mesmo comportamento do LogoWall em logos.tsx):
   repagina pelas colunas reais da grade, roda sozinho só quando visível e sem hover/foco,
   e respeita prefers-reduced-motion. */
function logos() {
  document.querySelectorAll('.logo-wall.is-carousel').forEach((root) => {
    const viewport = root.querySelector('.logo-viewport');
    const track = root.querySelector('.logo-track');
    const pager = root.querySelector('.logo-pager');
    const dots = root.querySelector('.logo-dots');
    const pause = root.querySelector('.logo-pause');
    const firstPage = track?.querySelector('.logo-page');
    if (!viewport || !track || !pager || !dots || !firstPage) {
      return;
    }

    const rows = 2;
    const interval = Number(root.dataset.interval) || 3200;
    const listClass = firstPage.className.replace(/\s*is-entering/, '');
    const items = [...track.querySelectorAll('.logo-page > li')];
    const of = dots.dataset.of || '/';
    const motion = window.matchMedia('(prefers-reduced-motion: reduce)');
    const pad = (value) => String(value).padStart(2, '0');
    const columns = (list) => Math.max(1, getComputedStyle(list).gridTemplateColumns.split(' ').filter(Boolean).length);

    let pages = [];
    let perPage = 0;
    let current = 0;
    let paused = false;
    let held = false;
    let visible = false;
    let timer = 0;
    let swipe = null;

    const playing = () => pages.length > 1 && !paused && !held && visible && !motion.matches;

    function schedule() {
      window.clearTimeout(timer);
      if (playing()) {
        timer = window.setTimeout(() => go(current + 1), interval);
      }
    }

    function render(animate) {
      track.style.transform = `translateX(${-current * 100}%)`;
      pages.forEach((page, index) => {
        const active = index === current;
        page.setAttribute('aria-label', `${pad(index + 1)} / ${pad(pages.length)}`);
        page.toggleAttribute('inert', !active);
        if (active) {
          page.removeAttribute('aria-hidden');
        } else {
          page.setAttribute('aria-hidden', 'true');
        }
        page.classList.remove('is-entering');
        if (active && animate) {
          void page.offsetWidth;
          page.classList.add('is-entering');
        }
      });
      [...dots.children].forEach((dot, index) => {
        const active = index === current;
        dot.classList.toggle('is-current', active);
        if (active) {
          dot.setAttribute('aria-current', 'true');
        } else {
          dot.removeAttribute('aria-current');
        }
        const bar = dot.firstElementChild;
        bar?.replaceChildren();
        if (active && bar) {
          const fill = document.createElement('span');
          fill.className = playing() ? 'is-running' : 'is-full';
          fill.style.animationDuration = `${interval}ms`;
          bar.append(fill);
        }
      });
      if (pause) {
        pause.hidden = motion.matches;
      }
      schedule();
    }

    function go(next) {
      if (pages.length < 2) {
        return;
      }
      current = ((next % pages.length) + pages.length) % pages.length;
      render(true);
    }

    function build(size) {
      const firstItem = current * perPage;
      perPage = size;
      pages = [];
      track.replaceChildren();
      for (let start = 0; start < items.length; start += size) {
        const list = document.createElement('ul');
        list.className = listClass;
        items.slice(start, start + size).forEach((item, index) => {
          item.style.setProperty('--i', String(index));
          list.append(item);
        });
        track.append(list);
        pages.push(list);
      }
      dots.replaceChildren(...pages.map((_, index) => {
        const dot = document.createElement('button');
        dot.type = 'button';
        dot.className = 'logo-dot';
        dot.setAttribute('aria-label', `${pad(index + 1)} ${of} ${pad(pages.length)}`);
        dot.append(document.createElement('span'));
        dot.addEventListener('click', () => go(index));
        return dot;
      }));
      pager.hidden = pages.length < 2;
      current = Math.min(Math.floor(firstItem / size), pages.length - 1);
      render(false);
    }

    const setHeld = (value) => {
      if (held !== value) {
        held = value;
        render(false);
      }
    };
    const setVisible = (value) => {
      if (visible !== value) {
        visible = value;
        render(false);
      }
    };

    root.addEventListener('mouseenter', () => setHeld(true));
    root.addEventListener('mouseleave', () => setHeld(root.contains(document.activeElement)));
    root.addEventListener('focusin', () => setHeld(true));
    root.addEventListener('focusout', (event) => {
      if (!root.contains(event.relatedTarget)) {
        setHeld(root.matches(':hover'));
      }
    });
    root.addEventListener('keydown', (event) => {
      if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') {
        return;
      }
      event.preventDefault();
      go(current + (event.key === 'ArrowRight' ? 1 : -1));
    });

    viewport.addEventListener('pointerdown', (event) => {
      swipe = event.pointerType === 'mouse' ? null : { x: event.clientX, y: event.clientY };
    });
    viewport.addEventListener('pointerup', (event) => {
      const start = swipe;
      swipe = null;
      if (!start) {
        return;
      }
      const dx = event.clientX - start.x;
      if (Math.abs(dx) > 40 && Math.abs(dx) > Math.abs(event.clientY - start.y)) {
        go(current + (dx < 0 ? 1 : -1));
      }
    });
    viewport.addEventListener('pointercancel', () => {
      swipe = null;
    });

    pause?.addEventListener('click', () => {
      paused = !paused;
      pause.setAttribute('aria-pressed', String(paused));
      pause.setAttribute('aria-label', (paused ? pause.dataset.labelPlay : pause.dataset.labelPause) || '');
      const icon = pause.querySelector('svg');
      if (icon) {
        icon.innerHTML = paused ? PLAY_ICON : PAUSE_ICON;
      }
      render(false);
    });

    new IntersectionObserver(([entry]) => setVisible(entry.isIntersecting && document.visibilityState === 'visible'), { threshold: 0.35 }).observe(root);
    document.addEventListener('visibilitychange', () => setVisible(document.visibilityState === 'visible' && root.getBoundingClientRect().top < window.innerHeight));
    motion.addEventListener('change', () => render(false));

    build(columns(firstPage) * rows);
    new ResizeObserver(() => {
      const size = columns(pages[0]) * rows;
      if (size !== perPage) {
        build(size);
      }
    }).observe(viewport);
  });
}

reveal();
header();
consent();
lens();
tilt();
rotating();
plans();
cases();
lightbox();
leadForms();
contactForms();
whatsappClicks();
years();
orbit();
logos();
