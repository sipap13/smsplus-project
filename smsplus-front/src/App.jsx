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

  if (bootLoading) {
    return (
      <div style={{ minHeight: '100vh', display: 'flex', alignItems: 'center', justifyContent: 'center', background: '#f8f9ff', color: '#1a237e', fontWeight: 700 }}>
        Chargement...
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
          <Route path="services" element={<Services />} />
          <Route path="msisdn" element={<MsisdnSearch />} />
          <Route path="revenus" element={<Revenus />} />
          <Route path="alerts" element={<Alerts />} />
          <Route path="users" element={<Users />} />
        </Route>

        <Route path="*" element={<Navigate to={user ? '/dashboard' : '/login'} replace />} />
      </Routes>
    </BrowserRouter>
  );
}

export default App;