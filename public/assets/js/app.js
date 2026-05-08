// FatakNews.in - Core JS

const appConfig = (typeof APP !== 'undefined' ? APP : {}) || {};
window.APP = Object.assign({}, window.APP || {}, appConfig);

const Toast = {
  show(msg, type = 'info', duration = 3500) {
    const container = document.getElementById('toastContainer');
    if (!container) return;

    const icons = {
      success: 'fa-check-circle',
      error: 'fa-times-circle',
      info: 'fa-info-circle'
    };

    const el = document.createElement('div');
    el.className = `toast ${type}`;
    el.innerHTML = `<i class="fa ${icons[type] || icons.info}"></i><span>${msg}</span>`;
    container.appendChild(el);

    setTimeout(() => {
      el.style.opacity = '0';
      el.style.transform = 'translateX(100%)';
      el.style.transition = '0.3s';
      setTimeout(() => el.remove(), 300);
    }, duration);
  }
};

function resolveAppUrl(path = '') {
  if (/^https?:\/\//i.test(path)) return path;

  const base = (window.APP?.url || '').replace(/\/+$/, '');
  const normalized = String(path || '').trim();
  if (!normalized) return base;

  return normalized.startsWith('/') ? `${base}${normalized}` : `${base}/${normalized}`;
}

function escapeHtml(value = '') {
  return String(value)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

function syncAppCsrfToken(token) {
  if (!token) return;
  window.APP.csrfToken = token;
  document.querySelectorAll('input[name="csrf_token"]').forEach((field) => {
    field.value = token;
  });
}

async function refreshAppCsrfToken() {
  const res = await fetch(resolveAppUrl('/api/csrf'), {
    headers: { 'X-Requested-With': 'XMLHttpRequest' }
  });

  const text = await res.text();
  try {
    const data = JSON.parse(text);
    if (data?.success && data.token) {
      syncAppCsrfToken(data.token);
      return data.token;
    }
  } catch {}

  return null;
}

const API = {
  async post(url, data = {}, retried = false) {
    const res = await fetch(resolveAppUrl(url), {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': window.APP.csrfToken,
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: JSON.stringify(data)
    });

    const text = await res.text();
    try {
      const parsed = JSON.parse(text);
      if (parsed?.csrf_token) {
        syncAppCsrfToken(parsed.csrf_token);
      } else if (parsed?.token) {
        syncAppCsrfToken(parsed.token);
      }

      if (res.status === 403 && parsed?.error === 'Invalid CSRF token' && !retried) {
        const refreshed = await refreshAppCsrfToken();
        if (refreshed) {
          return this.post(url, data, true);
        }
      }

      return parsed;
    } catch {
      return { success: false, error: res.ok ? 'Unexpected server response' : `Request failed (${res.status})` };
    }
  },

  async get(url) {
    const res = await fetch(resolveAppUrl(url), {
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    });

    const text = await res.text();
    try {
      return JSON.parse(text);
    } catch {
      return { success: false, error: res.ok ? 'Unexpected server response' : `Request failed (${res.status})` };
    }
  },

  async upload(url, file, extraFields = {}, fieldName = 'file', retried = false) {
    const formData = new FormData();
    formData.append(fieldName, file);
    Object.entries(extraFields).forEach(([key, value]) => {
      formData.append(key, value);
    });

    const res = await fetch(resolveAppUrl(url), {
      method: 'POST',
      headers: {
        'X-CSRF-Token': window.APP.csrfToken,
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: formData
    });

    const text = await res.text();
    try {
      const parsed = JSON.parse(text);
      if (parsed?.csrf_token) {
        syncAppCsrfToken(parsed.csrf_token);
      } else if (parsed?.token) {
        syncAppCsrfToken(parsed.token);
      }

      if (res.status === 403 && parsed?.error === 'Invalid CSRF token' && !retried) {
        const refreshed = await refreshAppCsrfToken();
        if (refreshed) {
          return this.upload(url, file, extraFields, fieldName, true);
        }
      }

      return parsed;
    } catch {
      return { success: false, error: res.ok ? 'Unexpected server response' : `Upload failed (${res.status})` };
    }
  }
};

const currentScriptUrl = (() => {
  const current = document.currentScript?.src;
  if (current) return current;

  const fallback = [...document.scripts].reverse().find((script) => (
    script.src && /\/public\/assets\/js\/app\.js(?:\?|$)/.test(script.src)
  ));
  return fallback?.src || '';
})();

const currentScriptVersion = (() => {
  if (!currentScriptUrl) return '';
  try {
    const url = new URL(currentScriptUrl, window.location.href);
    return url.searchParams.get('v') || '';
  } catch {
    return '';
  }
})();

const loadedFeatureScripts = new Set();

function resolveFeatureScriptUrl(fileName) {
  const base = resolveAppUrl(`/public/assets/js/${fileName}`);
  return currentScriptVersion ? `${base}?v=${encodeURIComponent(currentScriptVersion)}` : base;
}

function loadFeatureScript(fileName) {
  if (!fileName || loadedFeatureScripts.has(fileName)) {
    return;
  }

  loadedFeatureScripts.add(fileName);
  const script = document.createElement('script');
  script.src = resolveFeatureScriptUrl(fileName);
  script.async = true;
  script.dataset.appFeature = fileName;
  document.body.appendChild(script);
}

window.Toast = Toast;
window.API = API;
window.escapeHtml = escapeHtml;
window.resolveAppUrl = resolveAppUrl;
window.syncAppCsrfToken = syncAppCsrfToken;
window.refreshAppCsrfToken = refreshAppCsrfToken;
window.loadFeatureScript = loadFeatureScript;

(function initTicker() {
  const track = document.getElementById('tickerTrack');
  if (!track) return;
  track.innerHTML += track.innerHTML;
})();

(function initNavbarScroll() {
  const nav = document.getElementById('navbar');
  if (!nav) return;

  window.addEventListener('scroll', () => {
    nav.style.boxShadow = window.scrollY > 60 ? '0 4px 32px rgba(0,0,0,0.6)' : '';
  }, { passive: true });
})();

(function initMobileMenu() {
  const btn = document.getElementById('mobileMenuBtn');
  const mobileNav = document.getElementById('mobileNav');
  const overlay = document.getElementById('mobileNavOverlay');
  if (!btn || !mobileNav || !overlay) return;

  const setOpen = (open) => {
    mobileNav.classList.toggle('open', open);
    overlay.classList.toggle('open', open);
    btn.classList.toggle('open', open);
    btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    document.body.classList.toggle('menu-open', open);
  };

  btn.addEventListener('click', () => {
    setOpen(!mobileNav.classList.contains('open'));
  });

  overlay.addEventListener('click', () => setOpen(false));
  mobileNav.querySelectorAll('a').forEach((link) => {
    link.addEventListener('click', () => setOpen(false));
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      setOpen(false);
    }
  });
})();

(function initNotifications() {
  const toggle = document.getElementById('notifToggle');
  const list = document.getElementById('notifList');
  if (!toggle) return;

  let loaded = false;

  toggle.addEventListener('click', async (e) => {
    e.stopPropagation();
    toggle.classList.toggle('open');

    if (!loaded && toggle.classList.contains('open') && list) {
      loaded = true;
      try {
        const data = await API.get('/api/notifications');
        const notifications = data.notifications || [];
        const avatarFallback = window.APP.avatarFallback || '';
        list.innerHTML = notifications.length
          ? notifications.map((n) => `
            <div class="notif-item ${n.is_read ? '' : 'unread'}">
              <img src="${n.actor_avatar || avatarFallback}" alt="" onerror="this.onerror=null;this.src='${avatarFallback}'">
              <div>
                <p>${n.message}</p>
                <time>${n.time_ago}</time>
              </div>
            </div>`).join('')
          : '<div class="empty-state" style="padding:24px"><i class="fa fa-bell"></i><p>No notifications</p></div>';
      } catch {
        list.innerHTML = '<div class="empty-state"><p>Failed to load</p></div>';
      }
    }
  });

  document.getElementById('markAllRead')?.addEventListener('click', async () => {
    await API.post('/api/notifications/read');
    document.querySelectorAll('.notif-badge').forEach((badge) => badge.remove());
    document.querySelectorAll('.notif-item.unread').forEach((item) => item.classList.remove('unread'));
    Toast.show('All notifications marked as read', 'success');
  });

  document.addEventListener('click', (e) => {
    if (!toggle.contains(e.target)) toggle.classList.remove('open');
  });
})();

(function initUserMenu() {
  const toggle = document.getElementById('userMenuToggle');
  if (!toggle) return;

  toggle.addEventListener('click', (e) => {
    e.stopPropagation();
    toggle.classList.toggle('open');
  });

  document.addEventListener('click', (e) => {
    if (!toggle.contains(e.target)) toggle.classList.remove('open');
  });
})();

document.addEventListener('click', async (e) => {
  const btn = e.target.closest('[data-react]');
  if (!btn) return;
  if (!window.APP.isLoggedIn) {
    Toast.show('Please login to react', 'info');
    return;
  }

  const postId = btn.dataset.react;
  const type = btn.dataset.type || 'like';
  const countEl = btn.querySelector('.react-count');

  try {
    const data = await API.post('/api/posts/react', { post_id: postId, type });
    if (countEl) countEl.textContent = data.count;
    btn.classList.toggle('liked', data.action === 'added');
    Toast.show(data.action === 'added' ? 'Liked' : 'Like removed', data.action === 'added' ? 'success' : 'info', 1500);
  } catch {
    Toast.show('Something went wrong', 'error');
  }
});

document.addEventListener('click', async (e) => {
  const btn = e.target.closest('[data-bookmark]');
  if (!btn) return;
  if (!window.APP.isLoggedIn) {
    Toast.show('Please login to bookmark', 'info');
    return;
  }

  try {
    const data = await API.post('/api/posts/bookmark', { post_id: btn.dataset.bookmark });
    btn.classList.toggle('bookmarked', data.action === 'added');
    Toast.show(data.action === 'added' ? 'Bookmarked' : 'Bookmark removed', data.action === 'added' ? 'success' : 'info', 1500);
  } catch {
    Toast.show('Something went wrong', 'error');
  }
});

async function shareStory(title, url) {
  if (!url) return false;

  if (typeof navigator.share === 'function') {
    try {
      await navigator.share({ title, url });
      return true;
    } catch (error) {
      if (error?.name === 'AbortError') return 'cancelled';
    }
  }

  if (navigator.clipboard?.writeText) {
    try {
      await navigator.clipboard.writeText(url);
      return 'copied';
    } catch {}
  }

  const input = document.createElement('input');
  input.type = 'text';
  input.value = url;
  input.setAttribute('readonly', '');
  input.style.position = 'fixed';
  input.style.opacity = '0';
  document.body.appendChild(input);
  input.select();
  input.setSelectionRange(0, input.value.length);

  let copied = false;
  try {
    copied = document.execCommand('copy');
  } catch {}

  input.remove();
  return copied ? 'copied' : false;
}

document.addEventListener('click', async (e) => {
  const btn = e.target.closest('[data-share-url]');
  if (!btn) return;

  e.preventDefault();

  try {
    const result = await shareStory(btn.dataset.shareTitle || document.title, btn.dataset.shareUrl || '');
    if (result === 'copied') {
      Toast.show('Link copied', 'success', 1500);
    } else if (result === 'cancelled') {
      return;
    } else if (result === false) {
      Toast.show('Share unavailable on this device', 'info', 1800);
    }
  } catch {
    Toast.show('Unable to share right now', 'error');
  }
});

document.addEventListener('click', async (e) => {
  const btn = e.target.closest('[data-follow]');
  if (!btn) return;
  if (!window.APP.isLoggedIn) {
    Toast.show('Please login to follow', 'info');
    return;
  }

  try {
    const data = await API.post('/api/follow', { following_id: btn.dataset.follow });
    btn.classList.toggle('following', data.action === 'followed');
    btn.textContent = data.action === 'followed' ? 'Following' : 'Follow';
    Toast.show(data.action === 'followed' ? 'Following' : 'Unfollowed', 'success', 1500);
  } catch {
    Toast.show('Something went wrong', 'error');
  }
});

(function initLiveSearch() {
  const input = document.getElementById('navSearch');
  const dropdown = document.getElementById('searchDropdown');
  if (!input || !dropdown) return;

  let timer;
  input.addEventListener('input', () => {
    clearTimeout(timer);
    const q = input.value.trim();

    if (q.length < 2) {
      dropdown.classList.remove('show');
      return;
    }

    timer = setTimeout(async () => {
      const data = await API.get(`/api/search?q=${encodeURIComponent(q)}`);
      if (!data.results?.length) {
        dropdown.classList.remove('show');
        return;
      }

      dropdown.innerHTML = data.results.map((result) => `
        <a href="${result.url}" class="search-result-item">
          <img src="${result.thumbnail || resolveAppUrl('/public/assets/images/placeholder.jpg')}" style="width:44px;height:44px;object-fit:cover;border-radius:6px" alt="${escapeHtml(result.alt || result.title || 'FatakNews result')}">
          <div>
            <div style="font-size:13px;font-weight:600">${result.title}</div>
            <div style="font-size:12px;color:var(--muted)">${result.type} Â· ${result.time_ago}</div>
          </div>
        </a>`).join('');

      dropdown.classList.add('show');
    }, 300);
  });

  document.addEventListener('click', (e) => {
    if (!input.contains(e.target) && !dropdown.contains(e.target)) {
      dropdown.classList.remove('show');
    }
  });
})();

(function initCommentForm() {
  const form = document.getElementById('commentForm');
  if (!form) return;

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (!window.APP.isLoggedIn) {
      Toast.show('Please login to comment', 'info');
      return;
    }

    const content = form.querySelector('textarea').value.trim();
    const postId = form.dataset.post;
    const parentId = form.dataset.parent || null;
    if (!content) return;

    const data = await API.post('/api/comments', { post_id: postId, content, parent_id: parentId });
    if (data.success) {
      document.getElementById('commentsList')?.insertAdjacentHTML('afterbegin', data.html);
      form.querySelector('textarea').value = '';
      Toast.show('Comment posted', 'success');
    }
  });
})();

document.addEventListener('click', (e) => {
  const btn = e.target.closest('[data-confirm]');
  if (!btn) return;
  if (!confirm(btn.dataset.confirm || 'Are you sure?')) e.preventDefault();
});

document.addEventListener('change', (e) => {
  const input = e.target;
  if (input.type !== 'file' || !input.dataset.preview) return;

  const preview = document.getElementById(input.dataset.preview);
  if (!preview || !input.files[0]) return;

  preview.src = URL.createObjectURL(input.files[0]);
  preview.style.display = 'block';
});

function initChart(id, labels, data, label, color = '#FF2D2D') {
  const canvas = document.getElementById(id);
  if (!canvas || typeof Chart === 'undefined') return;

  new Chart(canvas, {
    type: 'line',
    data: {
      labels,
      datasets: [{
        label,
        data,
        borderColor: color,
        backgroundColor: `${color}20`,
        tension: 0.4,
        fill: true,
        pointRadius: 4,
        pointBackgroundColor: color
      }]
    },
    options: {
      responsive: true,
      plugins: { legend: { display: false } },
      scales: {
        x: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#7A7A95' } },
        y: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#7A7A95' } }
      }
    }
  });
}

window.initChart = initChart;

(function initHomeCategoryTabDelegation() {
  document.addEventListener('click', async (event) => {
    const tab = event.target.closest('.cat-tab');
    if (!tab || !document.body.classList.contains('home-page')) return;

    const tabs = [...document.querySelectorAll('.cat-tab')];
    if (!tabs.includes(tab)) return;

    const feed = document.getElementById('newsFeed');
    if (!feed) return;

    if (tab.dataset.loading === 'true') return;

    tabs.forEach((item) => item.classList.remove('active'));
    tab.classList.add('active');
    tab.dataset.loading = 'true';

    const catId = tab.dataset.cat || '';
    feed.innerHTML = '<div class="loading-spinner"><i class="fa fa-spinner"></i></div>';

    try {
      const data = await API.get(`/api/posts?category=${encodeURIComponent(catId)}&page=1`);
      feed.innerHTML = data.html || '<div class="empty-state"><i class="fa fa-newspaper"></i><h3>No posts found</h3></div>';
      if (typeof window.initHomeFeedReveal === 'function') {
        window.initHomeFeedReveal(feed);
      }
    } catch {
      feed.innerHTML = '<div class="empty-state"><i class="fa fa-triangle-exclamation"></i><h3>Posts load nahi ho paaye</h3></div>';
      Toast.show('Category posts load nahi ho paaye', 'error');
    } finally {
      delete tab.dataset.loading;
    }
  });
})();

(function loadPageFeatureScripts() {
  const body = document.body;
  if (!body) return;

  // Feature scripts inherit the app.js cache-buster, so keep this file version in sync with home/explore/story bundle updates.
  const shouldLoadHome = body.classList.contains('home-page')
    || !!document.querySelector('.hero-slide, .cat-tab, #categoryRail, .mobile-home-frame');
  const shouldLoadStories = !!document.getElementById('storyComposerModal') || !!document.getElementById('storyViewerModal');
  const shouldLoadExplore = body.classList.contains('explore-page') || !!document.querySelector('.explore-social-host');
  const shouldLoadPanel = !!document.getElementById('panelSidebar') || !!document.querySelector('.data-table');

  if (shouldLoadHome) {
    loadFeatureScript('app.home.js');
  }

  if (shouldLoadStories) {
    loadFeatureScript('app.stories.js');
  }

  if (shouldLoadExplore) {
    loadFeatureScript('app.explore.js');
  }

  if (shouldLoadPanel) {
    loadFeatureScript('app.panel.js');
  }
})();
