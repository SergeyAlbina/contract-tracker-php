/**
 * Contract Tracker — Frontend JS
 * ES2023 · No dependencies
 */

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
