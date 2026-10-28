let tocObserver = null;

function getScrollRoot() {
  return document.querySelector('.main-scroll') || null;
}

function buildToc() {
  const content = document.getElementById('pageContent');
  const tocList = document.getElementById('tocList');
  tocList.innerHTML = '';

  const headings = content.querySelectorAll('h2, h3');
  if (!headings.length) {
    tocList.innerHTML = '<li style="padding:4px 8px;font-size:12px;color:var(--text-muted)">No sections</li>';
    return;
  }

  const scrollRoot = getScrollRoot();

  headings.forEach(h => {
    const li = document.createElement('li');
    const a = document.createElement('a');
    a.href = `#${h.id}`;
    a.textContent = h.textContent;
    a.dataset.target = h.id;
    if (h.tagName === 'H3') a.classList.add('toc-h3');
    a.addEventListener('click', e => {
      e.preventDefault();
      const el = document.getElementById(h.id);
      if (scrollRoot) {
        const top = el.getBoundingClientRect().top - scrollRoot.getBoundingClientRect().top + scrollRoot.scrollTop - 24;
        scrollRoot.scrollTo({ top, behavior: 'smooth' });
      } else {
        el?.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    });
    li.appendChild(a);
    tocList.appendChild(li);
  });

  initScrollSpy(headings, scrollRoot);
}

function initScrollSpy(headings, scrollRoot) {
  if (tocObserver) tocObserver.disconnect();

  const tocLinks = document.querySelectorAll('#tocList a');
  const linkMap = {};
  tocLinks.forEach(a => { linkMap[a.dataset.target] = a; });

  if (tocLinks.length > 0) {
    tocLinks[0].classList.add('active');
  }

  tocObserver = new IntersectionObserver(entries => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        tocLinks.forEach(a => a.classList.remove('active'));
        linkMap[entry.target.id]?.classList.add('active');
      }
    });
  }, {
    root: scrollRoot,
    rootMargin: '-20px 0px -65% 0px',
    threshold: 0
  });

  headings.forEach(h => tocObserver.observe(h));
}
