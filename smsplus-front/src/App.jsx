import { useEffect, useState } from 'react';
import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom';
import Login from './pages/Login';
import Dashboard from './pages/Dashboard';
import Services from './pages/Services';
import MsisdnSearch from './pages/MsisdnSearch';
import Revenus from './pages/Revenus';
import Alerts from './pages/Alerts';
import Users from './pages/Users';
import AppShell from './layout/AppShell';
import { clearAuth, fetchMe, getStoredUser } from './lib/auth';
import SosDashboard from './pages/SosDashboard';
import CdrOcc from './pages/CdrOcc';
import CdrMmg from './pages/CdrMmg';

function RoleGuard({ user, roles, children }) {
  if (!user || !roles.includes(user.role)) {
    return <Navigate to="/dashboard" replace />;
  }
  return children;
}

function App() {
  const [user, setUser] = useState(() => getStoredUser());
  const [bootError, setBootError] = useState('');
  const [bootLoading, setBootLoading] = useState(() => Boolean(localStorage.getItem('token')) && !getStoredUser());

  const handleLogin = (userData) => { setUser(userData); };

  const handleLogout = () => {
    clearAuth();
    setUser(null);
  };

  useEffect(() => {
    let mounted = true;
    const boot = async () => {
      if (!localStorage.getItem('token')) return;
      if (getStoredUser()) return;
      setBootLoading(true);
      setBootError('');
      try {
        const me = await fetchMe();
        if (!mounted) return;
        setUser(me);
      } catch {
        if (!mounted) return;
        clearAuth();
        setUser(null);
        setBootError("Session expirée. Merci de vous reconnecter.");
      } finally {
        if (mounted) setBootLoading(false);
      }
    };
    boot();
    return () => { mounted = false; };
  }, []);

  useEffect(() => {
    const savedDir = localStorage.getItem('ui_dir');
    document.documentElement.setAttribute('dir', savedDir === 'rtl' ? 'rtl' : 'ltr');
  }, []);

  if (bootLoading) {
    return (
      <div style={{ minHeight: '100vh', display: 'grid', placeItems: 'center', background: 'var(--bg-page)' }}>
        <div className="panel" style={{ width: 'min(92vw, 420px)', padding: '1rem' }}>
          <div className="skeleton" style={{ height: '16px', width: '55%', marginBottom: '0.8rem' }} />
          <div className="skeleton" style={{ height: '10px', width: '90%', marginBottom: '0.45rem' }} />
          <div className="skeleton" style={{ height: '10px', width: '76%' }} />
        </div>
      </div>
    );
  }

  return (
    <BrowserRouter>
      <Routes>
        <Route
          path="/login"
          element={user ? <Navigate to="/dashboard" replace /> : <Login onLogin={handleLogin} bootError={bootError} />}
        />

        <Route
          path="/"
          element={user ? <AppShell user={user} onLogout={handleLogout} /> : <Navigate to="/login" replace />}
        >
          <Route index element={<Navigate to="/dashboard" replace />} />
          <Route path="dashboard" element={<Dashboard user={user} />} />
          <Route path="sos" element={<SosDashboard />} />
          <Route
            path="services"
            element={(
              <RoleGuard user={user} roles={['ADMIN']}>
                <Services />
              </RoleGuard>
            )}
          />
          <Route
            path="msisdn"
            element={(
              <RoleGuard user={user} roles={['ADMIN', 'ANALYSTE_OP']}>
                <MsisdnSearch />
              </RoleGuard>
            )}
          />
          <Route path="revenus" element={<Revenus />} />
          <Route
            path="cdr/occ"
            element={(
              <RoleGuard user={user} roles={['ADMIN', 'ANALYSTE_BUSS']}>
                <CdrOcc />
              </RoleGuard>
            )}
          />
          <Route
            path="cdr/mmg"
            element={(
              <RoleGuard user={user} roles={['ADMIN', 'ANALYSTE_OP']}>
                <CdrMmg />
              </RoleGuard>
            )}
          />
          <Route
            path="alerts"
            element={(
              <RoleGuard user={user} roles={['ADMIN', 'ANALYSTE_OP']}>
                <Alerts />
              </RoleGuard>
            )}
          />
          <Route
            path="users"
            element={(
              <RoleGuard user={user} roles={['ADMIN']}>
                <Users user={user} />
              </RoleGuard>
            )}
          />
        </Route>

        <Route path="*" element={<Navigate to={user ? '/dashboard' : '/login'} replace />} />
      </Routes>
    </BrowserRouter>
  );
}

export default App;