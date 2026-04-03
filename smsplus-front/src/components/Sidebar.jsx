/* eslint-disable react/prop-types */
export default function Sidebar({ user, activePage, onNavigate }) {
  const isAdmin    = user.role === 'ADMIN';
  const isOp       = user.role === 'ANALYSTE_OP' || isAdmin;
  const isBuss     = user.role === 'ANALYSTE_BUSS' || isAdmin;

  const menuItems = [
    { id: 'dashboard',    label: 'Dashboard',          icon: '📊', show: true },
    { id: 'services',     label: 'Services',            icon: '📋', show: isAdmin },
    { id: 'msisdn',       label: 'Recherche MSISDN',    icon: '🔍', show: isOp },
    { id: 'revenus',      label: 'Revenus détaillés',   icon: '💰', show: isBuss },
    { id: 'alerts',       label: 'Alertes',             icon: '🔔', show: true },
    { id: 'users',        label: 'Utilisateurs',        icon: '👥', show: isAdmin },
  ];

  return (
    <div style={{
      width: '240px',
      minHeight: 'calc(100vh - 60px)',
      background: 'linear-gradient(180deg, #0f1c5e 0%, #1a237e 100%)',
      padding: '1.5rem 0',
      flexShrink: 0,
    }}>
      {menuItems.filter(m => m.show).map(item => (
        <button
          key={item.id}
          onClick={() => onNavigate(item.id)}
          style={{
            width: '100%',
            display: 'flex',
            alignItems: 'center',
            gap: '0.75rem',
            padding: '0.875rem 1.5rem',
            background: activePage === item.id
              ? 'rgba(255,255,255,0.15)'
              : 'transparent',
            color: activePage === item.id ? 'white' : 'rgba(255,255,255,0.7)',
            border: 'none',
            borderLeft: activePage === item.id
              ? '3px solid #42a5f5'
              : '3px solid transparent',
            cursor: 'pointer',
            fontSize: '0.95rem',
            fontWeight: activePage === item.id ? 600 : 400,
            textAlign: 'left',
            transition: 'all 0.2s',
          }}
          onMouseEnter={e => {
            if (activePage !== item.id) {
              e.currentTarget.style.background = 'rgba(255,255,255,0.08)';
              e.currentTarget.style.color = 'white';
            }
          }}
          onMouseLeave={e => {
            if (activePage !== item.id) {
              e.currentTarget.style.background = 'transparent';
              e.currentTarget.style.color = 'rgba(255,255,255,0.7)';
            }
          }}
        >
          <span style={{ fontSize: '1.1rem' }}>{item.icon}</span>
          {item.label}
        </button>
      ))}

      {/* Role badge */}
      <div style={{
        margin: '2rem 1rem 0',
        padding: '0.75rem',
        background: 'rgba(255,255,255,0.08)',
        borderRadius: '10px',
        fontSize: '0.78rem',
        color: 'rgba(255,255,255,0.6)',
        textAlign: 'center',
      }}>
        <div style={{ fontSize: '0.7rem', marginBottom: '0.25rem', textTransform: 'uppercase', letterSpacing: '1px' }}>Rôle</div>
        <div style={{ color: '#42a5f5', fontWeight: 600 }}>{user.role.replace('_', ' ')}</div>
      </div>
    </div>
  );
}
