/* eslint-disable react/prop-types */
import { Outlet, useLocation, useNavigate } from 'react-router-dom';
import Sidebar from '../components/Sidebar';

const pageTitles = {
  '/': '📊 Dashboard',
  '/dashboard': '📊 Dashboard',
  '/services': '📋 Services',
  '/msisdn': '🔍 Recherche MSISDN',
  '/revenus': '💰 Revenus',
  '/alerts': '🔔 Alertes',
  '/users': '👥 Utilisateurs',
};

export default function AppShell({ user, onLogout }) {
  const location = useLocation();
  const navigate = useNavigate();

  const activePage = (() => {
    const p = location.pathname;
    if (p === '/' || p === '/dashboard') return 'dashboard';
    if (p.startsWith('/services')) return 'services';
    if (p.startsWith('/msisdn')) return 'msisdn';
    if (p.startsWith('/revenus')) return 'revenus';
    if (p.startsWith('/alerts')) return 'alerts';
    if (p.startsWith('/users')) return 'users';
    return 'dashboard';
  })();

  const title = pageTitles[location.pathname] || pageTitles[`/${activePage}`] || '📊 Dashboard';

  return (
    <div style={{ display: 'flex', flexDirection: 'column', height: '100vh', fontFamily: "'Segoe UI', Arial, sans-serif" }}>
      <div style={{
        background: 'linear-gradient(90deg, #0f1c5e 0%, #1a237e 60%, #0f3460 100%)',
        color: 'white',
        padding: '0 1.5rem',
        height: '60px',
        display: 'flex',
        justifyContent: 'space-between',
        alignItems: 'center',
        flexShrink: 0,
        boxShadow: '0 2px 12px rgba(0,0,0,0.3)',
        zIndex: 100,
      }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem' }}>
          <div style={{ width: '32px', height: '32px', borderRadius: '8px', background: 'rgba(255,255,255,0.15)', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: '1.1rem' }}>
            📡
          </div>
          <div>
            <span style={{ font: '700 1rem/1 inherit' }}>SMS+</span>
            <span style={{ marginLeft: '0.5rem', opacity: 0.6, fontSize: '0.8rem' }}>Tunisie Telecom</span>
          </div>
          <div style={{ width: '1px', height: '24px', background: 'rgba(255,255,255,0.2)', margin: '0 0.5rem' }} />
          <span style={{ fontSize: '0.9rem', opacity: 0.9 }}>{title}</span>
        </div>

        <div style={{ display: 'flex', alignItems: 'center', gap: '1rem' }}>
          <div style={{ textAlign: 'right' }}>
            <p style={{ margin: 0, fontSize: '0.88rem', fontWeight: 600 }}>{user.email}</p>
            <p style={{ margin: 0, fontSize: '0.75rem', opacity: 0.6 }}>{user.direction || 'Assurance et Fraude'}</p>
          </div>
          <div style={{ width: '36px', height: '36px', borderRadius: '50%', background: 'rgba(255,255,255,0.15)', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: '1rem' }}>
            {user.role === 'ADMIN' ? '👑' : user.role === 'ANALYSTE_OP' ? '🔬' : '📊'}
          </div>
          <button
            onClick={onLogout}
            style={{
              background: 'rgba(255,255,255,0.1)', color: 'white',
              border: '1px solid rgba(255,255,255,0.25)', borderRadius: '8px',
              padding: '0.4rem 1rem', cursor: 'pointer', fontSize: '0.85rem',
              fontWeight: 600, transition: 'background 0.2s',
            }}
            onMouseEnter={e => e.currentTarget.style.background = 'rgba(255,255,255,0.2)'}
            onMouseLeave={e => e.currentTarget.style.background = 'rgba(255,255,255,0.1)'}
          >
            🚪 Déconnexion
          </button>
        </div>
      </div>

      <div style={{ display: 'flex', flex: 1, overflow: 'hidden' }}>
        <Sidebar
          user={user}
          activePage={activePage}
          onNavigate={(id) => {
            const map = {
              dashboard: '/dashboard',
              services: '/services',
              msisdn: '/msisdn',
              revenus: '/revenus',
              alerts: '/alerts',
              users: '/users',
            };
            navigate(map[id] || '/dashboard');
          }}
        />
        <main style={{ flex: 1, overflowY: 'auto', background: '#f8f9ff' }}>
          <Outlet />
        </main>
      </div>
    </div>
  );
}

