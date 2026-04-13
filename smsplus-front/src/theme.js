/**
 * Centralise le thème clair/sombre (persisté dans localStorage).
 * Appliqué sur <html> et <body> pour que tout le DOM hérite des variables CSS.
 */
export function applyTheme(theme) {
  const isDark = theme === 'dark';
  document.documentElement.classList.toggle('theme-dark', isDark);
  document.body.classList.toggle('theme-dark', isDark);
  try {
    localStorage.setItem('theme', theme);
  } catch {
    /* ignore */
  }
}

export function initTheme() {
  let t = 'light';
  try {
    t = localStorage.getItem('theme') || 'light';
  } catch {
    /* ignore */
  }
  applyTheme(t);
  return t;
}
