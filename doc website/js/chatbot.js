function setAssistantOpen(open) {
  const panel = document.getElementById('assistantPanel');
  const shell = document.getElementById('contentShell');
  const trigger = document.getElementById('assistantTrigger');
  if (!panel || !shell) return;

  panel.classList.toggle('open', open);
  shell.classList.toggle('assistant-open', open);
  trigger?.classList.toggle('active', open);

  // Reset expand state when closing
  if (!open) panel.classList.remove('expanded');

  if (open) {
    setTimeout(() => document.getElementById('chatInput')?.focus(), 100);
  }
}

function initChatbot() {
  const trigger = document.getElementById('assistantTrigger');
  const closeBtn = document.getElementById('assistantClose');
  const expandBtn = document.querySelector('.assistant-action-btn[aria-label="Expand"]');
  const panel = document.getElementById('assistantPanel');
  const messages = document.getElementById('chatMessages');
  const input = document.getElementById('chatInput');
  const sendBtn = document.getElementById('chatSend');

  if (!panel || !messages) return;

  // Expand / collapse toggle
  if (expandBtn) {
    expandBtn.addEventListener('click', () => {
      const isExpanded = panel.classList.toggle('expanded');
      expandBtn.title = isExpanded ? 'Collapse' : 'Expand';
      expandBtn.setAttribute('aria-label', isExpanded ? 'Collapse' : 'Expand');
      // Swap icon
      expandBtn.innerHTML = isExpanded
        ? `<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="4 14 10 14 10 20"/><polyline points="20 10 14 10 14 4"/><line x1="10" y1="14" x2="3" y2="21"/><line x1="21" y1="3" x2="14" y2="10"/></svg>`
        : `<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 3 21 3 21 9"/><polyline points="9 21 3 21 3 15"/><line x1="21" y1="3" x2="14" y2="10"/><line x1="3" y1="21" x2="10" y2="14"/></svg>`;
    });
  }

  function addMessage(text, role, isHtml) {
    const el = document.createElement('div');
    el.className = `chat-msg ${role}`;
    if (isHtml) el.innerHTML = text;
    else el.textContent = text;
    messages.appendChild(el);
    messages.scrollTop = messages.scrollHeight;
  }

  function ensureWelcome() {
    if (messages.childElementCount === 0) {
      addMessage(
        'Ask me anything about BeetleOps HRMS — I search this documentation and link you to the right page.',
        'bot',
        false
      );
    }
  }

  trigger?.addEventListener('click', () => {
    const willOpen = !panel.classList.contains('open');
    setAssistantOpen(willOpen);
    if (willOpen) ensureWelcome();
  });

  closeBtn?.addEventListener('click', () => setAssistantOpen(false));

  function submitQuestion() {
    const q = input.value.trim();
    if (!q) return;
    addMessage(q, 'user', false);
    input.value = '';

    if (!searchIndex.length) {
      addMessage('Docs are still loading. Try again in a moment.', 'bot', false);
      return;
    }

    const answer = answerFromDocs(q);
    addMessage(answer.html, 'bot', true);

    messages.querySelectorAll('.chat-msg.bot a').forEach(a => {
      a.addEventListener('click', e => {
        e.preventDefault();
        window.location.hash = a.getAttribute('href').slice(1);
      });
    });
  }

  sendBtn?.addEventListener('click', submitQuestion);
  input?.addEventListener('keydown', e => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      submitQuestion();
    }
  });

  document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && panel.classList.contains('open')) {
      setAssistantOpen(false);
    }
  });
}

