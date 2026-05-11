/* eslint-disable react/prop-types */
import { Outlet, useLocation, useNavigate } from 'react-router-dom';
import { useEffect, useState } from 'react';
import Sidebar from '../components/Sidebar';
import Navbar from '../components/Navbar';
import { applyTheme } from '../theme';

const pageTitles = {
  '/': 'Tableau de bord',
  '/dashboard': 'Tableau de bord',
  '/services': 'Services VAS',
  '/msisdn': 'Recherche MSISDN',
  '/cdr/occ': 'CDR OCC',
  '/cdr/mmg': 'CDR MMG',
  '/revenus': 'Revenus détaillés',
  '/alerts': 'Alertes fraude',
  '/predictions': 'Prédictions IA',
  '/notifications': 'Notifications',
  '/users': 'Utilisateurs',
  '/etl-monitor': 'ETL Monitor',
  '/duplicates': 'Doublons CDR',
  '/audit-logs': 'Logs d\'audit',

  '/data-lineage': 'Data Lineage Interactive',
  '/etl-performance': 'Performance ETL',
};

export default function AppShell({ user, onLogout, unreadCount = 0, setUnreadCount, setSidebarUnread }) {
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
    if (p.startsWith('/services')) return 'services';
    if (p.startsWith('/msisdn')) return 'msisdn';
    if (p.startsWith('/cdr/occ')) return 'cdr-occ';
    if (p.startsWith('/cdr/mmg')) return 'cdr-mmg';
    if (p.startsWith('/revenus')) return 'revenus';
    if (p.startsWith('/alerts')) return 'alerts';
    if (p.startsWith('/predictions')) return 'predictions';
    if (p.startsWith('/notifications')) return 'notifications';
    if (p.startsWith('/users')) return 'users';
    if (p.startsWith('/etl-monitor')) return 'etl-monitor';
    if (p.startsWith('/duplicates')) return 'duplicates';
    if (p.startsWith('/audit-logs')) return 'audit-logs';

    if (p.startsWith('/data-lineage')) return 'data-lineage';
    if (p.startsWith('/etl-performance')) return 'etl-performance';
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
          unreadCount={unreadCount}
          onNavigate={(id) => {
            const map = {
              dashboard: '/dashboard',
              services: '/services',
              msisdn: '/msisdn',
              'cdr-occ': '/cdr/occ',
              'cdr-mmg': '/cdr/mmg',
              revenus: '/revenus',
              alerts: '/alerts',
              predictions: '/predictions',
              notifications: '/notifications',
              users: '/users',
              'etl-monitor': '/etl-monitor',
              duplicates: '/duplicates',
              'audit-logs': '/audit-logs',

              'data-lineage': '/data-lineage',
              'etl-performance': '/etl-performance',
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
              unreadCount={unreadCount}
              setUnreadCount={setUnreadCount}
              setSidebarUnread={setSidebarUnread}
            />
            <Outlet />
          </div>
        </main>
      </div>
    </div>
  );
}

