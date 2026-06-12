 
import { Suspense, lazy, useEffect, useLayoutEffect, useState } from 'react';
import { BrowserRouter, Navigate, Route, Routes, useNavigate } from 'react-router-dom';
import AppShell from './layout/AppShell';
import { clearAuth, fetchMe, syncStoredUserWithToken } from './lib/auth';
import { registerAuthNavigate } from './lib/authNavigation';
import { isValidSessionUser } from './lib/sessionUser';
import { fetchNotificationCount } from './api/notifications';
import ToastContainer from './components/ToastContainer';

const Login = lazy(() => import('./pages/Login'));
const Dashboard = lazy(() => import('./pages/Dashboard'));
const Services = lazy(() => import('./pages/Services'));
const MsisdnSearch = lazy(() => import('./pages/MsisdnSearch'));
const Revenus = lazy(() => import('./pages/Revenus'));
const Alerts = lazy(() => import('./pages/Alerts'));
const AiChatWidget = lazy(() => import('./components/AiChatWidget'));
const Users = lazy(() => import('./pages/Users'));
const CdrOcc = lazy(() => import('./pages/CdrOcc'));
const CdrMmg = lazy(() => import('./pages/CdrMmg'));
const Predictions = lazy(() => import('./pages/Predictions'));
const Landing = lazy(() => import('./pages/Landing'));
const Notifications = lazy(() => import('./pages/Notifications'));
const Duplicates = lazy(() => import('./pages/Duplicates'));
const AuditLog = lazy(() => import('./pages/AuditLog'));
const DataLineage = lazy(() => import('./pages/DataLineage'));
const EtlPerformance = lazy(() => import('./pages/EtlPerformance'));

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
      <Suspense fallback={<BootSkeleton />}>
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
            <Route
              path="/duplicates"
              element={(
                <RoleGuard user={user} roles={['ADMIN', 'ANALYSTE_OP']}>
                  <Duplicates />
                </RoleGuard>
              )}
            />
            <Route path="/notifications" element={<Notifications />} />
            <Route
              path="/audit-logs"
              element={(
                <RoleGuard user={user} roles={['ADMIN']}>
                  <AuditLog />
                </RoleGuard>
              )}
            />

            <Route
              path="/data-lineage"
              element={<DataLineage />}
            />
            <Route
              path="/etl-performance"
              element={(
                <RoleGuard user={user} roles={['ADMIN', 'ANALYSTE_OP']}>
                  <EtlPerformance />
                </RoleGuard>
              )}
            />
          </Route>

          <Route path="*" element={<Navigate to={user ? '/dashboard' : '/'} replace />} />
        </Routes>
      </Suspense>
      {user && (
        <Suspense fallback={null}>
          <AiChatWidget user={user} />
        </Suspense>
      )}
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
