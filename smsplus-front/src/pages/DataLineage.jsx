import { useState, useEffect } from 'react';
import api from '../api/axios';

const PIPELINE = [
  {
    id: 'file',
    title: 'Fichiers CDR Bruts',
    desc: 'Fichiers CSV/XLS issus des switchs OCC et MMG.',
    color: '#3b82f6',
    tables: ['Fichier .csv (OCC)', 'Fichier .xls (MMG)'],
    ops: ['Lecture séquentielle', 'Validation format', 'Détection encodage'],
    badge: 'SOURCE',
  },
  {
    id: 'tmp',
    title: 'Zone Tampon',
    desc: 'Chargement en masse sans transformation. Isolation des erreurs.',
    color: '#8b5cf6',
    tables: ['ra_t_tmp_occ', 'ra_t_tmp_mmg'],
    ops: ['Bulk INSERT', 'Déduplication préliminaire', 'Logging des rejets'],
    badge: 'STAGING',
  },
  {
    id: 'normalized',
    title: 'Données Normalisées',
    desc: 'Mapping des champs, typage, filtrage des erreurs et enrichissement.',
    color: '#10b981',
    tables: ['ra_t_occ_cdr_detail', 'ra_t_mmg_cdr_det'],
    ops: ['Mapping champs switch → SQL', 'Calcul commission TT', 'Classification VAS/DATA/VOICE'],
    badge: 'DETAIL',
  },
  {
    id: 'aggregated',
    title: 'Agrégats BI',
    desc: 'Résumé heure/service pour affichage rapide du dashboard.',
    color: '#f59e0b',
    tables: ['ra_t_occ_agg', 'ra_t_mmg_agg'],
    ops: ['GROUP BY heure/service', 'SUM revenus', 'Cache dashboard'],
    badge: 'ANALYTICS',
  },
  {
    id: 'alerts',
    title: 'Alertes & Services',
    desc: 'Détection d\'anomalies, alertes seuil, catalogue de services.',
    color: '#ef4444',
    tables: ['ra_t_alerts', 'ra_t_services', 'ra_t_etl_jobs'],
    ops: ['Seuil écart MMG/OCC', 'Alerte revenu chute', 'Audit trail ETL'],
    badge: 'MONITORING',
  },
];

const TRANSFORMATIONS = [
  {
    step: '1',
    color: '#8b5cf6',
    title: 'Injection ETL Batch',
    desc: 'Les fichiers sont lus ligne par ligne. Les doublons sont détectés en amont via comparaison de clés composites (msisdn + date + service + montant).',
    tags: ['BULK COPY', 'DEDUP CHECK', 'ERROR LOG'],
  },
  {
    step: '2',
    color: '#10b981',
    title: 'Normalisation & Mapping',
    desc: 'Conversion des formats propriétaires switch vers SQL standard. Calcul automatique de la commission Tunisie Telecom et identification des services VAS actifs.',
    tags: ['FIELD MAPPING', 'TYPE CAST', 'VAS ENRICH'],
  },
  {
    step: '3',
    color: '#f59e0b',
    title: 'Agrégation Real-time',
    desc: 'Millions de lignes compressées en quelques milliers d\'agrégats horaires. Permet une consultation sub-seconde des graphiques de revenus.',
    tags: ['GROUP BY', 'SUM / COUNT', 'CACHE WARM'],
  },
  {
    step: '4',
    color: '#ef4444',
    title: 'Monitoring & Alertes',
    desc: 'Détection automatique des anomalies (chute de revenus, écart MMG/OCC > 5%). Notifications temps réel et journal d\'audit complet.',
    tags: ['THRESHOLD CHECK', 'NOTIFICATION', 'AUDIT TRAIL'],
  },
];

export default function DataLineage() {
  const [active, setActive]   = useState(null);
  const [stats, setStats]     = useState({});
  const [etlStats, setEtlStats] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    Promise.allSettled([
      api.get('/etl/lineage-stats'),
      api.get('/etl/stats'),
    ]).then(([lineageRes, etlRes]) => {
      if (lineageRes.status === 'fulfilled') setStats(lineageRes.value.data);
      if (etlRes.status   === 'fulfilled') setEtlStats(etlRes.value.data?.data || etlRes.value.data);
    }).catch(console.error).finally(() => setLoading(false));
  }, []);

  const totalRows = Object.values(stats).reduce((s, v) => s + (typeof v === 'number' ? v : 0), 0);

  return (
    <div className="page" style={{ padding: '2rem', maxWidth: '1300px', margin: '0 auto' }}>

      {/* Header */}
      <div style={{ marginBottom: '2.5rem' }}>
        <h1 style={{ fontSize: '1.75rem', fontWeight: 800, color: 'var(--text-main)', margin: '0 0 0.25rem' }}>
          Data Lineage Interactif
        </h1>
        <p style={{ color: 'var(--text-muted)', margin: 0 }}>
          Visualisation du flux de données — de l'import jusqu'au reporting
        </p>
      </div>

      {/* Global Stats Strip */}
      <div style={{
        display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(180px, 1fr))',
        gap: '1rem', marginBottom: '2.5rem'
      }}>
        {[
          { label: 'Total lignes DB', value: loading ? '…' : totalRows.toLocaleString('fr-FR'), color: '#6366f1' },
          { label: 'Jobs réussis', value: loading ? '…' : (etlStats?.today?.success ?? '—'), color: '#10b981' },
          { label: 'Jobs en erreur', value: loading ? '…' : (etlStats?.today?.failed  ?? '—'), color: '#ef4444' },
          { label: 'En cours', value: loading ? '…' : (etlStats?.today?.running ?? '—'), color: '#f59e0b' },
          { label: 'Total aujourd\'hui', value: loading ? '…' : (etlStats?.today?.total ?? '—'), color: '#8b5cf6' },
        ].map(kpi => (
          <div key={kpi.label} style={{
            background: 'var(--bg-elevated)', border: '1px solid var(--border)',
            borderRadius: '14px', padding: '1rem 1.25rem',
          }}>
            <p style={{ margin: 0, fontSize: '0.75rem', color: 'var(--text-muted)', fontWeight: 500 }}>{kpi.label}</p>
            <p style={{ margin: '4px 0 0', fontSize: '1.1rem', fontWeight: 800, color: kpi.color }}>{kpi.value}</p>
          </div>
        ))}
      </div>

      {/* Pipeline Flow */}
      <div style={{ background: 'var(--bg-elevated)', border: '1px solid var(--border)', borderRadius: '20px', padding: '2rem', marginBottom: '2rem' }}>
        <h2 style={{ margin: '0 0 2rem', fontSize: '1.1rem', fontWeight: 700, color: 'var(--text-main)' }}>
          Pipeline de traitement — cliquez sur une étape
        </h2>

        <div style={{ display: 'flex', alignItems: 'flex-start', gap: 0, overflowX: 'auto', paddingBottom: '1rem' }}>
          {PIPELINE.map((step, idx) => (
            <div key={step.id} style={{ display: 'flex', alignItems: 'flex-start', flexShrink: 0 }}>
              {/* Step Card */}
              <div
                onClick={() => setActive(active?.id === step.id ? null : step)}
                style={{
                  cursor: 'pointer',
                  width: 200,
                  background: active?.id === step.id ? `${step.color}18` : 'var(--bg-surface)',
                  border: `2px solid ${active?.id === step.id ? step.color : 'var(--border)'}`,
                  borderRadius: '16px',
                  padding: '1.25rem',
                  transition: 'all 0.2s ease',
                  transform: active?.id === step.id ? 'translateY(-4px)' : 'none',
                  boxShadow: active?.id === step.id ? `0 8px 24px ${step.color}30` : 'none',
                }}
              >
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: '0.75rem' }}>
                  <div style={{
                    width: 48, height: 48, borderRadius: '14px', background: step.color,
                    display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: '1.1rem', fontWeight: 800, color: '#fff',
                    boxShadow: `0 4px 12px ${step.color}44`
                  }}>{idx + 1}</div>
                  <span style={{
                    fontSize: '0.6rem', fontWeight: 800, letterSpacing: '0.08em',
                    background: `${step.color}20`, color: step.color,
                    padding: '2px 7px', borderRadius: '6px'
                  }}>{step.badge}</span>
                </div>

                <h3 style={{ margin: '0 0 0.25rem', fontSize: '0.9rem', fontWeight: 700, color: 'var(--text-main)' }}>
                  {step.title}
                </h3>
                <p style={{ margin: '0 0 0.75rem', fontSize: '0.75rem', color: 'var(--text-muted)', lineHeight: 1.4 }}>
                  {step.desc}
                </p>

                {/* Table row counts */}
                <div style={{ display: 'flex', flexDirection: 'column', gap: '3px' }}>
                  {step.tables.map(t => (
                    <div key={t} style={{ display: 'flex', justifyContent: 'space-between', fontSize: '0.72rem', fontFamily: 'monospace' }}>
                      <span style={{ color: step.color, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap', maxWidth: '120px' }}>
                        {t}
                      </span>
                      <span style={{ color: 'var(--text-muted)' }}>
                        {stats[t] != null ? stats[t].toLocaleString('fr-FR') : '—'}
                      </span>
                    </div>
                  ))}
                </div>
              </div>

              {/* Arrow connector */}
              {idx < PIPELINE.length - 1 && (
                <div style={{ display: 'flex', alignItems: 'center', paddingTop: '32px', flexShrink: 0 }}>
                  <div style={{ width: 16, height: 2, background: 'var(--border)' }} />
                  <div style={{ width: 0, height: 0, borderTop: '6px solid transparent', borderBottom: '6px solid transparent', borderLeft: '8px solid var(--border)' }} />
                </div>
              )}
            </div>
          ))}
        </div>

        {/* Detail Panel */}
        {active && (
          <div style={{
            marginTop: '1.5rem',
            background: `${active.color}0d`,
            border: `1px solid ${active.color}40`,
            borderRadius: '14px',
            padding: '1.25rem',
            animation: 'slideDown 0.2s ease'
          }}>
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '1.5rem' }}>
              <div>
                <p style={{ margin: '0 0 0.5rem', fontWeight: 700, fontSize: '0.85rem', color: active.color }}>
                  TABLES ({active.tables.length})
                </p>
                {active.tables.map(t => (
                  <div key={t} style={{
                    display: 'flex', justifyContent: 'space-between',
                    padding: '6px 10px', borderRadius: '8px',
                    background: 'var(--bg-elevated)', marginBottom: '4px',
                    fontFamily: 'monospace', fontSize: '0.8rem'
                  }}>
                    <span style={{ color: 'var(--text-main)' }}>{t}</span>
                    <span style={{ color: active.color, fontWeight: 700 }}>
                      {stats[t] != null ? `${stats[t].toLocaleString('fr-FR')} lignes` : '—'}
                    </span>
                  </div>
                ))}
              </div>
              <div>
                <p style={{ margin: '0 0 0.5rem', fontWeight: 700, fontSize: '0.85rem', color: active.color }}>
                  OPÉRATIONS
                </p>
                {active.ops.map(op => (
                  <div key={op} style={{
                    display: 'flex', alignItems: 'center', gap: '8px',
                    padding: '6px 10px', borderRadius: '8px',
                    background: 'var(--bg-elevated)', marginBottom: '4px',
                    fontSize: '0.82rem', color: 'var(--text-main)'
                  }}>
                    <span style={{ color: active.color, fontWeight: 700 }}>›</span> {op}
                  </div>
                ))}
              </div>
            </div>
          </div>
        )}
      </div>

      {/* Transformation Steps */}
      <div style={{ background: 'var(--bg-elevated)', border: '1px solid var(--border)', borderRadius: '20px', padding: '2rem' }}>
        <h2 style={{ margin: '0 0 1.5rem', fontSize: '1.1rem', fontWeight: 700, color: 'var(--text-main)' }}>
          Détail des transformations
        </h2>
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(280px, 1fr))', gap: '1rem' }}>
          {TRANSFORMATIONS.map(t => (
            <div key={t.step} style={{
              background: 'var(--bg-surface)', borderRadius: '14px', padding: '1.25rem',
              borderLeft: `4px solid ${t.color}`, position: 'relative', overflow: 'hidden'
            }}>
              <div style={{
                position: 'absolute', top: '1rem', right: '1rem',
                width: 32, height: 32, borderRadius: '50%',
                background: `${t.color}20`, color: t.color,
                display: 'flex', alignItems: 'center', justifyContent: 'center',
                fontWeight: 800, fontSize: '0.9rem'
              }}>{t.step}</div>

              <h4 style={{ margin: '0 0 0.5rem', fontSize: '0.95rem', fontWeight: 700, color: 'var(--text-main)', paddingRight: '2rem' }}>
                {t.title}
              </h4>
              <p style={{ margin: '0 0 0.75rem', fontSize: '0.82rem', color: 'var(--text-muted)', lineHeight: 1.5 }}>
                {t.desc}
              </p>
              <div style={{ display: 'flex', flexWrap: 'wrap', gap: '4px' }}>
                {t.tags.map(tag => (
                  <span key={tag} style={{
                    fontSize: '0.65rem', fontWeight: 700, letterSpacing: '0.06em',
                    background: `${t.color}18`, color: t.color,
                    padding: '2px 8px', borderRadius: '6px', fontFamily: 'monospace'
                  }}>{tag}</span>
                ))}
              </div>
            </div>
          ))}
        </div>
      </div>

      <style>{`
        @keyframes slideDown {
          from { opacity: 0; transform: translateY(-8px); }
          to   { opacity: 1; transform: translateY(0); }
        }
      `}</style>
    </div>
  );
}
