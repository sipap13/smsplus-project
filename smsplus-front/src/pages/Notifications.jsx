import { useEffect, useMemo, useState } from 'react';
import {
  fetchNotifications,
  markAllNotificationsRead,
  markNotificationRead,
} from '../api/notifications';

const PAGE_SIZE = 20;

export default function Notifications() {
  const [loading, setLoading] = useState(true);
  const [items, setItems] = useState([]);
  const [selected, setSelected] = useState([]);
  const [page, setPage] = useState(1);
  const [typeFilter, setTypeFilter] = useState('');
  const [priorityFilter, setPriorityFilter] = useState('');
  const [readFilter, setReadFilter] = useState('');
  const [dateFilter, setDateFilter] = useState('');

  useEffect(() => {
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
    load();
  }, []);

  const filtered = useMemo(() => {
    return items.filter((item) => {
      if (typeFilter && item.type !== typeFilter) {
        return false;
      }
      if (priorityFilter && item.priorite !== priorityFilter) {
        return false;
      }
      if (readFilter === 'lue' && !item.lue) {
        return false;
      }
      if (readFilter === 'non_lue' && item.lue) {
        return false;
      }
      if (dateFilter && String(item.created_at).slice(0, 10) !== dateFilter) {
        return false;
      }
      return true;
    });
  }, [items, typeFilter, priorityFilter, readFilter, dateFilter]);

  const paginated = useMemo(() => {
    const start = (page - 1) * PAGE_SIZE;
    return filtered.slice(start, start + PAGE_SIZE);
  }, [filtered, page]);

  const totalPages = Math.max(1, Math.ceil(filtered.length / PAGE_SIZE));

  const bulkMarkRead = async () => {
    if (!selected.length) {
      return;
    }
    try {
      await Promise.all(selected.map((id) => markNotificationRead(id)));
      setItems((prev) => prev.map((item) => (selected.includes(item.id) ? { ...item, lue: true } : item)));
      setSelected([]);
    } catch {
      // ignore
    }
  };

  return (
    <div className="page">
      <div className="page-header">
        <div>
          <h1 className="page-title">Notifications</h1>
          <p className="page-subtitle">Centre complet des notifications in-app</p>
        </div>
        <button
          type="button"
          className="btn btn-primary"
          onClick={async () => {
            try {
              await markAllNotificationsRead();
              setItems((prev) => prev.map((item) => ({ ...item, lue: true })));
            } catch {
              // ignore
            }
          }}
        >
          Tout marquer lu
        </button>
      </div>

      <div className="surface surface-pad" style={{ marginBottom: '1rem' }}>
        <div style={{ display: 'grid', gap: '0.6rem', gridTemplateColumns: 'repeat(auto-fit, minmax(170px, 1fr))' }}>
          <select className="cmdk-input" value={typeFilter} onChange={(e) => setTypeFilter(e.target.value)}>
            <option value="">Tous types</option>
            <option value="anomalie">anomalie</option>
            <option value="import">import</option>
            <option value="alerte">alerte</option>
            <option value="systeme">systeme</option>
            <option value="rapport">rapport</option>
          </select>
          <select className="cmdk-input" value={priorityFilter} onChange={(e) => setPriorityFilter(e.target.value)}>
            <option value="">Toutes priorites</option>
            <option value="basse">basse</option>
            <option value="normale">normale</option>
            <option value="haute">haute</option>
            <option value="critique">critique</option>
          </select>
          <select className="cmdk-input" value={readFilter} onChange={(e) => setReadFilter(e.target.value)}>
            <option value="">Lues et non lues</option>
            <option value="lue">Lues</option>
            <option value="non_lue">Non lues</option>
          </select>
          <input className="cmdk-input" type="date" value={dateFilter} onChange={(e) => setDateFilter(e.target.value)} />
          <button type="button" className="btn btn-ghost" onClick={bulkMarkRead}>Marquer sélection comme lue</button>
        </div>
      </div>

      <div className="surface surface-pad">
        {loading ? <p>Chargement...</p> : (
          <div className="table-wrap">
            <table>
              <thead>
                <tr>
                  <th />
                  <th>Date</th>
                  <th>Type</th>
                  <th>Priorité</th>
                  <th>Titre</th>
                  <th>Message</th>
                  <th>Etat</th>
                </tr>
              </thead>
              <tbody>
                {paginated.map((item) => (
                  <tr key={item.id} style={!item.lue ? { background: 'var(--primary-soft)' } : {}}>
                    <td>
                      <input
                        type="checkbox"
                        checked={selected.includes(item.id)}
                        onChange={(e) => {
                          if (e.target.checked) {
                            setSelected((prev) => [...prev, item.id]);
                          } else {
                            setSelected((prev) => prev.filter((id) => id !== item.id));
                          }
                        }}
                      />
                    </td>
                    <td>{String(item.created_at).slice(0, 19).replace('T', ' ')}</td>
                    <td>{item.type}</td>
                    <td>{item.priorite}</td>
                    <td>{item.titre}</td>
                    <td>{item.message}</td>
                    <td>{item.lue ? 'Lue' : 'Non lue'}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}

        <div style={{ display: 'flex', justifyContent: 'space-between', marginTop: '0.8rem' }}>
          <button type="button" className="btn btn-ghost" disabled={page <= 1} onClick={() => setPage((v) => Math.max(1, v - 1))}>Précédent</button>
          <span style={{ fontSize: '0.85rem' }}>Page {page}/{totalPages}</span>
          <button type="button" className="btn btn-ghost" disabled={page >= totalPages} onClick={() => setPage((v) => Math.min(totalPages, v + 1))}>Suivant</button>
        </div>
      </div>
    </div>
  );
}
