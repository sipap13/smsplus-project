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

function relativeTime(value) {
  const now = Date.now();
  const ts = new Date(value).getTime();
  const diffMin = Math.max(1, Math.floor((now - ts) / 60000));
  if (diffMin < 60) {
    return `il y a ${diffMin} min`;
  }
  const diffH = Math.floor(diffMin / 60);
  if (diffH < 24) {
    return `il y a ${diffH}h`;
  }
  return `il y a ${Math.floor(diffH / 24)}j`;
}

function dayGroup(date) {
  const d = new Date(date);
  const now = new Date();
  const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
  const target = new Date(d.getFullYear(), d.getMonth(), d.getDate());
  const diff = Math.floor((today - target) / (24 * 3600 * 1000));
  if (diff === 0) {
    return "Aujourd'hui";
  }
  if (diff === 1) {
    return 'Hier';
  }
  return 'Plus ancien';
}

const TYPE_ICON = {
  anomalie: { icon: '🔴', color: '#dc2626' },
  alerte: { icon: '🟠', color: '#f59e0b' },
  import: { icon: '🟢', color: '#16a34a' },
  rapport: { icon: '🔵', color: '#3b82f6' },
  systeme: { icon: '⚪', color: '#64748b' },
};

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
        setItems((prev) => prev.map((item) => (item.id === notification.id ? { ...item, lue: true, lue_at: new Date().toISOString() } : item)));
        const next = Math.max(0, unreadCount - 1);
        setUnreadCount(next);
        setSidebarUnread(next);
      }
      if (notification.action_url) {
        navigate(notification.action_url);
        setOpen(false);
      }
    } catch {
      // ignore notification read failure in UI
    }
  };

  const onMarkAllRead = async () => {
    try {
      await markAllNotificationsRead();
      setItems((prev) => prev.map((item) => ({ ...item, lue: true })));
      setUnreadCount(0);
      setSidebarUnread(0);
    } catch {
      // ignore
    }
  };

  const onDelete = async (id) => {
    try {
      await deleteNotification(id);
      setItems((prev) => prev.filter((item) => item.id !== id));
    } catch {
      // ignore
    }
  };

  const onClearRead = async () => {
    try {
      await clearReadNotifications();
      setItems((prev) => prev.filter((item) => !item.lue));
    } catch {
      // ignore
    }
  };

  const hasCritical = items.some((item) => !item.lue && item.priorite === 'critique');
  const badgeLabel = unreadCount > 99 ? '99+' : String(unreadCount);

  return (
    <div className="notif-wrap">
      <button type="button" className={`nav-icon-btn notif-bell ${hasCritical ? 'pulse' : ''}`} onClick={openDropdown} title="Notifications">
        🔔
        {unreadCount > 0 ? <span className="notif-badge">{badgeLabel}</span> : null}
      </button>

      {open ? (
        <div className="notif-dropdown">
          <div className="notif-head">
            <div>
              <strong>Notifications</strong>
              <span style={{ marginLeft: 6, color: 'var(--text-muted)', fontSize: '0.8rem' }}>({unreadCount} non lues)</span>
            </div>
            <div style={{ display: 'flex', gap: 6 }}>
              <button type="button" className="btn btn-ghost" style={{ padding: '0.2rem 0.45rem' }} onClick={onMarkAllRead}>Tout lu</button>
              <button type="button" className="btn btn-ghost" style={{ padding: '0.2rem 0.45rem' }} onClick={onClearRead}>Vider lues</button>
            </div>
          </div>
          <div className="notif-list">
            {loading ? <p style={{ padding: '0.8rem', margin: 0 }}>Chargement...</p> : null}
            {!loading && items.length === 0 ? <p style={{ padding: '0.8rem', margin: 0 }}>Aucune notification</p> : null}
            {!loading && grouped.map((entry) => {
              if (entry.type === 'separator') {
                return <div key={entry.id} className="notif-sep">{entry.label}</div>;
              }
              const item = entry.item;
              const style = TYPE_ICON[item.type] || TYPE_ICON.systeme;
              return (
                <div key={item.id} className={`notif-item ${item.lue ? 'read' : 'unread'}`}>
                  <button type="button" className="notif-content" onClick={() => handleRead(item)}>
                    <span className="notif-icon" style={{ color: style.color }}>{style.icon}</span>
                    <span className="notif-text">
                      <strong style={{ fontWeight: item.lue ? 600 : 800 }}>{item.titre}</strong>
                      <span>{String(item.message || '').slice(0, 80)}</span>
                      <small>{relativeTime(item.created_at)}</small>
                    </span>
                  </button>
                  <button type="button" className="notif-delete" onClick={() => onDelete(item.id)}>🗑</button>
                </div>
              );
            })}
          </div>
          <div className="notif-foot">
            <button type="button" className="btn btn-ghost" onClick={() => { navigate('/notifications'); setOpen(false); }}>
              Voir toutes les notifications
            </button>
          </div>
        </div>
      ) : null}
    </div>
  );
}
