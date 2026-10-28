let searchIndex = [];
let selectedResult = 0;

async function buildSearchIndex() {
  searchIndex = [];
  for (const page of pageIndex) {
    const raw = await fetchPageContent(page);
    searchIndex.push({
      ...page,
      route: getPageRoute(page.sectionSlug, page.slug),
      content: raw.toLowerCase(),
      rawContent: raw.replace(/^---[\s\S]*?---\n/, '')
    });
  }
}

function openSearch() {
  document.getElementById('searchModal').classList.add('open');
  const input = document.getElementById('searchModalInput');
  input.value = '';
  selectedResult = 0;
  renderSearchResults('');
  setTimeout(() => input.focus(), 50);
}

function closeSearch() {
  document.getElementById('searchModal').classList.remove('open');
}

function searchPages(query) {
  if (!query.trim()) return searchIndex.slice(0, 10);
  const q = query.toLowerCase();
  const terms = q.split(/\s+/).filter(Boolean);

  return searchIndex
    .map(item => {
      let score = 0;
      const titleLower = item.title.toLowerCase();
      if (titleLower.includes(q)) score += 10;
      terms.forEach(t => {
        if (titleLower.includes(t)) score += 5;
        if (item.content.includes(t)) score += 1;
      });
      return { ...item, score: query.trim() ? score : 1 };
    })
    .filter(item => item.score > 0)
    .sort((a, b) => b.score - a.score)
    .slice(0, 10);
}

function getSnippet(raw, query) {
  const text = raw.replace(/[#*`>|]/g, ' ').replace(/\s+/g, ' ').trim();
  if (!query.trim()) return text.slice(0, 100) + (text.length > 100 ? '…' : '');
  const idx = text.toLowerCase().indexOf(query.toLowerCase());
  if (idx === -1) return text.slice(0, 120) + '…';
  const start = Math.max(0, idx - 50);
  const end = Math.min(text.length, idx + 90);
  return (start > 0 ? '…' : '') + text.slice(start, end) + '…';
}

function escapeHtml(str) {
  return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

function renderSearchResults(query) {
  const container = document.getElementById('searchResults');
  const results = searchPages(query);

  if (!results.length) {
    container.innerHTML = '<div class="search-empty">No results found</div>';
    return;
  }

  let html = '';
  let lastSection = '';

  results.forEach((r, i) => {
    if (!query.trim() && r.sectionTitle !== lastSection) {
      lastSection = r.sectionTitle;
      html += `<div class="search-result-group">${escapeHtml(r.sectionTitle)}</div>`;
    }
    const breadcrumb = r.folderTitle
      ? `${r.sectionTitle} › ${r.folderTitle}`
      : r.sectionTitle;
    const snippet = getSnippet(r.rawContent || r.content, query);
    html += `<a class="search-result${i === selectedResult ? ' selected' : ''}" data-route="${r.route}" data-idx="${i}">
      <div class="search-result-title">${escapeHtml(r.title)}</div>
      <div class="search-result-breadcrumb">${escapeHtml(breadcrumb)}</div>
      ${snippet ? `<div class="search-result-snippet">${escapeHtml(snippet)}</div>` : ''}
    </a>`;
  });

  container.innerHTML = html;

  container.querySelectorAll('.search-result').forEach(el => {
    el.addEventListener('click', () => navigateTo(el.dataset.route));
  });

  const selected = container.querySelector('.search-result.selected');
  selected?.scrollIntoView({ block: 'nearest' });
}

function navigateTo(route) {
  closeSearch();
  window.location.hash = route.slice(1);
}

function findBestParagraph(raw, query) {
  const terms = query.toLowerCase().split(/\s+/).filter(t => t.length > 2);
  const blocks = raw.split(/\n\n+/).map(b => b.trim()).filter(b =>
    b && !b.startsWith('---') && !b.startsWith('> 📷')
  );

  let best = { score: 0, text: '' };
  blocks.forEach(block => {
    const clean = block.replace(/^#+\s*/gm, '').replace(/\*\*/g, '').replace(/`/g, '');
    const lower = clean.toLowerCase();
    let score = 0;
    terms.forEach(t => { if (lower.includes(t)) score += 2; });
    if (score > best.score) best = { score, text: clean };
  });

  if (best.text) {
    const oneLine = best.text.replace(/\s+/g, ' ').trim();
    return oneLine.length > 280 ? oneLine.slice(0, 277) + '…' : oneLine;
  }
  return getSnippet(raw, query);
}

function answerFromDocs(question) {
  const results = searchPages(question);
  if (!results.length) {
    return {
      html: 'I could not find that in the BeetleOps docs. Try different words, or use <strong>Ctrl+K</strong> to browse all pages.'
    };
  }

  const top = results.slice(0, 3);
  const intro = top.length === 1
    ? 'Here is what the docs say:'
    : 'I found a few relevant pages:';

  const parts = top.map(r => {
    const para = findBestParagraph(r.rawContent || '', question);
    const link = `<a href="${r.route}">${escapeHtml(r.title)}</a>`;
    return `<p style="margin:0 0 8px"><strong>${link}</strong><br><span style="color:var(--text-muted);font-size:12px">${escapeHtml(r.sectionTitle)}</span><br>${escapeHtml(para)}</p>`;
  });

  return { html: `<p style="margin:0 0 10px">${intro}</p>${parts.join('')}` };
}

function initSearch() {
  document.getElementById('searchTrigger').addEventListener('click', openSearch);

  document.getElementById('searchModalInput').addEventListener('input', e => {
    selectedResult = 0;
    renderSearchResults(e.target.value);
  });

  document.getElementById('searchModal').addEventListener('click', e => {
    if (e.target.id === 'searchModal') closeSearch();
  });

  document.addEventListener('keydown', e => {
    if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
      e.preventDefault();
      openSearch();
      return;
    }
    if (!document.getElementById('searchModal').classList.contains('open')) return;

    if (e.key === 'Escape') { closeSearch(); return; }

    const results = document.querySelectorAll('.search-result');
    if (!results.length) return;

    if (e.key === 'ArrowDown') {
      e.preventDefault();
      selectedResult = Math.min(selectedResult + 1, results.length - 1);
      renderSearchResults(document.getElementById('searchModalInput').value);
    }
    if (e.key === 'ArrowUp') {
      e.preventDefault();
      selectedResult = Math.max(selectedResult - 1, 0);
      renderSearchResults(document.getElementById('searchModalInput').value);
    }
    if (e.key === 'Enter') {
      e.preventDefault();
      const selected = document.querySelector('.search-result.selected');
      if (selected) navigateTo(selected.dataset.route);
    }
  });
}
