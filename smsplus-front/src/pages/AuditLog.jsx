/* eslint-disable react/prop-types */
import { useEffect, useMemo, useState } from 'react';
import {
  AreaChart, Area, XAxis, YAxis, Tooltip,
  ResponsiveContainer, CartesianGrid,
  BarChart, Bar, Legend,
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
  const [error, setError] = useState('');

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
    <div className="page">
      <div className="page-header tt-page-head">
        <div>
          <h1 className="page-title">Logs d'audit</h1>
          <p className="page-subtitle">Suivi complet de l'activité du système</p>
        </div>
      </div>

      {/* KPI Cards */}
      {stats && (
        <div className="kpi-grid-4" style={{ marginBottom: '1.5rem' }}>
          <div className="kpi-card tt-kpi">
            <p className="field-label">Total actions</p>
            <h3>{formatCompactNumber(stats.total_actions)}</h3>
          </div>
          <div className="kpi-card tt-kpi">
            <p className="field-label">Logins aujourd'hui</p>
            <h3>{stats.logins_aujourd_hui}</h3>
          </div>
          <div className="kpi-card tt-kpi">
            <p className="field-label">Top Utilisateur</p>
            <h3 style={{ fontSize: '1rem', whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>{stats.top_utilisateur}</h3>
          </div>
          <div className="kpi-card tt-kpi" style={{ borderTop: `3px solid ${stats.actions_echec > 5 ? 'var(--danger)' : 'var(--success)'}` }}>
            <p className="field-label">Échecs</p>
            <h3 style={{ color: stats.actions_echec > 5 ? 'var(--danger)' : 'inherit' }}>{stats.actions_echec}</h3>
          </div>
        </div>
      )}

      {/* Advanced Filters */}
      <div className="surface surface-pad" style={{ marginBottom: '1.5rem' }}>
        <div className="grid-4" style={{ gap: '1rem', alignItems: 'flex-end' }}>
          <div className="field">
            <label className="field-label">Recherche</label>
            <input 
              className="field-control" 
              placeholder="Description..." 
              value={filters.search}
              onChange={e => setFilters(f => ({ ...f, search: e.target.value }))}
            />
          </div>
          <div className="field">
            <label className="field-label">Action</label>
            <select 
              className="field-control"
              value={filters.action}
              onChange={e => setFilters(f => ({ ...f, action: e.target.value }))}
            >
              <option value="">Toutes</option>
              <option value="login">Login</option>
              <option value="logout">Logout</option>
              <option value="create">Create</option>
              <option value="update">Update</option>
              <option value="delete">Delete</option>
              <option value="export">Export</option>
              <option value="import">Import</option>
              <option value="2fa_success">2FA Succès</option>
            </select>
          </div>
          <div className="field">
            <label className="field-label">Statut</label>
            <select 
              className="field-control"
              value={filters.statut}
              onChange={e => setFilters(f => ({ ...f, statut: e.target.value }))}
            >
              <option value="">Tous</option>
              <option value="succes">Succès</option>
              <option value="echec">Échec</option>
              <option value="warning">Warning</option>
            </select>
          </div>
          <div style={{ display: 'flex', gap: '0.5rem' }}>
            <button className="btn btn-soft" style={{ flex: 1 }} onClick={resetFilters}>Réinitialiser</button>
            <button className="btn btn-primary" style={{ flex: 1 }} onClick={() => loadData()}>Filtrer</button>
          </div>
        </div>
      </div>

      {/* Main Table */}
      <div className="surface" style={{ overflow: 'hidden' }}>
        <div style={{ overflowX: 'auto' }}>
          <table className="table-hover">
            <thead>
              <tr>
                <th>Date/Heure</th>
                <th>Utilisateur</th>
                <th>Rôle</th>
                <th>Action</th>
                <th>Entité</th>
                <th>Description</th>
                <th>IP</th>
                <th>Statut</th>
              </tr>
            </thead>
            <tbody>
              {loading ? (
                <tr><td colSpan="8" style={{ textAlign: 'center', padding: '2rem' }}>Chargement...</td></tr>
              ) : logs.length === 0 ? (
                <tr><td colSpan="8" style={{ textAlign: 'center', padding: '2rem' }}>Aucun log trouvé.</td></tr>
              ) : (
                logs.map(log => (
                  <tr key={log.id} onClick={() => setSelectedLog(log)} style={{ cursor: 'pointer' }}>
                    <td style={{ fontSize: '0.85rem' }}>{formatDate(log.created_at)}</td>
                    <td>
                      <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                        {getAvatar(log.user_email)}
                        <span style={{ fontSize: '0.85rem' }}>{log.user_email || 'Système'}</span>
                      </div>
                    </td>
                    <td>{getRoleBadge(log.user_role)}</td>
                    <td>{getActionBadge(log.action)}</td>
                    <td><span className="chip">{log.entite}</span></td>
                    <td style={{ maxWidth: '250px', whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis', fontSize: '0.85rem' }}>
                      {log.description}
                    </td>
                    <td className="mono" style={{ fontSize: '0.8rem' }}>{log.ip_address}</td>
                    <td style={{ textAlign: 'center' }}>{getStatutIcon(log.statut)}</td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>

        {/* Pagination */}
        <div style={{ padding: '1rem', borderTop: '1px solid var(--border)', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
          <span style={{ fontSize: '0.85rem', color: 'var(--text-muted)' }}>
            Total : {meta.total} logs
          </span>
          <div style={{ display: 'flex', gap: '0.5rem' }}>
            <button 
              className="btn btn-ghost" 
              disabled={meta.current_page === 1}
              onClick={() => handlePageChange(meta.current_page - 1)}
            >
              Précédent
            </button>
            <span style={{ alignSelf: 'center', fontWeight: 600 }}>{meta.current_page} / {meta.last_page}</span>
            <button 
              className="btn btn-ghost" 
              disabled={meta.current_page === meta.last_page}
              onClick={() => handlePageChange(meta.current_page + 1)}
            >
              Suivant
            </button>
          </div>
        </div>
      </div>

      {/* Charts Section */}
      {graphStats && (
        <div className="grid-2" style={{ marginTop: '1.5rem', gap: '1.5rem' }}>
          <div className="surface surface-pad">
            <h3 className="text-heading" style={{ marginBottom: '1rem', fontSize: '1rem' }}>Activité par heure (24h)</h3>
            <div style={{ width: '100%', height: 250 }}>
              <ResponsiveContainer width="100%" height="100%" minWidth={0} minHeight={0}>
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
              <ResponsiveContainer width="100%" height="100%" minWidth={0} minHeight={0}>
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
          ⚠ Retention : Plus de 10 000 logs d'audit enregistrés. Pensez à l'archivage.
        </div>
      )}
    </div>
  );
}
