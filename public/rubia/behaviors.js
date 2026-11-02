const toggle = document.querySelector('.nav-toggle');
const menu = document.querySelector('.menu');

if (toggle && menu) {
  toggle.addEventListener('click', () => {
    const open = menu.classList.toggle('is-open');
    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
  });
}
