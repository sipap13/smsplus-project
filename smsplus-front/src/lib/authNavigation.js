/** Registered from inside <BrowserRouter> so 401 handling uses SPA navigation (no /login hard jump). */
let navigateRef = null;

export function registerAuthNavigate(navigateFn) {
  navigateRef = navigateFn;
}

/**
 * @param {string} to
 * @param {{ replace?: boolean }} [opts]
 */
export function authNavigate(to, opts = {}) {
  const { replace = true } = opts;
  if (typeof navigateRef === 'function') {
    navigateRef(to, { replace });
    return;
  }
  window.location.replace(`${window.location.origin}${to.startsWith('/') ? to : `/${to}`}`);
}
