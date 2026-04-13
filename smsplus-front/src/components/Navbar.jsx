/* eslint-disable react/prop-types */
import { useEffect, useMemo, useState } from 'react';

const PAGES = [
  { id: 'dashboard', label: 'Vue Ops Overview', path: '/dashboard', hint: 'Navigation' },
  { id: 'alerts', label: 'Alertes fraude', path: '/alerts', hint: 'Navigation' },
  { id: 'cdr-occ', label: 'CDR OCC', path: '/cdr/occ', hint: 'Navigation' },
  { id: 'cdr-mmg', label: 'CDR MMG', path: '/cdr/mmg', hint: 'Navigation' },
  { id: 'msisdn', label: 'Recherche MSISDN', path: '/msisdn', hint: 'Navigation' },
  { id: 'services', label: 'Services VAS', path: '/services', hint: 'Navigation' },
  { id: 'users', label: 'Utilisateurs', path: '/users', hint: 'Navigation' },
  { id: 'sos', label: 'SOS Solde & Data', path: '/sos', hint: 'Navigation' },
];

function Icon({ name }) {
  const common = { width: 18, height: 18, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', strokeWidth: '1.9', strokeLinecap: 'round', strokeLinejoin: 'round' };
  if (name === 'search') return <svg {...common}><circle cx="11" cy="11" r="7" /><path d="M21 21l-4.3-4.3" /></svg>;
  if (name === 'moon') return <svg {...common}><path d="M21 12.8A8.5 8.5 0 1 1 11.2 3a6.5 6.5 0 0 0 9.8 9.8z" /></svg>;
  if (name === 'sun') return <svg {...common}><circle cx="12" cy="12" r="4" /><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4" /></svg>;
  if (name === 'user') return <svg {...common}><path d="M20 21a8 8 0 0 0-16 0" /><circle cx="12" cy="8" r="4" /></svg>;
  if (name === 'logout') return <svg {...common}><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" /><path d="M16 17l5-5-5-5" /><path d="M21 12H9" /></svg>;
  return <svg {...common}><circle cx="12" cy="12" r="8" /></svg>;
}

export default function Navbar({ title, breadcrumb, user, onLogout, theme = 'light', onToggleTheme, onNavigate }) {
  const [open, setOpen] = useState(false);
  const [q, setQ] = useState('');

  const results = useMemo(() => {
    const s = q.trim().toLowerCase();
    if (!s) return PAGES;
    return PAGES.filter((p) => p.label.toLowerCase().includes(s));
  }, [q]);

  useEffect(() => {
    const onKeyDown = (e) => {
      const isK = (e.key || '').toLowerCase() === 'k';
      const mod = e.ctrlKey || e.metaKey;
      if (mod && isK) {
        e.preventDefault();
        setOpen(true);
        return;
      }
      if (e.key === 'Escape') setOpen(false);
    };
    window.addEventListener('keydown', onKeyDown);
    return () => window.removeEventListener('keydown', onKeyDown);
  }, []);

  const username = user?.email?.split('@')[0] || 'Admin';

  return (
    <>
      <header className="tt-navbar tt-navbar-compact">
        <div className="tt-navbar-left">
          <div className="tt-breadcrumb">{breadcrumb}</div>
          <h1 className="tt-navbar-title">{title}</h1>
        </div>

        <div className="tt-navbar-right">
          <button type="button" className="nav-search" onClick={() => setOpen(true)} aria-label="Rechercher (Ctrl+K)">
            <span className="nav-search-icon"><Icon name="search" /></span>
            <span className="nav-search-text">Recherche</span>
            <span className="kbd">Ctrl K</span>
          </button>

          <button
            type="button"
            className="nav-icon-btn"
            onClick={onToggleTheme}
            aria-label="Basculer le thème"
            title={theme === 'dark' ? 'Passer au mode clair' : 'Passer au mode sombre'}
          >
            <Icon name={theme === 'dark' ? 'sun' : 'moon'} />
          </button>

          <div className="nav-user">
            <span className="nav-user-icon"><Icon name="user" /></span>
            <span className="nav-user-name">{username}</span>
          </div>

          <button type="button" className="nav-icon-btn" onClick={onLogout} aria-label="Déconnexion" title="Déconnexion">
            <Icon name="logout" />
          </button>
        </div>
      </header>

      {open && (
        <div className="cmdk-backdrop" role="dialog" aria-modal="true" onMouseDown={(e) => { if (e.target === e.currentTarget) setOpen(false); }}>
          <div className="cmdk">
            <div className="cmdk-input-row">
              <span className="cmdk-input-icon"><Icon name="search" /></span>
              <input
                autoFocus
                className="cmdk-input"
                value={q}
                onChange={(e) => setQ(e.target.value)}
                placeholder="Rechercher une page, MSISDN, keyword..."
              />
              <span className="kbd">ESC</span>
            </div>
            <div className="cmdk-list">
              {results.slice(0, 30).map((r) => (
                <button
                  type="button"
                  key={r.id}
                  className="cmdk-item"
                  onClick={() => {
                    setOpen(false);
                    setQ('');
                    onNavigate?.(r.path);
                  }}
                >
                  <span className="cmdk-item-title">{r.label}</span>
                  <span className="cmdk-item-hint">{r.hint}</span>
                </button>
              ))}
              {results.length === 0 && (
                <div className="cmdk-empty">Aucun résultat</div>
              )}
            </div>
          </div>
        </div>
      )}
    </>
  );
}
