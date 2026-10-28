let navData = null;
let pageIndex = [];

function getPageRoute(sectionSlug, pageSlug) {
  return `#/${sectionSlug}/${pageSlug}`;
}

function countPages(section) {
  let count = (section.pages || []).length;
  (section.folders || []).forEach(f => { count += f.pages.length; });
  return count;
}

function flattenPages(sections) {
  const pages = [];
  sections.forEach(section => {
    (section.pages || []).forEach(page => {
      pages.push({
        sectionSlug: section.slug,
        sectionTitle: section.title,
        slug: page.slug,
        title: page.title,
        file: page.file,
        category: section.title
      });
    });
    (section.folders || []).forEach(folder => {
      folder.pages.forEach(page => {
        pages.push({
          sectionSlug: section.slug,
          sectionTitle: section.title,
          folderTitle: folder.title,
          slug: page.slug,
          title: page.title,
          file: page.file,
          category: section.title
        });
      });
    });
  });
  return pages;
}

function renderSidebar(activeRoute) {
  const nav = document.getElementById('sidebarNav');
  nav.innerHTML = '';

  navData.sections.forEach(section => {
    const sectionEl = document.createElement('div');
    sectionEl.className = 'nav-section';

    const titleEl = document.createElement('div');
    titleEl.className = 'nav-section-title';
    titleEl.innerHTML = `<span>${section.title}</span>`;
    sectionEl.appendChild(titleEl);

    const pagesList = document.createElement('ul');
    pagesList.className = 'nav-section-pages';

    (section.pages || []).forEach(page => {
      const route = getPageRoute(section.slug, page.slug);
      const li = document.createElement('li');
      const a = document.createElement('a');
      a.className = 'nav-link' + (activeRoute === route ? ' active' : '');
      a.href = route;
      a.textContent = page.title;
      a.addEventListener('click', () => closeMobileSidebar());
      li.appendChild(a);
      pagesList.appendChild(li);
    });

    (section.folders || []).forEach(folder => {
      const folderEl = document.createElement('div');
      folderEl.className = 'nav-folder';

      const folderTitle = document.createElement('div');
      folderTitle.className = 'nav-folder-title';
      folderTitle.innerHTML = `<span>${folder.title}</span>`;
      folderEl.appendChild(folderTitle);

      const folderPages = document.createElement('ul');
      folderPages.className = 'nav-folder-pages';
      folder.pages.forEach(page => {
        const route = getPageRoute(section.slug, page.slug);
        const li = document.createElement('li');
        const a = document.createElement('a');
        a.className = 'nav-link' + (activeRoute === route ? ' active' : '');
        a.href = route;
        a.textContent = page.title;
        a.addEventListener('click', () => closeMobileSidebar());
        li.appendChild(a);
        folderPages.appendChild(li);
      });
      folderEl.appendChild(folderPages);
      pagesList.appendChild(folderEl);
    });

    sectionEl.appendChild(pagesList);
    nav.appendChild(sectionEl);
  });
}

function findPage(sectionSlug, pageSlug) {
  return pageIndex.find(p => p.sectionSlug === sectionSlug && p.slug === pageSlug);
}

function closeMobileSidebar() {
  document.getElementById('sidebar').classList.remove('open');
  document.getElementById('sidebarOverlay').classList.remove('open');
}

async function loadNavigation() {
  const res = await fetch('data/navigation.json');
  navData = await res.json();
  pageIndex = flattenPages(navData.sections);
  return navData;
}
