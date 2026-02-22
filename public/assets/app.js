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
