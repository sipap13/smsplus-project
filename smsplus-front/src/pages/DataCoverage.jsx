import { useEffect, useState } from 'react';
import api from '../api/axios';

const TABLE_META = {
  ra_t_occ_cdr_detail: { icon: 'Ⓞ', label: 'Détails OCC CDR', color: '#1e3a5f' },
  ra_t_mmg_cdr_det:    { icon: 'Ⓜ', label: 'Détails MMG CDR', color: '#2a5082' },
  ra_t_services:       { icon: '🏷️', label: 'Catalogue Services', color: '#3b6fa0' },
  ra_t_alerts:         { icon: '🔔', label: 'Alertes', color: '#4a8ec2' },
};

const ProgressBar = ({ pct, color }) => (
  <div style={{ height: 8, background: 'var(--border)', borderRadius: 4, overflow: 'hidden', marginTop: 6 }}>
    <div style={{
      width: `${pct}%`, height: '100%', borderRadius: 4,
      background: `linear-gradient(90deg, ${color}99, ${color})`,
      transition: 'width 0.8s ease'
    }} />
  </div>
);

export default function DataCoverage() {
  const [report, setReport] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    api.get('/dashboard/data-coverage')
      .then(res => setReport(res.data))
      .catch(console.error)
      .finally(() => setLoading(false));
  }, []);

  return (
    <div className="page" style={{ padding: '2rem', maxWidth: '1200px', margin: '0 auto' }}>

      {/* Header */}
      <div style={{ marginBottom: '2rem' }}>
        <h1 style={{ fontSize: '1.75rem', fontWeight: 800, color: 'var(--text-main)', margin: '0 0 0.25rem' }}>
          🔍 Audit de Couverture des Données
        </h1>
        <p style={{ color: 'var(--text-muted)', margin: 0 }}>
          Diagnostic d'utilisation des colonnes par table — vue de la qualité des données exploitées
        </p>
      </div>

      {loading ? (
        <div style={{ display: 'flex', justifyContent: 'center', padding: '4rem' }}>
          <div className="spinner" />
        </div>
      ) : !report ? (
        <div style={{ padding: '3rem', textAlign: 'center', color: 'var(--text-muted)' }}>Aucune donnée disponible</div>
      ) : (
        <>
          {/* Table Coverage Cards */}
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(320px, 1fr))', gap: '1.25rem', marginBottom: '1.5rem' }}>
            {Object.entries(report.tables).map(([tableName, stats]) => {
              const meta   = TABLE_META[tableName] || { icon: '🗄️', label: tableName, color: '#3b6fa0' };
              const pctNum = parseFloat(stats.taux_utilisation) || 0;
              const health = pctNum >= 70 ? { label: 'Bonne', color: '#10b981' }
                           : pctNum >= 40 ? { label: 'Moyenne', color: '#f59e0b' }
                                          : { label: 'Faible', color: '#ef4444' };
              return (
                <div key={tableName} style={{
                  background: 'var(--bg-elevated)', border: '1px solid var(--border)',
                  borderRadius: '16px', padding: '1.5rem',
                  borderTop: `4px solid ${meta.color}`,
                  boxShadow: 'var(--shadow-sm)'
                }}>
                  {/* Card Header */}
                  <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: '1rem' }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem' }}>
                      <div style={{
                        width: 40, height: 40, borderRadius: '12px', background: `${meta.color}18`,
                        display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: '1.2rem'
                      }}>{meta.icon}</div>
                      <div>
                        <p style={{ margin: 0, fontWeight: 700, fontSize: '0.9rem', color: 'var(--text-main)' }}>{meta.label}</p>
                        <code style={{ fontSize: '0.72rem', color: 'var(--text-muted)' }}>{tableName}</code>
                      </div>
                    </div>
                    <span style={{
                      fontSize: '0.7rem', fontWeight: 700, padding: '3px 10px', borderRadius: '99px',
                      background: `${health.color}18`, color: health.color
                    }}>{health.label}</span>
                  </div>

                  {/* Stats row */}
                  <div style={{ display: 'flex', gap: '1.5rem', marginBottom: '1rem' }}>
                    <div>
                      <p style={{ margin: 0, fontSize: '0.72rem', color: 'var(--text-muted)' }}>Lignes</p>
                      <p style={{ margin: 0, fontWeight: 800, fontSize: '1.1rem', color: 'var(--text-main)' }}>
                        {stats.total_lignes.toLocaleString('fr-FR')}
                      </p>
                    </div>
                    <div>
                      <p style={{ margin: 0, fontSize: '0.72rem', color: 'var(--text-muted)' }}>Colonnes utilisées</p>
                      <p style={{ margin: 0, fontWeight: 800, fontSize: '1.1rem', color: meta.color }}>
                        {stats.colonnes_utilisees.length}
                      </p>
                    </div>
                    <div>
                      <p style={{ margin: 0, fontSize: '0.72rem', color: 'var(--text-muted)' }}>Taux</p>
                      <p style={{ margin: 0, fontWeight: 800, fontSize: '1.1rem', color: health.color }}>
                        {stats.taux_utilisation}
                      </p>
                    </div>
                  </div>

                  <ProgressBar pct={pctNum} color={meta.color} />

                  {/* Column chips */}
                  <div style={{ marginTop: '1rem' }}>
                    <p style={{ margin: '0 0 6px', fontSize: '0.72rem', fontWeight: 600, color: 'var(--text-muted)', textTransform: 'uppercase', letterSpacing: '0.06em' }}>
                      Colonnes exploitées
                    </p>
                    <div style={{ display: 'flex', flexWrap: 'wrap', gap: '4px' }}>
                      {stats.colonnes_utilisees.map(col => (
                        <span key={col} style={{
                          fontSize: '0.7rem', fontFamily: 'monospace', fontWeight: 600,
                          background: `${meta.color}12`, color: meta.color,
                          border: `1px solid ${meta.color}30`,
                          padding: '2px 8px', borderRadius: '6px'
                        }}>{col}</span>
                      ))}
                    </div>
                  </div>
                </div>
              );
            })}
          </div>

          {/* Recommendations */}
          <div style={{
            background: 'var(--bg-elevated)', border: '1px solid var(--border)',
            borderRadius: '16px', padding: '1.5rem',
            borderLeft: '4px solid #1e3a5f'
          }}>
            <h3 style={{ margin: '0 0 1rem', fontSize: '1rem', fontWeight: 700, color: 'var(--text-main)', display: 'flex', alignItems: 'center', gap: '8px' }}>
              🚀 Recommandations d'amélioration
            </h3>
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(260px, 1fr))', gap: '0.75rem' }}>
              {report.recommandations.map((rec, i) => (
                <div key={i} style={{
                  display: 'flex', alignItems: 'flex-start', gap: '0.75rem',
                  background: 'var(--bg-surface)', borderRadius: '10px', padding: '0.85rem 1rem'
                }}>
                  <span style={{ color: '#1e3a5f', fontWeight: 800, flexShrink: 0 }}>#{i + 1}</span>
                  <span style={{ fontSize: '0.85rem', color: 'var(--text-main)', lineHeight: 1.5 }}>{rec}</span>
                </div>
              ))}
            </div>
          </div>
        </>
      )}
    </div>
  );
}
