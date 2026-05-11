import { useEffect, useMemo, useState } from 'react';
import {
  fetchNotifications,
  markAllNotificationsRead,
  markNotificationRead,
  deleteNotification,
} from '../api/notifications';

const PAGE_SIZE = 20;

const PRIORITY_MAP = {
  critique: { label: 'Critique', bg: 'rgba(239,68,68,0.15)',  color: '#ef4444' },
  haute:    { label: 'Haute',    bg: 'rgba(245,158,11,0.15)', color: '#f59e0b' },
  normale:  { label: 'Normale',  bg: 'rgba(99,102,241,0.15)', color: '#6366f1' },
  basse:    { label: 'Basse',    bg: 'rgba(100,116,139,0.1)', color: '#64748b' },
};

const TYPE_ICON = {};

const PriorityBadge = ({ p }) => {
  const m = PRIORITY_MAP[p] || PRIORITY_MAP.normale;
  return (
    <span style={{ fontSize: '0.7rem', fontWeight: 700, padding: '2px 10px', borderRadius: '99px', background: m.bg, color: m.color, whiteSpace: 'nowrap' }}>
      {m.label}
    </span>
  );
};

export default function Notifications() {
  const [loading, setLoading]             = useState(true);
  const [items, setItems]                 = useState([]);
  const [selected, setSelected]           = useState([]);
  const [page, setPage]                   = useState(1);
  const [typeFilter, setTypeFilter]       = useState('');
  const [priorityFilter, setPriorityFilter] = useState('');
  const [readFilter, setReadFilter]       = useState('');
  const [dateFilter, setDateFilter]       = useState('');
  const [deletingId, setDeletingId]       = useState(null);

  const load = async () => {
    setLoading(true);
    try {
      const data = await fetchNotifications();
      setItems(data.notifications || []);
    } catch {
      setItems([]);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { load(); }, []);

  const unreadCount = items.filter(i => !i.lue).length;

  const filtered = useMemo(() => items.filter(item => {
    if (typeFilter && item.type !== typeFilter) return false;
    if (priorityFilter && item.priorite !== priorityFilter) return false;
    if (readFilter === 'lue' && !item.lue) return false;
    if (readFilter === 'non_lue' && item.lue) return false;
    if (dateFilter && String(item.created_at).slice(0, 10) !== dateFilter) return false;
    return true;
  }), [items, typeFilter, priorityFilter, readFilter, dateFilter]);

  const paginated  = useMemo(() => filtered.slice((page - 1) * PAGE_SIZE, page * PAGE_SIZE), [filtered, page]);
  const totalPages = Math.max(1, Math.ceil(filtered.length / PAGE_SIZE));

  const bulkMarkRead = async () => {
    if (!selected.length) return;
    await Promise.all(selected.map(id => markNotificationRead(id)));
    setItems(prev => prev.map(item => selected.includes(item.id) ? { ...item, lue: true } : item));
    setSelected([]);
  };

  const handleMarkRead = async (id) => {
    await markNotificationRead(id);
    setItems(prev => prev.map(item => item.id === id ? { ...item, lue: true } : item));
  };

  const handleDelete = async (id) => {
    setDeletingId(id);
    try {
      await deleteNotification(id);
      setItems(prev => prev.filter(item => item.id !== id));
    } catch { /* ignore */ } finally {
      setDeletingId(null);
    }
  };

  const toggleSelect = (id) => setSelected(prev =>
    prev.includes(id) ? prev.filter(x => x !== id) : [...prev, id]
  );

  const toggleSelectAll = () => {
    const pageIds = paginated.map(i => i.id);
    const allSelected = pageIds.every(id => selected.includes(id));
    setSelected(prev => allSelected ? prev.filter(id => !pageIds.includes(id)) : [...new Set([...prev, ...pageIds])]);
  };

  const resetFilters = () => {
    setTypeFilter(''); setPriorityFilter(''); setReadFilter(''); setDateFilter(''); setPage(1);
  };

  const selectStyle = {
    height: '36px', border: '1px solid var(--border)', borderRadius: '8px',
    padding: '0 10px', fontSize: '0.85rem', background: 'var(--bg-surface)',
    color: 'var(--text-main)', cursor: 'pointer'
  };

  return (
    <div className="page" style={{ padding: '2rem', maxWidth: '1200px', margin: '0 auto' }}>

      {/* Header */}
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: '1.5rem', flexWrap: 'wrap', gap: '1rem' }}>
        <div>
          <h1 style={{ fontSize: '1.75rem', fontWeight: 800, color: 'var(--text-main)', margin: '0 0 0.25rem' }}>
            Notifications
          </h1>
          <p style={{ color: 'var(--text-muted)', margin: 0 }}>
            {unreadCount > 0 ? (
              <><span style={{ color: '#6366f1', fontWeight: 700 }}>{unreadCount} non lue{unreadCount > 1 ? 's' : ''}</span> sur {items.length} total</>
            ) : (
              `${items.length} notification${items.length > 1 ? 's' : ''} — toutes lues`
            )}
          </p>
        </div>
        <div style={{ display: 'flex', gap: '0.75rem' }}>
          {selected.length > 0 && (
            <button className="btn btn-soft" onClick={bulkMarkRead} style={{ height: '38px', fontSize: '0.85rem' }}>
              Marquer {selected.length} comme lue{selected.length > 1 ? 's' : ''}
            </button>
          )}
          <button className="btn btn-primary" style={{ height: '38px', fontSize: '0.85rem' }}
            onClick={async () => {
              await markAllNotificationsRead();
              setItems(prev => prev.map(item => ({ ...item, lue: true })));
            }}
          >
            Tout marquer lu
          </button>
        </div>
      </div>

      {/* Filters */}
      <div style={{
        background: 'var(--bg-elevated)', border: '1px solid var(--border)',
        borderRadius: '14px', padding: '1rem 1.25rem', marginBottom: '1.25rem',
        display: 'flex', flexWrap: 'wrap', gap: '0.75rem', alignItems: 'center'
      }}>
        <select style={selectStyle} value={typeFilter} onChange={e => { setTypeFilter(e.target.value); setPage(1); }}>
          <option value="">Tous types</option>
          <option value="anomalie">Anomalie</option>
          <option value="import">Import</option>
          <option value="alerte">Alerte</option>
          <option value="systeme">Système</option>
          <option value="rapport">Rapport</option>
        </select>
        <select style={selectStyle} value={priorityFilter} onChange={e => { setPriorityFilter(e.target.value); setPage(1); }}>
          <option value="">Toutes priorités</option>
          <option value="critique">Critique</option>
          <option value="haute">Haute</option>
          <option value="normale">Normale</option>
          <option value="basse">Basse</option>
        </select>
        <select style={selectStyle} value={readFilter} onChange={e => { setReadFilter(e.target.value); setPage(1); }}>
          <option value="">Toutes</option>
          <option value="non_lue">Non lues</option>
          <option value="lue">Lues</option>
        </select>
        <input style={{ ...selectStyle, width: '160px' }} type="date" value={dateFilter}
          onChange={e => { setDateFilter(e.target.value); setPage(1); }} />
        {(typeFilter || priorityFilter || readFilter || dateFilter) && (
          <button onClick={resetFilters} style={{ ...selectStyle, background: 'transparent', color: 'var(--text-muted)', border: '1px dashed var(--border)' }}>
            Réinitialiser
          </button>
        )}
        <span style={{ marginLeft: 'auto', fontSize: '0.82rem', color: 'var(--text-muted)' }}>
          {filtered.length} résultat{filtered.length > 1 ? 's' : ''}
        </span>
      </div>

      {/* List */}
      <div style={{ background: 'var(--bg-elevated)', border: '1px solid var(--border)', borderRadius: '14px', overflow: 'hidden' }}>
        {loading ? (
          <div style={{ padding: '3rem', textAlign: 'center' }}><div className="spinner" /></div>
        ) : paginated.length === 0 ? (
          <div style={{ padding: '3rem', textAlign: 'center', color: 'var(--text-muted)' }}>
            <div style={{ fontSize: '2rem', marginBottom: '0.5rem' }}>—</div>
            <p style={{ fontWeight: 600, margin: 0 }}>Aucune notification trouvée</p>
          </div>
        ) : (
          <>
            {/* Table header */}
            <div style={{
              display: 'grid', gridTemplateColumns: '40px 1fr 120px 100px 160px 100px',
              padding: '0.7rem 1.25rem', background: 'var(--bg-surface)',
              borderBottom: '1px solid var(--border)', fontSize: '0.75rem',
              color: 'var(--text-muted)', fontWeight: 600, textTransform: 'uppercase', letterSpacing: '0.05em'
            }}>
              <input type="checkbox" style={{ cursor: 'pointer' }}
                checked={paginated.every(i => selected.includes(i.id))}
                onChange={toggleSelectAll}
              />
              <span>Notification</span>
              <span>Type</span>
              <span>Priorité</span>
              <span>Date</span>
              <span>Actions</span>
            </div>

            {paginated.map(item => (
              <div key={item.id} style={{
                display: 'grid', gridTemplateColumns: '40px 1fr 120px 100px 160px 100px',
                padding: '0.9rem 1.25rem', borderBottom: '1px solid var(--border)',
                alignItems: 'center', gap: '0.5rem',
                background: !item.lue ? 'rgba(99,102,241,0.04)' : 'transparent',
                transition: 'background 0.15s'
              }}>
                <input type="checkbox" style={{ cursor: 'pointer' }}
                  checked={selected.includes(item.id)}
                  onChange={() => toggleSelect(item.id)}
                />
                <div>
                  <div style={{ display: 'flex', alignItems: 'center', gap: '8px', marginBottom: '2px' }}>
                    {!item.lue && <span style={{ width: 7, height: 7, borderRadius: '50%', background: '#6366f1', display: 'inline-block', flexShrink: 0 }} />}
                    <span style={{ fontWeight: !item.lue ? 700 : 500, fontSize: '0.88rem', color: 'var(--text-main)' }}>
                      {item.titre}
                    </span>
                  </div>
                  <p style={{ margin: 0, fontSize: '0.78rem', color: 'var(--text-muted)', lineHeight: 1.4 }}>
                    {item.message}
                  </p>
                </div>
                <div style={{ display: 'flex', alignItems: 'center', gap: '6px', fontSize: '0.8rem', color: 'var(--text-muted)' }}>
                  {item.type}
                </div>
                <PriorityBadge p={item.priorite} />
                <span style={{ fontSize: '0.78rem', color: 'var(--text-muted)', fontFamily: 'monospace' }}>
                  {String(item.created_at).slice(0, 16).replace('T', ' ')}
                </span>
                <div style={{ display: 'flex', gap: '4px' }}>
                  {!item.lue && (
                    <button onClick={() => handleMarkRead(item.id)}
                      style={{ background: 'none', border: '1px solid var(--border)', borderRadius: '6px', padding: '3px 8px', cursor: 'pointer', fontSize: '0.72rem', color: 'var(--text-muted)', transition: 'all 0.15s' }}
                      title="Marquer comme lue"
                    >✓</button>
                  )}
                  <button onClick={() => handleDelete(item.id)} disabled={deletingId === item.id}
                    style={{ background: 'none', border: '1px solid rgba(239,68,68,0.3)', borderRadius: '6px', padding: '3px 8px', cursor: 'pointer', fontSize: '0.72rem', color: '#ef4444', transition: 'all 0.15s' }}
                    title="Supprimer"
                  >Suppr.</button>
                </div>
              </div>
            ))}
          </>
        )}

        {/* Pagination */}
        {!loading && totalPages > 1 && (
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '0.75rem 1.25rem', background: 'var(--bg-surface)' }}>
            <button className="btn btn-soft" style={{ height: '34px', fontSize: '0.82rem' }}
              disabled={page <= 1} onClick={() => setPage(v => v - 1)}>Précédent</button>
            <span style={{ fontSize: '0.82rem', color: 'var(--text-muted)' }}>Page {page} / {totalPages}</span>
            <button className="btn btn-soft" style={{ height: '34px', fontSize: '0.82rem' }}
              disabled={page >= totalPages} onClick={() => setPage(v => v + 1)}>Suivant</button>
          </div>
        )}
      </div>
    </div>
  );
}
