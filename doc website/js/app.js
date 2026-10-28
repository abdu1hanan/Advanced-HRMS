async function navigate() {
  const hash = window.location.hash || '#/getting-started/introduction';
  const parts = hash.replace('#/', '').split('/');
  const sectionSlug = parts[0];
  const pageSlug = parts[1];

  const page = findPage(sectionSlug, pageSlug);
  if (!page) {
    document.getElementById('pageTitle').textContent = 'Page Not Found';
    document.getElementById('pageDescription').textContent = '';
    document.getElementById('pageCategory').textContent = '';
    document.getElementById('pageContent').innerHTML = '<p>The page you are looking for does not exist. Use the sidebar to navigate.</p>';
    return;
  }

  document.getElementById('pageContent').innerHTML = '<div class="loading">Loading...</div>';

  try {
    const data = await loadPage(page);
    document.title = `${data.title} — BeetleOps HRMS Docs`;
    document.getElementById('pageCategory').textContent = data.category;
    document.getElementById('pageTitle').textContent = data.title;
    document.getElementById('pageDescription').textContent = data.description;
    document.getElementById('pageContent').innerHTML = data.html;
    renderSidebar(hash);
    buildToc();
    const scrollRoot = document.querySelector('.main-scroll');
    if (scrollRoot) scrollRoot.scrollTop = 0;
    else window.scrollTo(0, 0);
  } catch (err) {
    document.getElementById('pageContent').innerHTML = `<p>Failed to load page: ${err.message}</p>`;
  }
}

function initMobileNav() {
  const toggle = document.getElementById('mobileToggle');
  const sidebar = document.getElementById('sidebar');
  const overlay = document.getElementById('sidebarOverlay');

  toggle.addEventListener('click', () => {
    sidebar.classList.toggle('open');
    overlay.classList.toggle('open');
  });
  overlay.addEventListener('click', closeMobileSidebar);
}

async function init() {
  await loadNavigation();
  await buildSearchIndex();
  initTheme();
  initSearch();
  initChatbot();
  initMobileNav();
  window.addEventListener('hashchange', navigate);
  if (!window.location.hash) {
    window.location.hash = '#/getting-started/introduction';
  } else {
    navigate();
  }
}

document.addEventListener('DOMContentLoaded', init);
