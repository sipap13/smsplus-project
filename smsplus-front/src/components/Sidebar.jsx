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
  return <svg {...common}><circle cx="12" cy="12" r="8" /></svg>;
}

export default function Sidebar({ user, activePage, onNavigate }) {
  const isAdmin    = user.role === 'ADMIN';
  const isOp       = user.role === 'ANALYSTE_OP' || isAdmin;
  const isBuss     = user.role === 'ANALYSTE_BUSS' || isAdmin;

  const menuItems = [
    { id: 'dashboard', label: 'Tableau de bord', icon: 'dashboard', show: true },
    { id: 'sos', label: 'SOS Solde & Data', icon: 'settings', show: true },
    { id: 'services', label: 'Services VAS', icon: 'services', show: isAdmin || isOp },
    { id: 'msisdn', label: 'Recherche MSISDN', icon: 'services', show: isOp },
    { id: 'cdr-occ', label: 'CDR OCC', icon: 'cdr', show: isBuss || isOp },
    { id: 'cdr-mmg', label: 'CDR MMG', icon: 'cdr', show: isOp },
    { id: 'revenus', label: 'Revenus détaillés', icon: 'dashboard', show: isBuss },
    { id: 'alerts', label: 'Alertes fraude', icon: 'fraud', show: isOp || isAdmin },
    { id: 'users', label: 'Utilisateurs', icon: 'users', show: isAdmin },
  ];

  return (
    <div className="app-sidebar">
      <div className="tt-sidebar-brand">
        <img src="/tt-logo-sidebar-clean.png" alt="Tunisie Telecom" className="tt-logo sidebar" />
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
        </button>
      ))}

      <div className="sidebar-role-badge">
        <div style={{ fontSize: '0.7rem', marginBottom: '0.25rem', textTransform: 'uppercase', letterSpacing: '1px' }}>Role</div>
        <div style={{ color: '#ffffff', fontWeight: 700 }}>{user.role.replace('_', ' ')}</div>
      </div>
    </div>
  );
}
