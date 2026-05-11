/* eslint-disable react/prop-types */
import { useMemo, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import {
  clearReadNotifications,
  deleteNotification,
  fetchNotifications,
  markAllNotificationsRead,
  markNotificationRead,
} from '../api/notifications';

/* ── Helpers ── */
function relativeTime(value) {
  const now = Date.now();
  const ts = new Date(value).getTime();
  const diffMin = Math.max(1, Math.floor((now - ts) / 60000));
  if (diffMin < 60) return `il y a ${diffMin} min`;
  const diffH = Math.floor(diffMin / 60);
  if (diffH < 24) return `il y a ${diffH}h`;
  return `il y a ${Math.floor(diffH / 24)}j`;
}

function dayGroup(date) {
  const d = new Date(date);
  const now = new Date();
  const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
  const target = new Date(d.getFullYear(), d.getMonth(), d.getDate());
  const diff = Math.floor((today - target) / (24 * 3600 * 1000));
  if (diff === 0) return "Aujourd'hui";
  if (diff === 1) return 'Hier';
  return 'Plus ancien';
}

const TYPE_COLOR = {
  anomalie: '#dc2626',
  alerte:   '#f59e0b',
  import:   '#16a34a',
  rapport:  '#3b82f6',
  systeme:  '#64748b',
};

/* ── SVG Icons — no emojis ── */
function BellIcon() {
  return (
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
         strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
      <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" />
      <path d="M13.73 21a2 2 0 0 1-3.46 0" />
    </svg>
  );
}

function TrashIcon() {
  return (
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
         strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
      <polyline points="3 6 5 6 21 6" />
      <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6" />
      <line x1="10" y1="11" x2="10" y2="17" />
      <line x1="14" y1="11" x2="14" y2="17" />
    </svg>
  );
}

function TypeDot({ type }) {
  const color = TYPE_COLOR[type] || '#64748b';
  return (
    <span style={{
      display: 'inline-block',
      width: 9, height: 9,
      borderRadius: '50%',
      background: color,
      flexShrink: 0,
      marginTop: 3,
    }} />
  );
}

/* ── Component ── */
export default function NotificationBell({ unreadCount, setUnreadCount, setSidebarUnread }) {
  const [open, setOpen] = useState(false);
  const [loading, setLoading] = useState(false);
  const [items, setItems] = useState([]);
  const navigate = useNavigate();

  const grouped = useMemo(() => {
    const result = [];
    let current = '';
    for (const item of items) {
      const section = dayGroup(item.created_at);
      if (section !== current) {
        result.push({ type: 'separator', label: section, id: `sep-${section}-${item.id}` });
        current = section;
      }
      result.push({ type: 'item', item });
    }
    return result;
  }, [items]);

  const openDropdown = async () => {
    setOpen((v) => !v);
    if (!open) {
      setLoading(true);
      try {
        const data = await fetchNotifications();
        setItems(data.notifications || []);
        setUnreadCount(data.non_lues || 0);
        setSidebarUnread(data.non_lues || 0);
      } catch {
        setItems([]);
      } finally {
        setLoading(false);
      }
    }
  };

  const handleRead = async (notification) => {
    try {
      if (!notification.lue) {
        await markNotificationRead(notification.id);
        setItems((prev) => prev.map((item) =>
          item.id === notification.id ? { ...item, lue: true, lue_at: new Date().toISOString() } : item
        ));
        const next = Math.max(0, unreadCount - 1);
        setUnreadCount(next);
        setSidebarUnread(next);
      }
      if (notification.action_url) {
        navigate(notification.action_url);
        setOpen(false);
      }
    } catch { /* silent */ }
  };

  const onMarkAllRead = async () => {
    try {
      await markAllNotificationsRead();
      setItems((prev) => prev.map((item) => ({ ...item, lue: true })));
      setUnreadCount(0);
      setSidebarUnread(0);
    } catch { /* silent */ }
  };

  const onDelete = async (id) => {
    try {
      await deleteNotification(id);
      setItems((prev) => prev.filter((item) => item.id !== id));
    } catch { /* silent */ }
  };

  const onClearRead = async () => {
    try {
      await clearReadNotifications();
      setItems((prev) => prev.filter((item) => !item.lue));
    } catch { /* silent */ }
  };

  const hasCritical = items.some((item) => !item.lue && item.priorite === 'critique');
  const badgeLabel = unreadCount > 99 ? '99+' : String(unreadCount);

  return (
    <div className="notif-wrap">
      <button
        type="button"
        className={`nav-icon-btn notif-bell${hasCritical ? ' pulse' : ''}`}
        onClick={openDropdown}
        title="Notifications"
      >
        <BellIcon />
        {unreadCount > 0 && (
          <span className="notif-badge">{badgeLabel}</span>
        )}
      </button>

      {open && (
        <div className="notif-dropdown">
          <div className="notif-head">
            <div>
              <strong>Notifications</strong>
              <span style={{ marginLeft: 6, color: 'var(--text-muted)', fontSize: '0.8rem' }}>
                ({unreadCount} non lues)
              </span>
            </div>
            <div style={{ display: 'flex', gap: 6 }}>
              <button type="button" className="btn btn-ghost" style={{ padding: '0.2rem 0.5rem', fontSize: '0.76rem' }} onClick={onMarkAllRead}>
                Tout lu
              </button>
              <button type="button" className="btn btn-ghost" style={{ padding: '0.2rem 0.5rem', fontSize: '0.76rem' }} onClick={onClearRead}>
                Vider lues
              </button>
            </div>
          </div>

          <div className="notif-list">
            {loading && (
              <p style={{ padding: '1rem', margin: 0, color: 'var(--text-muted)', fontSize: '0.85rem' }}>
                Chargement...
              </p>
            )}
            {!loading && items.length === 0 && (
              <p style={{ padding: '1rem', margin: 0, color: 'var(--text-muted)', fontSize: '0.85rem', textAlign: 'center' }}>
                Aucune notification
              </p>
            )}
            {!loading && grouped.map((entry) => {
              if (entry.type === 'separator') {
                return <div key={entry.id} className="notif-sep">{entry.label}</div>;
              }
              const item = entry.item;
              return (
                <div key={item.id} className={`notif-item${item.lue ? ' read' : ' unread'}`}>
                  <button type="button" className="notif-content" onClick={() => handleRead(item)}>
                    <TypeDot type={item.type} />
                    <span className="notif-text">
                      <strong style={{ fontWeight: item.lue ? 600 : 800 }}>{item.titre}</strong>
                      <span>{String(item.message || '').slice(0, 80)}</span>
                      <small>{relativeTime(item.created_at)}</small>
                    </span>
                  </button>
                  <button type="button" className="notif-delete" onClick={() => onDelete(item.id)} title="Supprimer">
                    <TrashIcon />
                  </button>
                </div>
              );
            })}
          </div>

          <div className="notif-foot">
            <button
              type="button"
              className="btn btn-ghost"
              style={{ width: '100%', justifyContent: 'center' }}
              onClick={() => { navigate('/notifications'); setOpen(false); }}
            >
              Voir toutes les notifications
            </button>
          </div>
        </div>
      )}
    </div>
  );
}
