/* eslint-disable react/prop-types */
import { useEffect, useLayoutEffect, useState } from 'react';
import { BrowserRouter, Navigate, Route, Routes, useNavigate } from 'react-router-dom';
import Login from './pages/Login';
import Dashboard from './pages/Dashboard';
import Services from './pages/Services';
import MsisdnSearch from './pages/MsisdnSearch';
import Revenus from './pages/Revenus';
import Alerts from './pages/Alerts';
import AiChatWidget from './components/AiChatWidget';
import Users from './pages/Users';
import AppShell from './layout/AppShell';
import { clearAuth, fetchMe, syncStoredUserWithToken } from './lib/auth';
import { registerAuthNavigate } from './lib/authNavigation';
import { isValidSessionUser } from './lib/sessionUser';
import CdrOcc from './pages/CdrOcc';
import CdrMmg from './pages/CdrMmg';
import Predictions from './pages/Predictions';
import Import from './pages/Import';
import Landing from './pages/Landing';
import Notifications from './pages/Notifications';
import { fetchNotificationCount } from './api/notifications';
import ToastContainer from './components/ToastContainer';

function RoleGuard({ user, roles, children }) {
  if (!user || !roles.includes(user.role)) {
    return <Navigate to="/dashboard" replace />;
  }
  return children;
}

/** Enregistre navigate() avant tout effet qui appelle l’API (évite window.location et URLs incohérentes). */
function AuthNavigateMount() {
  const navigate = useNavigate();
  useLayoutEffect(() => {
    registerAuthNavigate((to, opts) => navigate(to, opts));
    return () => registerAuthNavigate(null);
  }, [navigate]);
  return null;
}

function BootSkeleton() {
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

function AppRoutes() {
  const [user, setUser] = useState(() => syncStoredUserWithToken());
  const [bootError, setBootError] = useState('');
  const [ready, setReady] = useState(() => !(localStorage.getItem('token') || '').trim());
  const [unreadCount, setUnreadCount] = useState(0);
  const [toasts, setToasts] = useState([]);
  const [lastCritical, setLastCritical] = useState(false);

  const handleLogin = (userData) => {
    if (isValidSessionUser(userData)) setUser(userData);
  };

  const handleLogout = () => {
    clearAuth();
    setUser(null);
  };

  useEffect(() => {
    let cancelled = false;
    const boot = async () => {
      const raw = localStorage.getItem('token');
      const token = (raw || '').trim();
      if (!token) {
        if (raw !== null && !token) localStorage.removeItem('token');
        if (!cancelled) {
          setUser(null);
          setReady(true);
        }
        return;
      }
      if (!cancelled) setReady(false);
      setBootError('');
      try {
        const me = await fetchMe();
        if (!cancelled) setUser(me);
      } catch {
        if (!cancelled) {
          clearAuth();
          setUser(null);
          setBootError("Session expirée. Merci de vous reconnecter.");
        }
      } finally {
        if (!cancelled) setReady(true);
      }
    };
    boot();
    return () => { cancelled = true; };
  }, []);

  useEffect(() => {
    if (!user) {
      return undefined;
    }

    let active = true;
    const poll = async () => {
      try {
        const response = await fetchNotificationCount();
        if (!active) {
          return;
        }
        const nextCount = Number(response.non_lues || 0);
        const hasCritical = Boolean(response.has_critique);
        setUnreadCount(nextCount);
        if (hasCritical && !lastCritical) {
          setToasts((prev) => [
            ...prev,
            {
              id: Date.now(),
              type: 'warning',
              title: 'Notification critique',
              message: 'Une nouvelle notification critique est disponible.',
            },
          ]);
        }
        setLastCritical(hasCritical);
      } catch {
        // ignore polling errors
      }
    };

    poll();
    const interval = setInterval(poll, 30000);
    return () => {
      active = false;
      clearInterval(interval);
    };
  }, [user, lastCritical]);

  useEffect(() => {
    const savedDir = localStorage.getItem('ui_dir');
    document.documentElement.setAttribute('dir', savedDir === 'rtl' ? 'rtl' : 'ltr');
  }, []);

  if (!ready) return <BootSkeleton />;

  return (
    <>
      <Routes>
        <Route path="/" element={user ? <Navigate to="/dashboard" replace /> : <Landing bootError={bootError} />} />
        <Route
          path="/login"
          element={user ? <Navigate to="/dashboard" replace /> : <Login onLogin={handleLogin} bootError={bootError} />}
        />

        <Route
          element={user ? <AppShell user={user} onLogout={handleLogout} unreadCount={unreadCount} setUnreadCount={setUnreadCount} setSidebarUnread={setUnreadCount} /> : <Navigate to="/" replace />}
        >
        <Route path="/dashboard" element={<Dashboard user={user} />} />
        <Route
          path="/services"
          element={(
            <RoleGuard user={user} roles={['ADMIN']}>
              <Services />
            </RoleGuard>
          )}
        />
        <Route
          path="/msisdn"
          element={(
            <RoleGuard user={user} roles={['ADMIN', 'ANALYSTE_OP']}>
              <MsisdnSearch />
            </RoleGuard>
          )}
        />
        <Route path="/revenus" element={<Revenus />} />
        <Route
          path="/cdr/occ"
          element={(
            <RoleGuard user={user} roles={['ADMIN', 'ANALYSTE_BUSS']}>
              <CdrOcc />
            </RoleGuard>
          )}
        />
        <Route
          path="/cdr/mmg"
          element={(
            <RoleGuard user={user} roles={['ADMIN', 'ANALYSTE_OP']}>
              <CdrMmg />
            </RoleGuard>
          )}
        />
        <Route
          path="/alerts"
          element={(
            <RoleGuard user={user} roles={['ADMIN', 'ANALYSTE_OP']}>
              <Alerts />
            </RoleGuard>
          )}
        />
        <Route
          path="/users"
          element={(
            <RoleGuard user={user} roles={['ADMIN']}>
              <Users user={user} />
            </RoleGuard>
          )}
        />
        <Route
          path="/predictions"
          element={(
            <RoleGuard user={user} roles={['ADMIN', 'ANALYSTE_BUSS']}>
              <Predictions />
            </RoleGuard>
          )}
        />
        <Route path="/notifications" element={<Notifications />} />
        <Route
          path="/imports"
          element={(
            <RoleGuard user={user} roles={['ADMIN']}>
              <Import />
            </RoleGuard>
          )}
        />
      </Route>

      <Route path="*" element={<Navigate to={user ? '/dashboard' : '/'} replace />} />
      </Routes>
      {user && <AiChatWidget user={user} />}
      <ToastContainer toasts={toasts} onClose={(id) => setToasts((prev) => prev.filter((t) => t.id !== id))} />
    </>
  );
}

export default function App() {
  return (
    <BrowserRouter>
      <AuthNavigateMount />
      <AppRoutes />
    </BrowserRouter>
  );
}
