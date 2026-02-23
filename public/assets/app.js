/**
 * Contract Tracker — Frontend JS
 * ES2023 · No dependencies
 */

// ── Theme toggle (global) ──
const themeButtons = document.querySelectorAll('[data-theme-toggle]');
const themeMeta = document.querySelector('meta[name="theme-color"]');
const themeColorMap = { dark: '#07080d', light: '#f4f7fc' };

const detectTheme = () => {
  const attrTheme = document.documentElement.getAttribute('data-theme');
  if (attrTheme === 'dark' || attrTheme === 'light') return attrTheme;

  try {
    const saved = localStorage.getItem('theme');
    if (saved === 'dark' || saved === 'light') return saved;
  } catch (e) {}

  if (window.matchMedia && window.matchMedia('(prefers-color-scheme: light)').matches) {
    return 'light';
  }
  return 'dark';
};

const applyTheme = (theme, persist = false) => {
  document.documentElement.setAttribute('data-theme', theme);
  document.documentElement.style.colorScheme = theme;

  if (themeMeta) {
    themeMeta.setAttribute('content', themeColorMap[theme] || themeColorMap.dark);
  }

  themeButtons.forEach((button) => {
    button.textContent = theme === 'dark' ? 'Тема: темная' : 'Тема: светлая';
    button.setAttribute('aria-pressed', theme === 'light' ? 'true' : 'false');
  });

  if (persist) {
    try { localStorage.setItem('theme', theme); } catch (e) {}
  }
};

applyTheme(detectTheme());

themeButtons.forEach((button) => {
  button.addEventListener('click', () => {
    const nextTheme = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
    applyTheme(nextTheme, true);
  });
});

// ── Flash auto-dismiss ──
document.querySelectorAll('.flash').forEach(el => {
  setTimeout(() => {
    el.style.opacity = '0';
    el.style.transform = 'translateY(-10px)';
    setTimeout(() => el.remove(), 300);
  }, 4500);
});

// ── 44-ФЗ → показать НМЦК ──
const lawSel = document.getElementById('law_type');
const nmckGr = document.getElementById('nmck-group');
if (lawSel && nmckGr) {
  const toggle = () => {
    const show = lawSel.value === '44';
    nmckGr.style.display = show ? '' : 'none';
    if (!show) { const i = nmckGr.querySelector('input'); if (i) i.value = ''; }
  };
  lawSel.addEventListener('change', toggle);
  toggle();
}

// ── Confirm опасные действия ──
document.querySelectorAll('[data-confirm]').forEach(el => {
  el.addEventListener('click', e => {
    if (!confirm(el.dataset.confirm)) e.preventDefault();
  });
});

// ── Upload — показать имя файла ──
document.querySelectorAll('.upload-zone').forEach(zone => {
  const input = zone.querySelector('input[type="file"]');
  const label = zone.querySelector('.upload-zone__label');
  if (input && label) {
    input.addEventListener('change', () => {
      if (input.files.length) label.textContent = input.files[0].name;
    });
  }
});

// ── Smooth page transition (CSS class) ──
document.documentElement.classList.add('loaded');

// ── Table columns: visibility + manual resize ──
const parseStoredObject = (raw) => {
  if (!raw) return {};
  try {
    const value = JSON.parse(raw);
    return value && typeof value === 'object' ? value : {};
  } catch (e) {
    return {};
  }
};

const storageGet = (key) => {
  try {
    return localStorage.getItem(key);
  } catch (e) {
    return null;
  }
};

const storageSet = (key, value) => {
  try {
    localStorage.setItem(key, value);
  } catch (e) {}
};

const initManagedTables = () => {
  document.querySelectorAll('table[data-table-id]').forEach((table) => {
    const tableId = table.dataset.tableId || '';
    if (!tableId) return;

    const visibilityKey = `table:${tableId}:visibility`;
    const widthKey = `table:${tableId}:widths`;
    const controls = document.querySelectorAll(
      `input[type="checkbox"][data-table-id="${tableId}"][data-table-column]`
    );
    const cols = Array.from(table.querySelectorAll('colgroup col[data-col-key]'));
    const storedVisibility = parseStoredObject(storageGet(visibilityKey));
    const storedWidths = parseStoredObject(storageGet(widthKey));

    const setColumnVisible = (columnKey, visible) => {
      table.querySelectorAll(`[data-col-key="${columnKey}"]`).forEach((el) => {
        el.style.display = visible ? '' : 'none';
      });
      table.querySelectorAll(`col[data-col-key="${columnKey}"]`).forEach((el) => {
        el.style.display = visible ? '' : 'none';
      });
    };

    controls.forEach((checkbox) => {
      const key = checkbox.dataset.tableColumn || '';
      if (!key) return;
      const visible = storedVisibility[key] !== false;
      checkbox.checked = visible;
      setColumnVisible(key, visible);

      checkbox.addEventListener('change', () => {
        storedVisibility[key] = checkbox.checked;
        setColumnVisible(key, checkbox.checked);
        storageSet(visibilityKey, JSON.stringify(storedVisibility));
      });
    });

    cols.forEach((col) => {
      const key = col.dataset.colKey || '';
      const width = Number(storedWidths[key] ?? 0);
      if (key && Number.isFinite(width) && width >= 60) {
        col.style.width = `${Math.round(width)}px`;
      }
    });

    const headers = Array.from(table.querySelectorAll('thead th[data-col-key]'));
    headers.forEach((th) => {
      const key = th.dataset.colKey || '';
      if (!key) return;

      const col = table.querySelector(`col[data-col-key="${key}"]`);
      if (!col) return;

      const handle = document.createElement('span');
      handle.className = 'col-resizer';
      handle.setAttribute('aria-hidden', 'true');
      th.append(handle);

      handle.addEventListener('mousedown', (event) => {
        if (event.button !== 0) return;
        event.preventDefault();

        if (th.style.display === 'none') return;

        const startX = event.clientX;
        const startWidth = Math.max(60, Math.round(th.getBoundingClientRect().width));

        th.classList.add('is-resizing');
        document.body.classList.add('is-col-resizing');

        const onMove = (moveEvent) => {
          const delta = moveEvent.clientX - startX;
          const nextWidth = Math.max(60, Math.round(startWidth + delta));
          col.style.width = `${nextWidth}px`;
        };

        const onUp = () => {
          document.removeEventListener('mousemove', onMove);
          document.removeEventListener('mouseup', onUp);
          th.classList.remove('is-resizing');
          document.body.classList.remove('is-col-resizing');

          const width = Math.max(60, Math.round(col.getBoundingClientRect().width));
          storedWidths[key] = width;
          storageSet(widthKey, JSON.stringify(storedWidths));
        };

        document.addEventListener('mousemove', onMove);
        document.addEventListener('mouseup', onUp);
      });
    });
  });
};

initManagedTables();
