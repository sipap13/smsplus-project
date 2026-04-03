import { useState } from 'react';
import Login       from './pages/Login';
import Dashboard   from './pages/Dashboard';
import Services    from './pages/Services';
import MsisdnSearch from './pages/MsisdnSearch';
import Revenus     from './pages/Revenus';
import Alerts      from './pages/Alerts';
import Users       from './pages/Users';
import Sidebar     from './components/Sidebar';

function App() {
  const [user, setUser]         = useState(() => {
    const saved = localStorage.getItem('user');
    return saved ? JSON.parse(saved) : null;
  });
  const [activePage, setActivePage] = useState('dashboard');

  const handleLogin = (userData) => { setUser(userData); setActivePage('dashboard'); };

  const handleLogout = () => {
    localStorage.removeItem('token');
    localStorage.removeItem('user');
    setUser(null);
  };

  if (!user) return <Login onLogin={handleLogin} />;

  const renderPage = () => {
    switch (activePage) {
      case 'dashboard': return <Dashboard user={user} />;
      case 'services':  return <Services />;
      case 'msisdn':    return <MsisdnSearch />;
      case 'revenus':   return <Revenus />;
      case 'alerts':    return <Alerts />;
      case 'users':     return <Users />;
      default:          return <Dashboard user={user} />;
    }
  };

  const pageTitles = {
    dashboard: '📊 Dashboard',
    services:  '📋 Services',
    msisdn:    '🔍 Recherche MSISDN',
    revenus:   '💰 Revenus',
    alerts:    '🔔 Alertes',
    users:     '👥 Utilisateurs',
  };

  return (
    <div style={{ display: 'flex', flexDirection: 'column', height: '100vh', fontFamily: "'Segoe UI', Arial, sans-serif" }}>
      {/* Top Navbar */}
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
        {/* Left — Brand */}
        <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem' }}>
          <div style={{ width: '32px', height: '32px', borderRadius: '8px', background: 'rgba(255,255,255,0.15)', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: '1.1rem' }}>
            📡
          </div>
          <div>
            <span style={{ font: '700 1rem/1 inherit' }}>SMS+</span>
            <span style={{ marginLeft: '0.5rem', opacity: 0.6, fontSize: '0.8rem' }}>Tunisie Telecom</span>
          </div>
          <div style={{ width: '1px', height: '24px', background: 'rgba(255,255,255,0.2)', margin: '0 0.5rem' }} />
          <span style={{ fontSize: '0.9rem', opacity: 0.9 }}>{pageTitles[activePage]}</span>
        </div>

        {/* Right — User info */}
        <div style={{ display: 'flex', alignItems: 'center', gap: '1rem' }}>
          <div style={{ textAlign: 'right' }}>
            <p style={{ margin: 0, fontSize: '0.88rem', fontWeight: 600 }}>{user.email}</p>
            <p style={{ margin: 0, fontSize: '0.75rem', opacity: 0.6 }}>{user.direction || 'Assurance et Fraude'}</p>
          </div>
          <div style={{ width: '36px', height: '36px', borderRadius: '50%', background: 'rgba(255,255,255,0.15)', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: '1rem' }}>
            {user.role === 'ADMIN' ? '👑' : user.role === 'ANALYSTE_OP' ? '🔬' : '📊'}
          </div>
          <button
            onClick={handleLogout}
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

      {/* Body: Sidebar + Content */}
      <div style={{ display: 'flex', flex: 1, overflow: 'hidden' }}>
        <Sidebar user={user} activePage={activePage} onNavigate={setActivePage} />
        <main style={{ flex: 1, overflowY: 'auto', background: '#f8f9ff' }}>
          {renderPage()}
        </main>
      </div>
    </div>
  );
}

export default App;