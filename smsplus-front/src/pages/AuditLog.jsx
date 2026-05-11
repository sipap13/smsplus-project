import { useEffect, useState } from 'react';
import {
  AreaChart, Area, XAxis, YAxis, Tooltip,
  ResponsiveContainer, CartesianGrid,
  BarChart, Bar,
} from 'recharts';
import { fetchAuditLogs, fetchAuditStats } from '../api/auditLogs';
import Modal from '../components/Modal';
import { formatCompactNumber } from '../lib/format';

/**
 * AuditLog Page
 * Admin-only view for tracking all system actions.
 */
export default function AuditLog() {
  const [logs, setLogs] = useState([]);
  const [meta, setMeta] = useState({ total: 0, per_page: 25, current_page: 1 });
  const [stats, setStats] = useState(null);
  const [graphStats, setGraphStats] = useState(null);
  const [loading, setLoading] = useState(true);

  // Filters
  const [filters, setFilters] = useState({
    user_email: '',
    action: '',
    entite: '',
    statut: '',
    date_debut: '',
    date_fin: '',
    search: '',
  });

  const [selectedLog, setSelectedLog] = useState(null);

  const loadData = async (page = 1) => {
    setLoading(true);
    try {
      const [logsRes, statsRes] = await Promise.all([
        fetchAuditLogs({ ...filters, page }),
        fetchAuditStats()
      ]);
      setLogs(logsRes.data);
      setMeta(logsRes.meta);
      setStats(logsRes.stats);
      setGraphStats(statsRes);
    } catch (err) {
      setError('Impossible de charger les logs d\'audit.');
      console.error(err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadData();
  }, [filters]);

  const handlePageChange = (p) => {
    loadData(p);
  };

  const resetFilters = () => {
    setFilters({
      user_email: '',
      action: '',
      entite: '',
      statut: '',
      date_debut: '',
      date_fin: '',
      search: '',
    });
  };

  const formatDate = (dateStr) => {
    if (!dateStr) return 'N/A';
    return new Intl.DateTimeFormat('fr-FR', {
      day: 'numeric',
      month: 'short',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    }).format(new Date(dateStr));
  };

  const getRoleBadge = (role) => {
    const map = {
      'ADMIN': { color: '#dc2626', bg: '#fee2e2', label: 'ADMIN' },
      'ANALYSTE_OP': { color: '#d97706', bg: '#fef3c7', label: 'OP' },
      'ANALYSTE_BUSS': { color: '#16a34a', bg: '#dcfce7', label: 'BUSS' },
    };
    const s = map[role] || { color: '#6b7280', bg: '#f3f4f6', label: role };
    return <span className="badge" style={{ color: s.color, background: s.bg, fontWeight: 700 }}>{s.label}</span>;
  };

  const getActionBadge = (action) => {
    const colors = {
      login: '#3b82f6',
      create: '#16a34a',
      update: '#f59e0b',
      delete: '#ef4444',
      export: '#8b5cf6',
      import: '#06b6d4',
    };
    const color = colors[action] || '#64748b';
    return <span className="badge" style={{ background: color, color: '#fff', fontSize: '0.75rem' }}>{action.toUpperCase()}</span>;
  };

  const getStatutIcon = (statut) => {
    if (statut === 'succes') return <span style={{ color: '#16a34a' }}>✓</span>;
    if (statut === 'echec') return <span style={{ color: '#dc2626' }}>✗</span>;
    return <span style={{ color: '#f59e0b' }}>⚠</span>;
  };

  const getAvatar = (email) => {
    const initial = email ? email.charAt(0).toUpperCase() : '?';
    const colors = ['#3b82f6', '#16a34a', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4'];
    const idx = (email || '').length % colors.length;
    return (
      <div style={{ width: 24, height: 24, borderRadius: '50%', background: colors[idx], color: '#fff', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: '0.7rem', fontWeight: 700 }}>
        {initial}
      </div>
    );
  };

  const renderJsonDiff = (before, after) => {
    if (!before && !after) return <p>Aucune donnée disponible.</p>;
    
    // Simple side by side for now
    return (
      <div className="grid-2" style={{ gap: '1rem' }}>
        <div>
          <h4 style={{ fontSize: '0.8rem', color: 'var(--text-muted)', marginBottom: '0.5rem' }}>AVANT</h4>
          <pre style={{ padding: '0.8rem', background: '#fee2e2', borderRadius: '8px', fontSize: '0.75rem', overflow: 'auto' }}>
            {JSON.stringify(before, null, 2)}
          </pre>
        </div>
        <div>
          <h4 style={{ fontSize: '0.8rem', color: 'var(--text-muted)', marginBottom: '0.5rem' }}>APRÈS</h4>
          <pre style={{ padding: '0.8rem', background: '#dcfce7', borderRadius: '8px', fontSize: '0.75rem', overflow: 'auto' }}>
            {JSON.stringify(after, null, 2)}
          </pre>
        </div>
      </div>
    );
  };

  return (
    <div className="page" style={{ padding: '2rem', maxWidth: '1400px', margin: '0 auto' }}>

      {/* Header */}
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: '1.5rem', flexWrap: 'wrap', gap: '1rem' }}>
        <div>
          <h1 style={{ fontSize: '1.75rem', fontWeight: 800, color: 'var(--text-main)', margin: '0 0 0.25rem' }}>Logs d&apos;Audit Système</h1>
          <p style={{ color: 'var(--text-muted)', margin: 0 }}>Traçabilité complète de toutes les actions utilisateurs et système</p>
        </div>
        <button className="btn btn-soft" style={{ height: '38px', fontSize: '0.85rem' }} onClick={() => loadData()}>
          Rafraîchir
        </button>
      </div>

      {/* KPI Cards */}
      {stats && (
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(180px, 1fr))', gap: '1rem', marginBottom: '1.5rem' }}>
          {[
            { label: 'Total actions', value: formatCompactNumber(stats.total_actions), color: '#6366f1' },
            { label: "Logins aujourd'hui", value: stats.logins_aujourd_hui, color: '#10b981' },
            { label: 'Top utilisateur', value: stats.top_utilisateur, color: '#f59e0b', small: true },
            { label: 'Échecs', value: stats.actions_echec, color: stats.actions_echec > 5 ? '#ef4444' : '#10b981' },
          ].map(k => (
            <div key={k.label} style={{ background: 'var(--bg-elevated)', border: '1px solid var(--border)', borderRadius: '14px', padding: '1rem 1.25rem' }}>
              <p style={{ margin: 0, fontSize: '0.72rem', color: 'var(--text-muted)', fontWeight: 500 }}>{k.label}</p>
              <p style={{ margin: '4px 0 0', fontWeight: 800, fontSize: k.small ? '0.9rem' : '1.2rem', color: k.color, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{k.value}</p>
            </div>
          ))}
        </div>
      )}

      {/* Filters */}
      <div style={{ background: 'var(--bg-elevated)', border: '1px solid var(--border)', borderRadius: '14px', padding: '1rem 1.25rem', marginBottom: '1.25rem', display: 'flex', flexWrap: 'wrap', gap: '0.75rem', alignItems: 'center' }}>
        <input
          placeholder="🔍 Rechercher dans les descriptions…"
          value={filters.search}
          onChange={e => setFilters(f => ({ ...f, search: e.target.value }))}
          onKeyDown={e => e.key === 'Enter' && loadData()}
          style={{ flex: '2 1 200px', height: '36px', border: '1px solid var(--border)', borderRadius: '8px', padding: '0 12px', fontSize: '0.85rem', background: 'var(--bg-surface)', color: 'var(--text-main)' }}
        />
        {[  
          { key: 'action', placeholder: 'Action', opts: ['login','logout','create','update','delete','export','import','2fa_success'] },
          { key: 'statut', placeholder: 'Statut', opts: ['succes','echec','warning'] },
        ].map(f => (
          <select key={f.key} value={filters[f.key]} onChange={e => setFilters(prev => ({ ...prev, [f.key]: e.target.value }))}
            style={{ height: '36px', border: '1px solid var(--border)', borderRadius: '8px', padding: '0 10px', fontSize: '0.85rem', background: 'var(--bg-surface)', color: 'var(--text-main)', cursor: 'pointer' }}>
            <option value="">— {f.placeholder} —</option>
            {f.opts.map(o => <option key={o} value={o}>{o.charAt(0).toUpperCase() + o.slice(1)}</option>)}
          </select>
        ))}
        <input type="date" value={filters.date_debut} onChange={e => setFilters(f => ({ ...f, date_debut: e.target.value }))}
          style={{ height: '36px', border: '1px solid var(--border)', borderRadius: '8px', padding: '0 10px', fontSize: '0.85rem', background: 'var(--bg-surface)', color: 'var(--text-main)' }} />
        <span style={{ color: 'var(--text-muted)', fontSize: '0.8rem' }}>→</span>
        <input type="date" value={filters.date_fin} onChange={e => setFilters(f => ({ ...f, date_fin: e.target.value }))}
          style={{ height: '36px', border: '1px solid var(--border)', borderRadius: '8px', padding: '0 10px', fontSize: '0.85rem', background: 'var(--bg-surface)', color: 'var(--text-main)' }} />
        {Object.values(filters).some(Boolean) && (
          <button onClick={resetFilters} style={{ height: '36px', padding: '0 14px', borderRadius: '8px', border: '1px dashed var(--border)', background: 'transparent', color: 'var(--text-muted)', cursor: 'pointer', fontSize: '0.82rem' }}>✕ Reset</button>
        )}
        <span style={{ marginLeft: 'auto', fontSize: '0.78rem', color: 'var(--text-muted)' }}>{meta.total.toLocaleString()} logs</span>
      </div>

      {/* Main Table */}
      <div style={{ background: 'var(--bg-elevated)', border: '1px solid var(--border)', borderRadius: '14px', overflow: 'hidden' }}>
        {loading ? (
          <div style={{ padding: '3rem', textAlign: 'center' }}><div className="spinner" /></div>
        ) : (
          <div style={{ overflowX: 'auto' }}>
            <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: '0.85rem' }}>
              <thead>
                <tr style={{ background: 'var(--bg-surface)' }}>
                  {['Date/Heure', 'Utilisateur', 'Rôle', 'Action', 'Entité', 'Description', 'IP', 'Statut'].map(h => (
                    <th key={h} style={{ padding: '0.7rem 1rem', textAlign: 'left', fontWeight: 600, fontSize: '0.75rem', color: 'var(--text-muted)', borderBottom: '1px solid var(--border)', whiteSpace: 'nowrap', textTransform: 'uppercase', letterSpacing: '0.04em' }}>{h}</th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {logs.length === 0 ? (
                  <tr><td colSpan={8} style={{ padding: '3rem', textAlign: 'center', color: 'var(--text-muted)' }}>Aucun log trouvé.</td></tr>
                ) : (
                  logs.map(log => (
                    <tr key={log.id} onClick={() => setSelectedLog(log)}
                      style={{ cursor: 'pointer', borderBottom: '1px solid var(--border)', transition: 'background 0.1s' }}
                      onMouseEnter={e => e.currentTarget.style.background = 'var(--bg-surface)'}
                      onMouseLeave={e => e.currentTarget.style.background = 'transparent'}
                    >
                      <td style={{ padding: '0.7rem 1rem', color: 'var(--text-muted)', fontFamily: 'monospace', whiteSpace: 'nowrap' }}>{formatDate(log.created_at)}</td>
                      <td style={{ padding: '0.7rem 1rem' }}>
                        <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                          {getAvatar(log.user_email)}
                          <span style={{ fontSize: '0.82rem', color: 'var(--text-main)' }}>{log.user_email || 'Système'}</span>
                        </div>
                      </td>
                      <td style={{ padding: '0.7rem 1rem' }}>{getRoleBadge(log.user_role)}</td>
                      <td style={{ padding: '0.7rem 1rem' }}>{getActionBadge(log.action)}</td>
                      <td style={{ padding: '0.7rem 1rem' }}><span style={{ fontFamily: 'monospace', fontSize: '0.78rem', background: 'var(--bg-surface)', padding: '2px 8px', borderRadius: '6px', color: 'var(--text-muted)' }}>{log.entite}</span></td>
                      <td style={{ padding: '0.7rem 1rem', maxWidth: '280px', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap', color: 'var(--text-main)' }}>{log.description}</td>
                      <td style={{ padding: '0.7rem 1rem', fontFamily: 'monospace', fontSize: '0.78rem', color: 'var(--text-muted)' }}>{log.ip_address}</td>
                      <td style={{ padding: '0.7rem 1rem', textAlign: 'center', fontSize: '1rem' }}>{getStatutIcon(log.statut)}</td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
        )}

        {/* Pagination */}
        <div style={{ padding: '0.75rem 1.25rem', borderTop: '1px solid var(--border)', display: 'flex', justifyContent: 'space-between', alignItems: 'center', background: 'var(--bg-surface)' }}>
          <span style={{ fontSize: '0.8rem', color: 'var(--text-muted)' }}>{meta.total.toLocaleString()} logs au total</span>
          <div style={{ display: 'flex', gap: '0.5rem', alignItems: 'center' }}>
            <button className="btn btn-soft" style={{ height: '32px', fontSize: '0.8rem' }} disabled={meta.current_page <= 1} onClick={() => handlePageChange(meta.current_page - 1)}>← Préc.</button>
            <span style={{ fontSize: '0.82rem', fontWeight: 600, color: 'var(--text-main)' }}>{meta.current_page} / {meta.last_page || 1}</span>
            <button className="btn btn-soft" style={{ height: '32px', fontSize: '0.8rem' }} disabled={meta.current_page >= (meta.last_page || 1)} onClick={() => handlePageChange(meta.current_page + 1)}>Suiv. →</button>
          </div>
        </div>
      </div>

      {/* Charts Section */}
      {graphStats && (
        <div className="grid-2" style={{ marginTop: '1.5rem', gap: '1.5rem' }}>
          <div className="surface surface-pad">
            <h3 className="text-heading" style={{ marginBottom: '1rem', fontSize: '1rem' }}>Activité par heure (24h)</h3>
            <div style={{ width: '100%', height: 250 }}>
              <ResponsiveContainer width="100%" height={280} minWidth={0} debounce={50}>
                <AreaChart data={graphStats.by_hour}>
                  <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="var(--border)" />
                  <XAxis 
                    dataKey="hour" 
                    tickFormatter={(v) => new Date(v).getHours() + 'h'}
                    tick={{ fontSize: 10 }}
                  />
                  <YAxis tick={{ fontSize: 10 }} />
                  <Tooltip labelFormatter={(v) => formatDate(v)} />
                  <Area type="monotone" dataKey="count" stroke="var(--primary)" fill="var(--primary-soft)" />
                </AreaChart>
              </ResponsiveContainer>
            </div>
          </div>
          <div className="surface surface-pad">
            <h3 className="text-heading" style={{ marginBottom: '1rem', fontSize: '1rem' }}>Activité par jour (7j)</h3>
            <div style={{ width: '100%', height: 250 }}>
              <ResponsiveContainer width="100%" height={280} minWidth={0} debounce={50}>
                <BarChart data={graphStats.by_day}>
                  <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="var(--border)" />
                  <XAxis 
                    dataKey="day" 
                    tickFormatter={(v) => new Intl.DateTimeFormat('fr-FR', { weekday: 'short' }).format(new Date(v))}
                    tick={{ fontSize: 10 }}
                  />
                  <YAxis tick={{ fontSize: 10 }} />
                  <Tooltip labelFormatter={(v) => new Date(v).toLocaleDateString('fr-FR')} />
                  <Bar dataKey="count" fill="var(--primary)" radius={[4, 4, 0, 0]} />
                </BarChart>
              </ResponsiveContainer>
            </div>
          </div>
        </div>
      )}

      {/* Top Users */}
      {graphStats && (
        <div className="surface surface-pad" style={{ marginTop: '1.5rem' }}>
          <h3 className="text-heading" style={{ marginBottom: '1rem', fontSize: '1rem' }}>Top Utilisateurs</h3>
          <table className="table-dense">
            <thead>
              <tr>
                <th>Email</th>
                <th>Rôle</th>
                <th>Actions</th>
                <th>Échecs</th>
                <th>Dernière activité</th>
              </tr>
            </thead>
            <tbody>
              {graphStats.top_users.map(u => (
                <tr key={u.user_email}>
                  <td>{u.user_email}</td>
                  <td>{getRoleBadge(u.user_role)}</td>
                  <td className="num">{u.total_actions}</td>
                  <td className="num" style={{ color: u.failed_actions > 0 ? 'var(--danger)' : 'inherit' }}>{u.failed_actions}</td>
                  <td>{formatDate(u.last_action)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {/* Detail Modal */}
      {selectedLog && (
        <Modal 
          title={`Détail de l'action #${selectedLog.id}`} 
          onClose={() => setSelectedLog(null)}
          wide
        >
          <div style={{ display: 'flex', flexDirection: 'column', gap: '1.25rem' }}>
            <div className="grid-2" style={{ gap: '1rem' }}>
              <div className="field">
                <span className="field-label">UTILISATEUR</span>
                <p style={{ margin: 0, fontWeight: 600 }}>{selectedLog.user_email || 'Système'} ({selectedLog.user_role || 'N/A'})</p>
              </div>
              <div className="field">
                <span className="field-label">DATE ET HEURE</span>
                <p style={{ margin: 0, fontWeight: 600 }}>{formatDate(selectedLog.created_at)}</p>
              </div>
              <div className="field">
                <span className="field-label">ACTION / ENTITÉ</span>
                <p style={{ margin: 0, fontWeight: 600 }}>{selectedLog.action.toUpperCase()} - {selectedLog.entite.toUpperCase()}</p>
              </div>
              <div className="field">
                <span className="field-label">ADRESSE IP</span>
                <p className="mono" style={{ margin: 0, fontWeight: 600 }}>{selectedLog.ip_address}</p>
              </div>
            </div>
            
            <div className="field">
              <span className="field-label">DESCRIPTION</span>
              <p style={{ margin: 0, padding: '0.8rem', background: 'var(--bg-surface)', borderRadius: '8px' }}>{selectedLog.description}</p>
            </div>

            <div className="field">
              <span className="field-label">MODIFICATIONS (JSON DIFF)</span>
              {renderJsonDiff(selectedLog.donnees_avant, selectedLog.donnees_apres)}
            </div>

            <div className="field">
              <span className="field-label">USER AGENT</span>
              <p style={{ margin: 0, fontSize: '0.8rem', color: 'var(--text-muted)' }}>{selectedLog.user_agent}</p>
            </div>

            <div style={{ display: 'flex', justifyContent: 'flex-end', marginTop: '1rem' }}>
              <button className="btn btn-soft" onClick={() => setSelectedLog(null)}>Fermer</button>
            </div>
          </div>
        </Modal>
      )}

      {meta.total > 10000 && (
        <div style={{ marginTop: '1rem', color: 'var(--warning)', fontSize: '0.85rem', textAlign: 'center' }}>
          ⚠ Retention : Plus de 10 000 logs d&apos;audit enregistrés. Pensez à l&apos;archivage.
        </div>
      )}
    </div>
  );
}
