/* Navegación adaptable, sin dependencias externas. */
document.querySelectorAll('[data-nav-toggle]').forEach(function (button) {
  var sidebar = button.closest('.workspace-sidebar');
  function closeMenu() { sidebar.classList.remove('nav-expanded'); button.setAttribute('aria-expanded', 'false'); button.setAttribute('aria-label', 'Abrir navegación'); }
  button.addEventListener('click', function () { var open = sidebar.classList.toggle('nav-expanded'); button.setAttribute('aria-expanded', String(open)); button.setAttribute('aria-label', open ? 'Cerrar navegación' : 'Abrir navegación'); });
  sidebar.querySelectorAll('nav a').forEach(function (link) { link.addEventListener('click', closeMenu); });
  document.addEventListener('keydown', function (event) { if (event.key === 'Escape' && sidebar.classList.contains('nav-expanded')) { closeMenu(); button.focus(); } });
});
