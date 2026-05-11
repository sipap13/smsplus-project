/* eslint-disable react/prop-types */
function SidebarIcon({ name }) {
  const common = { width: 16, height: 16, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', strokeWidth: '1.9', strokeLinecap: 'round', strokeLinejoin: 'round' };
  if (name === 'dashboard') {
    return (
      <svg {...common}><rect x="3" y="3" width="7" height="7" /><rect x="14" y="3" width="7" height="7" /><rect x="3" y="14" width="7" height="7" /><rect x="14" y="14" width="7" height="7" /></svg>
    );
  }
  if (name === 'services') {
    return (
      <svg {...common}><path d="M4 7h16M4 12h16M4 17h16" /></svg>
    );
  }
  if (name === 'users') {
    return (
      <svg {...common}><circle cx="9" cy="8" r="3" /><circle cx="17" cy="9" r="2.5" /><path d="M3.5 19a5.5 5.5 0 0 1 11 0" /><path d="M13.5 19a4 4 0 0 1 7 0" /></svg>
    );
  }
  if (name === 'cdr') {
    return (
      <svg {...common}><ellipse cx="12" cy="6" rx="7.5" ry="3" /><path d="M4.5 6v6c0 1.7 3.4 3 7.5 3s7.5-1.3 7.5-3V6" /><path d="M4.5 12v6c0 1.7 3.4 3 7.5 3s7.5-1.3 7.5-3v-6" /></svg>
    );
  }
  if (name === 'fraud') {
    return (
      <svg {...common}><path d="M12 3l9 16H3L12 3z" /><path d="M12 9v4" /><path d="M12 17h.01" /></svg>
    );
  }
  if (name === 'settings') {
    return (
      <svg {...common}><circle cx="12" cy="12" r="3" /><path d="M19.4 15a1 1 0 0 0 .2 1.1l.1.1a1 1 0 0 1 0 1.4l-1 1a1 1 0 0 1-1.4 0l-.1-.1a1 1 0 0 0-1.1-.2 1 1 0 0 0-.6.9V20a1 1 0 0 1-1 1h-1.5a1 1 0 0 1-1-1v-.2a1 1 0 0 0-.6-.9 1 1 0 0 0-1.1.2l-.1.1a1 1 0 0 1-1.4 0l-1-1a1 1 0 0 1 0-1.4l.1-.1a1 1 0 0 0 .2-1.1 1 1 0 0 0-.9-.6H4a1 1 0 0 1-1-1v-1.5a1 1 0 0 1 1-1h.2a1 1 0 0 0 .9-.6 1 1 0 0 0-.2-1.1l-.1-.1a1 1 0 0 1 0-1.4l1-1a1 1 0 0 1 1.4 0l.1.1a1 1 0 0 0 1.1.2 1 1 0 0 0 .6-.9V4a1 1 0 0 1 1-1h1.5a1 1 0 0 1 1 1v.2a1 1 0 0 0 .6.9 1 1 0 0 0 1.1-.2l.1-.1a1 1 0 0 1 1.4 0l1 1a1 1 0 0 1 0 1.4l-.1.1a1 1 0 0 0-.2 1.1 1 1 0 0 0 .9.6H20a1 1 0 0 1 1 1v1.5a1 1 0 0 1-1 1h-.2a1 1 0 0 0-.4.1" /></svg>
    );
  }
  if (name === 'ai') {
    return (
      <svg {...common}><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z" /></svg>
    );
  }
  if (name === 'predictions') {
    return (
      <svg {...common}><path d="M3 3v18h18" /><path d="M7 16l4-6 4 3 5-8" /></svg>
    );
  }
  if (name === 'duplicates') {
    return (
      <svg {...common}><rect x="3" y="3" width="18" height="18" rx="2" ry="2" /><line x1="9" y1="3" x2="9" y2="21" /><line x1="15" y1="3" x2="15" y2="21" /></svg>
    );
  }
  if (name === 'audit') {
    return (
      <svg {...common}><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" /><circle cx="12" cy="12" r="3" /></svg>
    );
  }
  return <svg {...common}><circle cx="12" cy="12" r="8" /></svg>;
}

export default function Sidebar({ user, activePage, onNavigate, unreadCount = 0 }) {
  const isAdmin    = user.role === 'ADMIN';
  const isOp       = user.role === 'ANALYSTE_OP' || isAdmin;
  const isBuss     = user.role === 'ANALYSTE_BUSS' || isAdmin;

  const menuItems = [
    { id: 'dashboard', label: 'Tableau de bord', icon: 'dashboard', show: true },
    { id: 'services', label: 'Services VAS', icon: 'services', show: isAdmin || isOp },
    { id: 'msisdn', label: 'Recherche MSISDN', icon: 'services', show: isOp || isAdmin },
    { id: 'cdr-occ', label: 'CDR OCC', icon: 'cdr', show: isBuss || isAdmin },
    { id: 'cdr-mmg', label: 'CDR MMG', icon: 'cdr', show: isOp || isAdmin },
    { id: 'revenus', label: 'Revenus détaillés', icon: 'dashboard', show: isBuss || isAdmin },
    { id: 'alerts', label: 'Alertes fraude', icon: 'fraud', show: isOp || isAdmin },
    { id: 'duplicates', label: 'Doublons CDR', icon: 'duplicates', show: isOp || isAdmin },
    { id: 'predictions', label: 'Prédictions IA', icon: 'predictions', show: isBuss || isAdmin },
    { id: 'users', label: 'Utilisateurs', icon: 'users', show: isAdmin },
    { id: 'audit-logs', label: 'Logs d\'audit', icon: 'audit', show: isAdmin },

    { id: 'data-lineage', label: 'Data Lineage', icon: 'predictions', show: true },
    { id: 'etl-performance', label: 'Performance ETL', icon: 'audit', show: isAdmin || isOp },
  ];

  return (
    <div className="app-sidebar">
      <div className="tt-sidebar-brand" onClick={() => onNavigate('dashboard')} style={{ cursor: 'pointer' }}>
        <img src="/tt-logo-sidebar-clean.png" alt="Tunisie Telecom" className="tt-logo sidebar" onError={(e) => { e.target.style.display='none'; }} />
        <div>
          <p className="tt-sidebar-title">Tunisie Telecom</p>
          <p className="tt-sidebar-sub">SMS+ VAS Platform</p>
        </div>
      </div>
      {menuItems.filter(m => m.show).map(item => (
        <button
          key={item.id}
          onClick={() => onNavigate(item.id)}
          className={`sidebar-btn ${activePage === item.id ? 'active' : ''}`}
        >
          <span className="sidebar-icon"><SidebarIcon name={item.icon} /></span>
          <span className="sidebar-label">{item.label}</span>
          {item.id === 'notifications' && unreadCount > 0 ? (
            <span className="notif-badge sidebar">{unreadCount > 99 ? '99+' : unreadCount}</span>
          ) : null}
        </button>
      ))}

      <div className="sidebar-role-badge" style={{ 
        background: 'rgba(11, 102, 195, 0.08)', 
        border: '1px solid rgba(11, 102, 195, 0.2)',
        margin: '1.5rem 0.8rem 0',
        padding: '0.85rem'
      }}>
        <div style={{ fontSize: '0.62rem', marginBottom: '0.2rem', textTransform: 'uppercase', color: 'var(--primary)', opacity: 0.7, fontWeight: 700 }}>Privilèges</div>
        <div style={{ color: 'var(--primary)', fontWeight: 800, fontSize: '0.88rem', letterSpacing: '0.02em' }}>{user.role.replace('_', ' ')}</div>
      </div>
    </div>
  );
}
