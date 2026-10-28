function initTheme() {
  const root = document.documentElement;
  const saved = localStorage.getItem('docs-theme') || 'dark';
  root.setAttribute('data-theme', saved);

  document.querySelectorAll('.theme-btn').forEach(btn => {
    btn.classList.toggle('active', btn.dataset.theme === saved);
    btn.addEventListener('click', () => {
      const theme = btn.dataset.theme;
      root.setAttribute('data-theme', theme);
      localStorage.setItem('docs-theme', theme);
      document.querySelectorAll('.theme-btn').forEach(b =>
        b.classList.toggle('active', b.dataset.theme === theme)
      );
    });
  });
}
