import { useState, useEffect, useCallback } from 'react';
import {
  LineChart, Line, XAxis, YAxis, CartesianGrid, Tooltip, Legend, ResponsiveContainer,
  BarChart, Bar, ReferenceLine
} from 'recharts';
import api from '../api/axios';
import { format, formatDistanceToNow, parseISO } from 'date-fns';
import { fr } from 'date-fns/locale';

const JOB_LABELS = {
  'etl_agg_from_raw':        'Agrégation CDR (OCC_AGG)',
  'etl_cdr_from_tmp':        'Traitement CDR (OCC_DETAIL)',
  'import_occ_csv':          'Import OCC (CSV)',
  'import_mmg_csv':          'Import MMG (CSV)',
  'dashboard_stats_load':    'Mise à jour Dashboard',
  'dashboard_revenus_chart': 'Graphique Revenus',
  'notifications_load':      'Chargement Notifications',
  'notifications_polling':   'Polling Notifications',
  'prediction_data_collect': 'IA: Collecte Données',
  'prediction_metrics_calc': 'IA: Calcul Métriques',
  'prediction_groq_call':    'IA: Appel LLM',
  'msisdn_search_all':       'Recherche MSISDN',
  'cdr_occ_paginate':        'Consultation CDR OCC',
  'cdr_mmg_paginate':        'Consultation CDR MMG',
  'etl_deduplicate':         'Suppression Doublons',
  'export_occ_excel':        'Export OCC Excel',
  'export_mmg_excel':        'Export MMG Excel',
  'export_revenus_csv':      'Export Revenus CSV',
};

const getJobLabel = (jobName) => {
  if (JOB_LABELS[jobName]) return JOB_LABELS[jobName];
  if (jobName.startsWith('etl_deduplicate')) return 'Dédoublonnage CDR';
  if (jobName.startsWith('import_occ'))     return 'Importation OCC';
  if (jobName.startsWith('import_mmg'))     return 'Importation MMG';
  if (jobName.startsWith('export_'))        return 'Exportation de données';
  return jobName.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
};

const COLORS = ['#0f2744', '#1e3a5f', '#2a5082', '#3b6fa0', '#4a8ec2', '#5ba3d9', '#7ab8e0', '#9ecae1'];

const StatusBadge = ({ status }) => {
  const map = {
    success: { label: 'Succès', bg: 'rgba(16,185,129,0.15)', color: '#10b981' },
    failed:  { label: 'Erreur',  bg: 'rgba(239,68,68,0.15)',  color: '#ef4444' },
    running: { label: 'En cours',bg: 'rgba(99,102,241,0.15)', color: '#6366f1' },
  };
  const s = map[status] || { label: status, bg: 'var(--bg-surface)', color: 'var(--text-muted)' };
  return (
    <span style={{ padding: '2px 10px', borderRadius: '99px', fontSize: '0.75rem', fontWeight: 700, background: s.bg, color: s.color }}>
      {s.label}
    </span>
  );
};

const KPICard = ({ icon, label, value, sub, color }) => (
  <div style={{
    background: 'var(--bg-elevated)', border: '1px solid var(--border)', borderRadius: '16px',
    padding: '1.25rem 1.5rem', display: 'flex', alignItems: 'center', gap: '1rem',
    boxShadow: 'var(--shadow-sm)'
  }}>
    <div style={{
      width: 48, height: 48, borderRadius: '14px', background: `${color}22`,
      display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: '1.4rem', flexShrink: 0
    }}>{icon}</div>
    <div>
      <p style={{ margin: 0, fontSize: '0.8rem', color: 'var(--text-muted)', fontWeight: 500 }}>{label}</p>
      <p style={{ margin: '2px 0 0', fontSize: '1.4rem', fontWeight: 800, color: 'var(--text-main)' }}>{value}</p>
      {sub && <p style={{ margin: '2px 0 0', fontSize: '0.75rem', color: 'var(--text-muted)' }}>{sub}</p>}
    </div>
  </div>
);

export default function EtlPerformance() {
  const [data, setData]       = useState({});
  const [recentJobs, setRecentJobs] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError]     = useState('');
  const [days, setDays]       = useState(7);
  const [activeJob, setActiveJob] = useState(null);
  const [refreshKey, setRefreshKey] = useState(0);

  const fetchData = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const [perfRes, jobsRes] = await Promise.allSettled([
        api.get(`/etl/performance?days=${days}`),
        api.get(`/etl/jobs?page=1&per_page=20`),
      ]);

      if (perfRes.status === 'fulfilled') {
        const formatted = {};
        for (const [jobName, jobs] of Object.entries(perfRes.value.data)) {
          formatted[jobName] = jobs.map(j => ({
            ...j,
            label: format(new Date(j.date), 'dd/MM HH:mm', { locale: fr }),
          }));
        }
        setData(formatted);
        // Set first job as active by default
        const keys = Object.keys(formatted);
        if (keys.length && !activeJob) setActiveJob(keys[0]);
      } else {
        setError('Impossible de charger les données de performance.');
      }

      if (jobsRes.status === 'fulfilled') {
        setRecentJobs(jobsRes.value.data?.data || jobsRes.value.data || []);
      }
    } catch (err) {
      console.error(err);
      setError('Erreur de connexion à l\'API.');
    } finally {
      setLoading(false);
    }
  }, [days, refreshKey]);

  useEffect(() => { fetchData(); }, [fetchData]);

  // Global KPIs computed from all jobs
  const kpis = Object.values(data).flat();
  const totalJobs       = kpis.reduce((s, j) => s + (j.count || 0), 0);
  const totalRows       = kpis.reduce((s, j) => s + (j.rows  || 0), 0);
  const avgDuration     = kpis.length ? (kpis.reduce((s, j) => s + (j.duration_sec || 0), 0) / kpis.length).toFixed(1) : 0;
  const uniqueJobTypes  = Object.keys(data).length;

  const activeJobData   = activeJob ? (data[activeJob] || []) : [];
  const activeColor     = COLORS[Object.keys(data).indexOf(activeJob) % COLORS.length];
  const avgDur          = activeJobData.length ? (activeJobData.reduce((s, j) => s + j.duration_sec, 0) / activeJobData.length) : 0;

  return (
    <div className="page" style={{ padding: '2rem', maxWidth: '1400px', margin: '0 auto' }}>

      {/* Header */}
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: '2rem', flexWrap: 'wrap', gap: '1rem' }}>
        <div>
          <h1 style={{ fontSize: '1.75rem', fontWeight: 800, color: 'var(--text-main)', margin: '0 0 0.25rem' }}>
            Audit de Performance ETL
          </h1>
          <p style={{ color: 'var(--text-muted)', margin: 0 }}>
            Analyse des temps de traitement, volumes et santé des jobs
          </p>
        </div>
        <div style={{ display: 'flex', gap: '0.75rem', alignItems: 'center' }}>
          <select
            style={{ height: '40px', border: '1px solid var(--border)', borderRadius: '10px', padding: '0 12px', fontSize: '0.9rem', background: 'var(--bg-surface)', color: 'var(--text-main)', cursor: 'pointer' }}
            value={days} onChange={e => setDays(Number(e.target.value))}
          >
            <option value={1}>Dernières 24h</option>
            <option value={7}>7 derniers jours</option>
            <option value={15}>15 derniers jours</option>
            <option value={30}>30 derniers jours</option>
            <option value={90}>90 derniers jours</option>
          </select>
          <button
            className="btn btn-primary"
            style={{ height: '40px', display: 'flex', alignItems: 'center', gap: '6px', fontWeight: 600 }}
            onClick={() => setRefreshKey(k => k + 1)}
          >
            {loading ? 'Chargement...' : 'Actualiser'}
          </button>
        </div>
      </div>

      {error && (
        <div style={{ padding: '1rem', background: 'rgba(239,68,68,0.1)', color: 'var(--danger)', borderRadius: '12px', marginBottom: '2rem' }}>
          {error}
        </div>
      )}

      {/* KPI Row */}
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))', gap: '1rem', marginBottom: '2rem' }}>
        <KPICard label="Types de jobs" value={uniqueJobTypes} sub={`sur ${days}j`} color="#1e3a5f" />
        <KPICard label="Exécutions totales" value={totalJobs.toLocaleString()} sub={`sur ${days}j`} color="#2a5082" />
        <KPICard label="Lignes traitées" value={totalRows > 0 ? totalRows.toLocaleString() : '—'} sub="volume cumulé" color="#3b6fa0" />
        <KPICard label="Durée moyenne" value={`${avgDuration}s`} sub="tous jobs confondus" color="#4a8ec2" />
      </div>

      {loading ? (
        <div style={{ display: 'flex', justifyContent: 'center', padding: '4rem 0' }}>
          <div className="spinner" />
        </div>
      ) : Object.keys(data).length === 0 ? (
        <div style={{ padding: '4rem', textAlign: 'center', color: 'var(--text-muted)', background: 'var(--bg-elevated)', borderRadius: '16px', border: '1px solid var(--border)' }}>
          <div style={{ fontSize: '3rem', marginBottom: '1rem' }}>📭</div>
          <p style={{ fontSize: '1.1rem', fontWeight: 600 }}>Aucune donnée de performance trouvée</p>
          <p>Essayez d'élargir la période d'analyse.</p>
        </div>
      ) : (
        <div style={{ display: 'grid', gridTemplateColumns: '280px 1fr', gap: '1.5rem', alignItems: 'start' }}>

          {/* Job Sidebar */}
          <div style={{ background: 'var(--bg-elevated)', border: '1px solid var(--border)', borderRadius: '16px', overflow: 'hidden' }}>
            <div style={{ padding: '1rem 1.25rem', borderBottom: '1px solid var(--border)', fontWeight: 700, fontSize: '0.9rem', color: 'var(--text-muted)', letterSpacing: '0.05em', textTransform: 'uppercase' }}>
              Jobs ({uniqueJobTypes})
            </div>
            {Object.entries(data).map(([jobName, jobData], idx) => {
              const color = COLORS[idx % COLORS.length];
              const avg = jobData.length ? (jobData.reduce((s, j) => s + j.duration_sec, 0) / jobData.length).toFixed(1) : 0;
              const isActive = activeJob === jobName;
              return (
                <button
                  key={jobName}
                  onClick={() => setActiveJob(jobName)}
                  style={{
                    width: '100%', textAlign: 'left', padding: '0.9rem 1.25rem',
                    background: isActive ? `${color}15` : 'transparent',
                    borderTop: 'none',
                    borderRight: 'none',
                    borderBottom: '1px solid var(--border)',
                    borderLeft: isActive ? `3px solid ${color}` : '3px solid transparent',
                    cursor: 'pointer',
                    transition: 'all 0.15s'
                  }}
                >
                  <div style={{ fontWeight: 700, fontSize: '0.85rem', color: isActive ? color : 'var(--text-main)', marginBottom: '2px' }}>
                    {getJobLabel(jobName)}
                  </div>
                  <div style={{ fontSize: '0.75rem', color: 'var(--text-muted)', display: 'flex', justifyContent: 'space-between' }}>
                    <span>{jobData.reduce((s, j) => s + (j.count || 0), 0)} exec</span>
                    <span>{avg}s moy</span>
                  </div>
                </button>
              );
            })}
          </div>

          {/* Detail Panel */}
          {activeJob && (
            <div style={{ display: 'flex', flexDirection: 'column', gap: '1.5rem' }}>
              <div style={{ background: 'var(--bg-elevated)', border: '1px solid var(--border)', borderRadius: '16px', padding: '1.5rem' }}>
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '1.5rem', flexWrap: 'wrap', gap: '1rem' }}>
                  <div>
                    <h2 style={{ margin: 0, fontWeight: 800, fontSize: '1.2rem', color: 'var(--text-main)', display: 'flex', alignItems: 'center', gap: '8px' }}>
                      <span style={{ width: 12, height: 12, borderRadius: '50%', background: activeColor, display: 'inline-block' }} />
                      {getJobLabel(activeJob)}
                    </h2>
                    <code style={{ fontSize: '0.75rem', color: 'var(--text-muted)' }}>{activeJob}</code>
                  </div>
                  <div style={{ display: 'flex', gap: '1rem', fontSize: '0.85rem', color: 'var(--text-muted)' }}>
                    <span>{activeJobData.reduce((s, j) => s + (j.count || 0), 0)} exécutions</span>
                    <span>{avgDur.toFixed(1)}s moy</span>
                    <span>{activeJobData.reduce((s, j) => s + (j.rows || 0), 0).toLocaleString()} lignes</span>
                  </div>
                </div>

                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '2rem' }}>
                  {/* Duration Chart */}
                  <div>
                    <h4 style={{ margin: '0 0 0.75rem', fontSize: '0.85rem', color: 'var(--text-muted)', fontWeight: 600, textTransform: 'uppercase', letterSpacing: '0.05em' }}>
                      Temps de traitement (s)
                    </h4>
                    <div style={{ height: 260 }}>
                      <ResponsiveContainer width="100%" height={280} minWidth={0} debounce={50}>
                        <LineChart data={activeJobData}>
                          <CartesianGrid strokeDasharray="3 3" stroke="var(--border)" vertical={false} />
                          <XAxis dataKey="label" stroke="var(--text-muted)" tick={{ fontSize: 10 }} tickMargin={8} />
                          <YAxis stroke="var(--text-muted)" tick={{ fontSize: 10 }} tickFormatter={v => `${v}s`} />
                          <Tooltip
                            contentStyle={{ background: 'var(--bg-elevated)', border: '1px solid var(--border)', borderRadius: '8px', color: 'var(--text-main)', fontSize: '12px' }}
                            formatter={(value) => [`${value} s`, 'Durée']}
                            labelFormatter={(label, payload) => {
                              if (payload?.[0]) return `${label} · ${payload[0].payload.count || 0} jobs`;
                              return label;
                            }}
                          />
                          <ReferenceLine y={avgDur} stroke="#94a3b8" strokeDasharray="4 2"
                            label={{ value: `Moy ${avgDur.toFixed(1)}s`, position: 'right', fill: '#94a3b8', fontSize: 10 }}
                          />
                          <Line type="monotone" dataKey="duration_sec" stroke={activeColor} strokeWidth={2.5}
                            dot={{ r: 3, fill: activeColor }} activeDot={{ r: 5 }} name="Durée"
                          />
                        </LineChart>
                      </ResponsiveContainer>
                    </div>
                  </div>

                  {/* Volume Chart */}
                  <div>
                    <h4 style={{ margin: '0 0 0.75rem', fontSize: '0.85rem', color: 'var(--text-muted)', fontWeight: 600, textTransform: 'uppercase', letterSpacing: '0.05em' }}>
                      Volume traité (lignes)
                    </h4>
                    <div style={{ height: 260 }}>
                      <ResponsiveContainer width="100%" height={280} minWidth={0} debounce={50}>
                        <BarChart data={activeJobData}>
                          <CartesianGrid strokeDasharray="3 3" stroke="var(--border)" vertical={false} />
                          <XAxis dataKey="label" stroke="var(--text-muted)" tick={{ fontSize: 10 }} tickMargin={8} />
                          <YAxis stroke="var(--text-muted)" tick={{ fontSize: 10 }} tickFormatter={v => v.toLocaleString()} />
                          <Tooltip
                            contentStyle={{ background: 'var(--bg-elevated)', border: '1px solid var(--border)', borderRadius: '8px', color: 'var(--text-main)', fontSize: '12px' }}
                            formatter={(value) => [value.toLocaleString(), 'Lignes']}
                          />
                          <Bar dataKey="rows" fill={activeColor} opacity={0.85} radius={[4, 4, 0, 0]} name="Lignes" />
                        </BarChart>
                      </ResponsiveContainer>
                    </div>
                  </div>
                </div>
              </div>

              {/* Recent executions table */}
              {recentJobs.length > 0 && (
                <div style={{ background: 'var(--bg-elevated)', border: '1px solid var(--border)', borderRadius: '16px', overflow: 'hidden' }}>
                   <div style={{ padding: '1rem 1.5rem', borderBottom: '1px solid var(--border)', fontWeight: 700, fontSize: '0.95rem', color: 'var(--text-main)' }}>
                    Dernières exécutions (tous jobs)
                  </div>
                  <div style={{ overflowX: 'auto' }}>
                    <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: '0.85rem' }}>
                      <thead>
                        <tr style={{ background: 'var(--bg-surface)' }}>
                          {['Job', 'Statut', 'Démarré', 'Durée', 'Lignes'].map(h => (
                            <th key={h} style={{ padding: '0.75rem 1rem', textAlign: 'left', fontWeight: 600, color: 'var(--text-muted)', borderBottom: '1px solid var(--border)', whiteSpace: 'nowrap' }}>{h}</th>
                          ))}
                        </tr>
                      </thead>
                      <tbody>
                        {recentJobs.slice(0, 10).map((job, i) => (
                          <tr key={job.id || i} style={{ borderBottom: '1px solid var(--border)' }}>
                            <td style={{ padding: '0.75rem 1rem', color: 'var(--text-main)', fontWeight: 500 }}>
                              {getJobLabel(job.job_name || job.type || '')}
                            </td>
                            <td style={{ padding: '0.75rem 1rem' }}>
                              <StatusBadge status={job.status} />
                            </td>
                            <td style={{ padding: '0.75rem 1rem', color: 'var(--text-muted)', whiteSpace: 'nowrap' }}>
                              {job.created_at ? formatDistanceToNow(parseISO(job.created_at), { addSuffix: true, locale: fr }) : '—'}
                            </td>
                            <td style={{ padding: '0.75rem 1rem', color: 'var(--text-muted)', fontFamily: 'monospace' }}>
                              {job.duration_sec != null ? `${job.duration_sec}s` : '—'}
                            </td>
                            <td style={{ padding: '0.75rem 1rem', color: 'var(--text-muted)', fontFamily: 'monospace' }}>
                              {job.processed_rows != null ? job.processed_rows.toLocaleString() : '—'}
                            </td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                </div>
              )}
            </div>
          )}
        </div>
      )}
    </div>
  );
}
