// FatakNews.in - Panel JS

(function initPanelMobileSidebar() {
  const sidebar = document.getElementById('panelSidebar');
  if (!sidebar) return;

  const panelMain = document.querySelector('.panel-main');
  const panelHeader = panelMain?.querySelector('.panel-header');
  let btn = document.getElementById('panelSidebarToggle');

  if (!btn && panelHeader) {
    btn = document.createElement('button');
    btn.type = 'button';
    btn.id = 'panelSidebarToggle';
    btn.className = 'panel-sidebar-toggle';
    btn.setAttribute('aria-label', 'Open panel menu');
    btn.innerHTML = '<i class="fa fa-bars"></i>';

    const headerLead = panelHeader.firstElementChild;
    if (headerLead && getComputedStyle(headerLead).display.includes('flex')) {
      headerLead.insertBefore(btn, headerLead.firstChild);
    } else {
      panelHeader.insertBefore(btn, panelHeader.firstChild);
    }
  }

  if (!btn) return;

  btn.classList.add('panel-sidebar-toggle');
  btn.style.removeProperty('display');
  btn.setAttribute('aria-controls', 'panelSidebar');
  btn.setAttribute('aria-expanded', 'false');

  let mobileBar = sidebar.querySelector('.panel-sidebar-mobilebar');
  if (!mobileBar) {
    const labelText = sidebar.querySelector('.panel-logo div[style*="text-transform:uppercase"]')?.textContent?.trim() || 'Panel Menu';
    mobileBar = document.createElement('div');
    mobileBar.className = 'panel-sidebar-mobilebar';
    mobileBar.innerHTML = `<strong>${labelText}</strong><button type="button" class="panel-sidebar-mobileclose" aria-label="Close panel menu"><i class="fa fa-times"></i></button>`;
    sidebar.insertBefore(mobileBar, sidebar.firstChild);
    mobileBar.querySelector('.panel-sidebar-mobileclose')?.addEventListener('click', () => setOpen(false));
  }

  let overlay = document.querySelector('.panel-sidebar-overlay');
  if (!overlay) {
    overlay = document.createElement('div');
    overlay.className = 'panel-sidebar-overlay';
    document.body.appendChild(overlay);
  }

  const setOpen = (open) => {
    sidebar.classList.toggle('open', open);
    overlay.classList.toggle('open', open);
    document.body.classList.toggle('menu-open', open);
    btn.setAttribute('aria-expanded', open ? 'true' : 'false');
  };

  btn.addEventListener('click', () => setOpen(!sidebar.classList.contains('open')));
  overlay.addEventListener('click', () => setOpen(false));

  sidebar.querySelectorAll('a').forEach((link) => {
    link.addEventListener('click', () => {
      if (window.innerWidth <= 768) setOpen(false);
    });
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') setOpen(false);
  });

  window.addEventListener('resize', () => {
    if (window.innerWidth > 768) setOpen(false);
  });
})();

(function initResponsiveDataTables() {
  document.querySelectorAll('.data-table').forEach((table) => {
    const headers = Array.from(table.querySelectorAll('thead th')).map((th) => th.textContent.trim());
    if (!headers.length) return;

    table.querySelectorAll('tbody tr').forEach((row) => {
      Array.from(row.children).forEach((cell, index) => {
        if (cell.tagName !== 'TD') return;
        cell.setAttribute('data-label', headers[index] || 'Field');
      });
    });
  });
})();
