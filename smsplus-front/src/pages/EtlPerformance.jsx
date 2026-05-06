import { useState, useEffect } from 'react';
import {
  LineChart, Line, XAxis, YAxis, CartesianGrid, Tooltip, Legend, ResponsiveContainer,
  BarChart, Bar
} from 'recharts';
import api from '../api/axios';
import { format } from 'date-fns';
import { fr } from 'date-fns/locale';

const JOB_LABELS = {
  'etl_agg_from_raw':       'Agrégation CDR (OCC_AGG)',
  'etl_cdr_from_tmp':       'Traitement CDR (OCC_DETAIL)',
  'import_occ_csv':         'Import OCC (Fichier CSV)',
  'import_mmg_csv':         'Import MMG (Fichier CSV)',
  'dashboard_stats_load':   'Mise à jour Dashboard',
  'dashboard_revenus_chart': 'Graphique Revenus',
  'notifications_load':      'Chargement Notifications',
  'notifications_polling':   'Polling Notifications',
  'prediction_data_collect': 'IA: Collecte Données',
  'prediction_metrics_calc': 'IA: Calcul Métriques',
  'prediction_groq_call':    'IA: Appel LLM',
  'msisdn_search_all':      'Recherche MSISDN',
  'cdr_occ_paginate':       'Consultation CDR OCC',
  'cdr_mmg_paginate':       'Consultation CDR MMG',
  'etl_deduplicate':        'Suppression Doublons',
  'export_occ_excel':       'Export OCC Excel',
  'export_mmg_excel':       'Export MMG Excel',
  'export_revenus_csv':      'Export Revenus CSV',
};

const getJobLabel = (jobName) => {
  if (JOB_LABELS[jobName]) return JOB_LABELS[jobName];
  
  // Gestion des noms dynamiques avec suffixes (ex: etl_deduplicate_15042026)
  if (jobName.startsWith('etl_deduplicate')) return 'Dédoublonnage CDR';
  if (jobName.startsWith('import_occ')) return 'Importation OCC';
  if (jobName.startsWith('import_mmg')) return 'Importation MMG';
  if (jobName.startsWith('export_')) return 'Exportation de données';
  
  // Si c'est un nom très brut, on le nettoie un peu
  return jobName
    .replace(/_/g, ' ')
    .replace(/\b\w/g, l => l.toUpperCase());
};

export default function EtlPerformance() {
  const [data, setData] = useState({});
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [days, setDays] = useState(7);

  useEffect(() => {
    const fetchPerformance = async () => {
      setLoading(true);
      try {
        const res = await api.get(`/etl/performance?days=${days}`);
        // format dates for Recharts
        const formattedData = {};
        for (const [jobName, jobs] of Object.entries(res.data)) {
          formattedData[jobName] = jobs.map(j => ({
            ...j,
            formattedDate: format(new Date(j.date), 'dd MMM HH:mm', { locale: fr }),
          }));
        }
        setData(formattedData);
      } catch (err) {
        console.error(err);
        setError('Impossible de charger les données de performance.');
      } finally {
        setLoading(false);
      }
    };
    fetchPerformance();
  }, [days]);

  const COLORS = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4'];

  return (
    <div className="page" style={{ padding: '2rem', maxWidth: '1400px', margin: '0 auto' }}>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: '2rem' }}>
        <div>
          <h1 style={{ fontSize: '2rem', fontWeight: 800, color: 'var(--text-main)', margin: '0 0 0.5rem' }}>
            Audit de Performance ETL
          </h1>
          <p style={{ color: 'var(--text-muted)', fontSize: '1.1rem' }}>
            Analyse des temps de traitement et des volumes de données par job
          </p>
        </div>
        <div style={{ display: 'flex', alignItems: 'flex-end', gap: '1rem' }}>
          <div className="field">
            <label className="field-label">Période d'analyse</label>
            <select className="field-control" value={days} onChange={e => setDays(Number(e.target.value))}>
              <option value={7}>7 derniers jours</option>
              <option value={15}>15 derniers jours</option>
              <option value={30}>30 derniers jours</option>
              <option value={90}>90 derniers jours</option>
            </select>
          </div>
          <button 
            className="btn btn-ghost" 
            onClick={() => {
              // Re-trigger useEffect by toggling a dummy state or just calling the function if exported
              // But here we just use location.reload or better, a state-based refresh
              setDays(prev => prev); // Won't trigger if same, so let's use a refresh key
              window.location.reload(); // Simple and effective for now
            }}
            style={{ height: '42px', marginBottom: '4px' }}
          >
            ⟳ Actualiser
          </button>
        </div>
      </div>

      {error && (
        <div className="alert error" style={{ padding: '1rem', background: 'rgba(239, 68, 68, 0.1)', color: 'var(--danger)', borderRadius: '12px', marginBottom: '2rem' }}>
          {error}
        </div>
      )}

      {loading ? (
        <div style={{ display: 'flex', justifyContent: 'center', padding: '4rem 0' }}>
          <div className="spinner" />
        </div>
      ) : (
        Object.entries(data).length === 0 ? (
          <div style={{ padding: '4rem 0', textAlign: 'center', color: 'var(--text-muted)' }}>
            Aucune donnée de performance trouvée pour cette période.
          </div>
        ) : (
          <div style={{ display: 'grid', gap: '2rem' }}>
            {Object.entries(data).map(([jobName, jobData], index) => {
              const color = COLORS[index % COLORS.length];
              return (
                <div key={jobName} style={{ background: 'var(--bg-elevated)', border: '1px solid var(--border)', borderRadius: '16px', padding: '1.5rem', boxShadow: 'var(--shadow-sm)' }}>
                  <h3 style={{ margin: '0 0 1rem', fontSize: '1.2rem', color: 'var(--text-main)', display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                    <div style={{ width: 12, height: 12, borderRadius: '50%', background: color }} />
                    {getJobLabel(jobName)}
                    <span style={{ fontSize: '0.75rem', color: 'var(--text-muted)', fontWeight: 400, marginLeft: 'auto', fontFamily: 'monospace' }}>
                      {jobName}
                    </span>
                  </h3>
                  
                  <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '2rem' }}>
                    {/* Duration Chart */}
                    <div>
                      <h4 style={{ margin: '0 0 1rem', fontSize: '0.9rem', color: 'var(--text-muted)' }}>Temps de traitement (secondes)</h4>
                      <div style={{ height: 300 }}>
                        <ResponsiveContainer width="100%" height="100%" minWidth={0} minHeight={0}>
                          <LineChart data={jobData}>
                            <CartesianGrid strokeDasharray="3 3" stroke="var(--border)" vertical={false} />
                            <XAxis 
                              dataKey="formattedDate" 
                              stroke="var(--text-muted)" 
                              tick={{ fontSize: 11 }} 
                              tickMargin={10} 
                            />
                            <YAxis 
                              stroke="var(--text-muted)" 
                              tick={{ fontSize: 11 }} 
                              tickFormatter={v => `${v}s`}
                            />
                            <Tooltip 
                              contentStyle={{ background: 'var(--bg-elevated)', border: '1px solid var(--border)', borderRadius: '8px', color: 'var(--text-main)' }}
                              formatter={(value, name, props) => {
                                if (name === 'Durée') return [`${value} s`, 'Durée moyenne'];
                                return [value, name];
                              }}
                              labelFormatter={(label, payload) => {
                                if (payload && payload[0]) {
                                  const d = payload[0].payload;
                                  return `${label} (${d.count} jobs)`;
                                }
                                return label;
                              }}
                            />
                            <Line 
                              type="monotone" 
                              dataKey="duration_sec" 
                              stroke={color} 
                              strokeWidth={3}
                              dot={{ r: 4, strokeWidth: 2, fill: 'var(--bg-elevated)' }}
                              activeDot={{ r: 6 }}
                            />
                          </LineChart>
                        </ResponsiveContainer>
                      </div>
                    </div>

                    {/* Volume Chart */}
                    <div>
                      <h4 style={{ margin: '0 0 1rem', fontSize: '0.9rem', color: 'var(--text-muted)' }}>Volume traité (lignes)</h4>
                      <div style={{ height: 300 }}>
                        <ResponsiveContainer width="100%" height="100%" minWidth={0} minHeight={0}>
                          <BarChart data={jobData}>
                            <CartesianGrid strokeDasharray="3 3" stroke="var(--border)" vertical={false} />
                            <XAxis 
                              dataKey="formattedDate" 
                              stroke="var(--text-muted)" 
                              tick={{ fontSize: 11 }} 
                              tickMargin={10} 
                            />
                            <YAxis 
                              stroke="var(--text-muted)" 
                              tick={{ fontSize: 11 }} 
                              tickFormatter={v => v.toLocaleString()}
                            />
                            <Tooltip 
                              contentStyle={{ background: 'var(--bg-elevated)', border: '1px solid var(--border)', borderRadius: '8px', color: 'var(--text-main)' }}
                              formatter={(value) => [value.toLocaleString(), 'Total Lignes']}
                              labelFormatter={(label, payload) => {
                                if (payload && payload[0]) {
                                  const d = payload[0].payload;
                                  return `${label} (${d.count} jobs)`;
                                }
                                return label;
                              }}
                            />
                            <Bar 
                              dataKey="rows" 
                              fill={color} 
                              opacity={0.8}
                              radius={[4, 4, 0, 0]}
                            />
                          </BarChart>
                        </ResponsiveContainer>
                      </div>
                    </div>
                  </div>
                </div>
              );
            })}
          </div>
        )
      )}
    </div>
  );
}
