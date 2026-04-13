/* eslint-disable react/prop-types */
import { Outlet, useLocation, useNavigate } from 'react-router-dom';
import { useEffect, useState } from 'react';
import Sidebar from '../components/Sidebar';
import Navbar from '../components/Navbar';
import { applyTheme } from '../theme';

const pageTitles = {
  '/': 'Tableau de bord',
  '/dashboard': 'Tableau de bord',
  '/sos': 'SOS Solde & Data',
  '/services': 'Services VAS',
  '/msisdn': 'Recherche MSISDN',
  '/cdr/occ': 'CDR OCC',
  '/cdr/mmg': 'CDR MMG',
  '/revenus': 'Revenus détaillés',
  '/alerts': 'Alertes fraude',
  '/users': 'Utilisateurs',
};

export default function AppShell({ user, onLogout }) {
  const location = useLocation();
  const navigate = useNavigate();
  const [theme, setTheme] = useState(() => {
    try {
      return localStorage.getItem('theme') || 'light';
    } catch {
      return 'light';
    }
  });

  useEffect(() => {
    applyTheme(theme);
  }, [theme]);

  const toggleTheme = () => {
    setTheme((prev) => (prev === 'dark' ? 'light' : 'dark'));
  };

  const activePage = (() => {
    const p = location.pathname;
    if (p === '/' || p === '/dashboard') return 'dashboard';
    if (p.startsWith('/sos')) return 'sos';
    if (p.startsWith('/services')) return 'services';
    if (p.startsWith('/msisdn')) return 'msisdn';
    if (p.startsWith('/cdr/occ')) return 'cdr-occ';
    if (p.startsWith('/cdr/mmg')) return 'cdr-mmg';
    if (p.startsWith('/revenus')) return 'revenus';
    if (p.startsWith('/alerts')) return 'alerts';
    if (p.startsWith('/users')) return 'users';
    return 'dashboard';
  })();

  const title = pageTitles[location.pathname] || pageTitles[`/${activePage}`] || 'Tableau de bord';
  const breadcrumb = `Accueil / ${title}`;

  return (
    <div className="app-shell">
      <div className="app-body">
        <Sidebar
          user={user}
          activePage={activePage}
          onNavigate={(id) => {
            const map = {
              dashboard: '/dashboard',
              sos: '/sos',
              services: '/services',
              msisdn: '/msisdn',
              'cdr-occ': '/cdr/occ',
              'cdr-mmg': '/cdr/mmg',
              revenus: '/revenus',
              alerts: '/alerts',
              users: '/users',
            };
            navigate(map[id] || '/dashboard');
          }}
        />
        <main className="app-main">
          <div className="content-wrap">
            <Navbar
              title={title}
              breadcrumb={breadcrumb}
              user={user}
              onLogout={onLogout}
              theme={theme}
              onToggleTheme={toggleTheme}
              onNavigate={(path) => navigate(path)}
            />
            <Outlet />
          </div>
        </main>
      </div>
    </div>
  );
}

