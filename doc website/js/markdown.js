function parseFrontmatter(raw) {
  const match = raw.match(/^---\r?\n([\s\S]*?)\r?\n---\r?\n([\s\S]*)$/);
  if (!match) return { meta: {}, body: raw };

  const meta = {};
  match[1].split('\n').forEach(line => {
    const idx = line.indexOf(':');
    if (idx > -1) {
      const key = line.slice(0, idx).trim();
      const val = line.slice(idx + 1).trim().replace(/^["']|["']$/g, '');
      meta[key] = val;
    }
  });
  return { meta, body: match[2] };
}

function slugify(text) {
  return text.toLowerCase().replace(/[^\w\s-]/g, '').replace(/\s+/g, '-').replace(/-+/g, '-');
}

function postProcessHtml(html) {
  html = html.replace(/<blockquote>\s*<p>\s*<strong>IMPORTANT<\/strong>[\s:—-]*([\s\S]*?)<\/p>\s*<\/blockquote>/gi,
    '<blockquote class="callout-important"><p><strong>IMPORTANT</strong>$1</p></blockquote>');

  html = html.replace(/<ol>([\s\S]*?)<\/ol>/g, (match, inner) => {
    if (!inner.includes('<strong>')) return match;
    const cleaned = inner.replace(/<li><p><strong>(.*?)<\/strong>([\s\S]*?)<\/p><\/li>/g,
      '<li><strong>$1</strong>$2</li>');
    return `<ol class="steps">${cleaned}</ol>`;
  });

  html = html.replace(/<blockquote>\s*<p>📷 Screenshot coming soon[\s\S]*?<\/p>\s*<\/blockquote>/gi,
    '<div class="screenshot-placeholder">📷 Screenshot coming soon — add app screenshot here</div>');

  return html;
}

function addHeadingIds(html) {
  const div = document.createElement('div');
  div.innerHTML = html;
  div.querySelectorAll('h2, h3').forEach(h => {
    if (!h.id) h.id = slugify(h.textContent);
  });
  return div.innerHTML;
}

async function loadPage(page) {
  const res = await fetch(`content/${page.file}`);
  if (!res.ok) throw new Error(`Page not found: ${page.file}`);
  const raw = await res.text();
  const { meta, body } = parseFrontmatter(raw);

  let html = marked.parse(body, { gfm: true, breaks: false });
  html = postProcessHtml(html);
  html = addHeadingIds(html);

  return {
    title: meta.title || page.title,
    category: meta.category || page.category,
    description: meta.description || '',
    html
  };
}

async function fetchPageContent(page) {
  const res = await fetch(`content/${page.file}`);
  if (!res.ok) return '';
  const raw = await res.text();
  return parseFrontmatter(raw).body;
}
