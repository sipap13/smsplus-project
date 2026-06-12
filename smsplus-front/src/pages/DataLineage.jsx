import { useState, useEffect, useRef } from 'react';
import api from '../api/axios';

const STAGES = [
  {
    id: 'source', step: '1', title: 'Sources CDR', badge: 'SOURCE',
    color: '#1e3a5f', gradient: 'linear-gradient(135deg, #1e3a5f, #0f2744)',
    desc: 'Fichiers CSV/XLS issus des switchs réseau OCC et MMG.',
    tables: [{ label: 'Fichier .csv (OCC)', key: 'ra_t_tmp_occ' }, { label: 'Fichier .xls (MMG)', key: 'ra_t_tmp_mmg' }],
    ops: ['Lecture séquentielle', 'Validation format', 'Détection encodage UTF-8/Latin'],
    techDetails: 'Import via ProcessImportJob (Laravel Queue). Parsing avec PhpSpreadsheet pour XLS et fgetcsv pour CSV.',
  },
  {
    id: 'staging', step: '2', title: 'Zone Tampon (Staging)', badge: 'STAGING',
    color: '#2a5082', gradient: 'linear-gradient(135deg, #2a5082, #1e3a5f)',
    desc: 'Chargement brut sans transformation. Isolation des erreurs avant insertion.',
    tables: [{ key: 'ra_t_tmp_occ' }, { key: 'ra_t_tmp_mmg' }],
    ops: ['Bulk INSERT (batch 500)', 'Déduplication clé composite', 'Logging des rejets'],
    techDetails: 'Clé composite: msisdn + date + keyword + montant. Les doublons sont comptés mais non insérés.',
  },
  {
    id: 'normalized', step: '3', title: 'Données Normalisées', badge: 'DETAIL',
    color: '#3b6fa0', gradient: 'linear-gradient(135deg, #3b6fa0, #2a5082)',
    desc: 'Mapping des champs, typage strict, enrichissement service et classification.',
    tables: [{ key: 'ra_t_occ_cdr_detail' }, { key: 'ra_t_mmg_cdr_det' }],
    ops: ['Mapping champs switch → SQL', 'Calcul commission TT', 'Classification VAS/DATA/VOICE/SMS'],
    techDetails: 'JOIN avec ra_t_services pour enrichir keyword → nom_service, nom_fournisseur, prix catalogue.',
  },
  {
    id: 'analytics', step: '4', title: 'Agrégats Analytics', badge: 'BI',
    color: '#4a8ec2', gradient: 'linear-gradient(135deg, #4a8ec2, #3b6fa0)',
    desc: 'Résumés par heure/jour/service pour alimenter le dashboard en temps réel.',
    tables: [{ key: 'ra_t_mmg_agg' }, { key: 'ra_t_occ_agg' }],
    ops: ['GROUP BY heure/service', 'SUM revenus & COUNT CDR', 'Cache dashboard (10 min TTL)'],
    techDetails: 'Agrégation automatique via DashboardController. Cache Laravel avec invalidation sur nouvel import.',
  },
  {
    id: 'monitoring', step: '5', title: 'Monitoring & Alertes', badge: 'OPS',
    color: '#5ba3d9', gradient: 'linear-gradient(135deg, #5ba3d9, #4a8ec2)',
    desc: 'Détection d\'anomalies, alertes seuil, catalogue services et audit trail.',
    tables: [{ key: 'ra_t_alerts' }, { key: 'ra_t_services' }, { key: 'ra_t_etl_jobs' }],
    ops: ['Seuil écart MMG/OCC > 5%', 'Alerte chute revenus', 'Audit trail complet ETL'],
    techDetails: 'EtlMonitorService trace chaque job. AlertController déclenche des notifications push.',
  },
];

const fmt = (n) => typeof n === 'number' ? n.toLocaleString('fr-FR') : '—';

export default function DataLineage() {
  const [activeIdx, setActiveIdx] = useState(null);
  const [stats, setStats] = useState({});
  const [etlStats, setEtlStats] = useState(null);
  const [loading, setLoading] = useState(true);
  const [animatedBars, setAnimatedBars] = useState(false);
  const flowRef = useRef(null);

  useEffect(() => {
    Promise.allSettled([
      api.get('/etl/lineage-stats'),
      api.get('/etl/stats'),
    ]).then(([lr, er]) => {
      if (lr.status === 'fulfilled') setStats(lr.value.data);
      if (er.status === 'fulfilled') setEtlStats(er.value.data?.data || er.value.data);
    }).catch(console.error).finally(() => setLoading(false));

    const t = setTimeout(() => setAnimatedBars(true), 300);
    return () => clearTimeout(t);
  }, []);

  const totalRows = Object.values(stats).reduce((s, v) => s + (typeof v === 'number' ? v : 0), 0);
  const maxTable = Math.max(1, ...Object.values(stats).filter(v => typeof v === 'number'));

  const kpis = [
    { label: 'Lignes totales en base', value: loading ? '…' : fmt(totalRows), abbr: 'DB', color: '#1e3a5f' },
    { label: 'Jobs réussis (24h)', value: loading ? '…' : (etlStats?.today?.success ?? '—'), abbr: 'OK', color: '#2a5082' },
    { label: 'Erreurs (24h)', value: loading ? '…' : (etlStats?.today?.failed ?? '—'), abbr: 'KO', color: '#1e3a5f' },
    { label: 'En cours', value: loading ? '…' : (etlStats?.today?.running ?? '—'), abbr: 'RUN', color: '#3b6fa0' },
    { label: 'Jobs totaux (24h)', value: loading ? '…' : (etlStats?.today?.total ?? '—'), abbr: 'ALL', color: '#4a8ec2' },
  ];

  return (
    <div className="page" style={{ padding: '2rem', maxWidth: '1400px', margin: '0 auto' }}>

      {/* Header */}
      <div style={{ marginBottom: '2rem', display: 'flex', justifyContent: 'space-between', alignItems: 'flex-end', flexWrap: 'wrap', gap: '1rem' }}>
        <div>
          <h1 style={{ fontSize: '1.6rem', fontWeight: 800, color: 'var(--text-main)', margin: '0 0 4px', letterSpacing: '-0.02em' }}>
            Data Lineage
          </h1>
          <p style={{ color: 'var(--text-muted)', margin: 0, fontSize: '0.9rem' }}>
            Traçabilité complète du flux de données — de l'import brut au reporting décisionnel
          </p>
        </div>
        <div />
      </div>

      {/* KPI cards */}
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))', gap: '12px', marginBottom: '2rem' }}>
        {kpis.map(k => (
          <div key={k.label} style={{
            background: 'var(--bg-elevated)', border: '1px solid var(--border)',
            borderRadius: '16px', padding: '1.1rem 1.25rem',
            display: 'flex', alignItems: 'center', gap: '14px',
            transition: 'transform 0.2s, box-shadow 0.2s',
          }}
          onMouseEnter={e => { e.currentTarget.style.transform = 'translateY(-2px)'; e.currentTarget.style.boxShadow = `0 8px 20px ${k.color}15`; }}
          onMouseLeave={e => { e.currentTarget.style.transform = 'none'; e.currentTarget.style.boxShadow = 'none'; }}
          >
            <div style={{
              width: 44, height: 44, borderRadius: '12px', background: `${k.color}15`,
              display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: '0.7rem', fontWeight: 800, color: k.color, letterSpacing: '0.04em', flexShrink: 0
            }}>{k.abbr}</div>
            <div>
              <p style={{ margin: 0, fontSize: '0.7rem', color: 'var(--text-muted)', fontWeight: 500, textTransform: 'uppercase', letterSpacing: '0.04em' }}>{k.label}</p>
              <p style={{ margin: '2px 0 0', fontSize: '1.25rem', fontWeight: 800, color: k.color }}>{k.value}</p>
            </div>
          </div>
        ))}
      </div>

      {/* Pipeline flow */}
      <div ref={flowRef} style={{
        background: 'var(--bg-elevated)', border: '1px solid var(--border)',
        borderRadius: '20px', padding: '2rem', marginBottom: '1.5rem',
        position: 'relative', overflow: 'hidden'
      }}>
        <div style={{ position: 'absolute', top: 0, left: 0, right: 0, height: '3px', background: 'linear-gradient(90deg, #0f2744, #1e3a5f, #2a5082, #3b6fa0, #4a8ec2, #5ba3d9)', opacity: 0.7 }} />

        <h2 style={{ margin: '0 0 1.5rem', fontSize: '1rem', fontWeight: 700, color: 'var(--text-main)' }}>
          Pipeline de traitement
        </h2>

        <div style={{ display: 'flex', alignItems: 'stretch', gap: 0, overflowX: 'auto', paddingBottom: '0.5rem' }}>
          {STAGES.map((stage, idx) => {
            const isActive = activeIdx === idx;
            return (
              <div key={stage.id} style={{ display: 'flex', alignItems: 'stretch', flexShrink: 0 }}>
                <div
                  onClick={() => setActiveIdx(isActive ? null : idx)}
                  style={{
                    cursor: 'pointer', width: 210,
                    background: isActive ? `${stage.color}10` : 'var(--bg-surface)',
                    border: `2px solid ${isActive ? stage.color : 'var(--border)'}`,
                    borderRadius: '16px', padding: '1.25rem',
                    transition: 'all 0.25s cubic-bezier(0.4, 0, 0.2, 1)',
                    transform: isActive ? 'translateY(-6px) scale(1.02)' : 'none',
                    boxShadow: isActive ? `0 12px 32px ${stage.color}25` : '0 2px 8px rgba(0,0,0,0.04)',
                    display: 'flex', flexDirection: 'column',
                  }}
                >
                  {/* Top row */}
                  <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: '10px' }}>
                    <div style={{
                      width: 44, height: 44, borderRadius: '14px', background: stage.gradient,
                      display: 'flex', alignItems: 'center', justifyContent: 'center',
                      fontSize: '1rem', fontWeight: 800, color: '#fff', boxShadow: `0 4px 14px ${stage.color}40`,
                    }}>{stage.step}</div>
                    <span style={{
                      fontSize: '0.58rem', fontWeight: 800, letterSpacing: '0.08em',
                      background: `${stage.color}18`, color: stage.color,
                      padding: '3px 8px', borderRadius: '6px'
                    }}>{stage.badge}</span>
                  </div>

                  <h3 style={{ margin: '0 0 4px', fontSize: '0.88rem', fontWeight: 700, color: 'var(--text-main)' }}>
                    {stage.title}
                  </h3>
                  <p style={{ margin: '0 0 10px', fontSize: '0.72rem', color: 'var(--text-muted)', lineHeight: 1.45, flex: 1 }}>
                    {stage.desc}
                  </p>

                  {/* Table bars */}
                  <div style={{ display: 'flex', flexDirection: 'column', gap: '5px' }}>
                    {stage.tables.map(t => {
                      const count = stats[t.key];
                      const pct = typeof count === 'number' ? Math.max(4, (count / maxTable) * 100) : 0;
                      return (
                        <div key={t.key}>
                          <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: '0.68rem', marginBottom: '2px' }}>
                            <span style={{ color: 'var(--text-muted)', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap', maxWidth: '120px' }}>
                              {t.label || t.key}
                            </span>
                            <span style={{ color: stage.color, fontWeight: 700, fontFamily: 'monospace', fontSize: '0.65rem' }}>
                              {fmt(count)}
                            </span>
                          </div>
                          <div style={{ height: 4, background: 'var(--border)', borderRadius: 2, overflow: 'hidden' }}>
                            <div style={{
                              height: '100%', borderRadius: 2, background: stage.gradient,
                              width: animatedBars ? `${pct}%` : '0%',
                              transition: 'width 0.8s cubic-bezier(0.4, 0, 0.2, 1)',
                              transitionDelay: `${idx * 150}ms`
                            }} />
                          </div>
                        </div>
                      );
                    })}
                  </div>
                </div>

                {/* Arrow */}
                {idx < STAGES.length - 1 && (
                  <div style={{ display: 'flex', alignItems: 'center', padding: '0 2px', flexShrink: 0 }}>
                    <svg width="28" height="20" viewBox="0 0 28 20" style={{ opacity: 0.4 }}>
                      <defs>
                        <linearGradient id={`arrow-${idx}`} x1="0%" y1="0%" x2="100%" y2="0%">
                          <stop offset="0%" stopColor={stage.color} />
                          <stop offset="100%" stopColor={STAGES[idx + 1].color} />
                        </linearGradient>
                      </defs>
                      <line x1="0" y1="10" x2="18" y2="10" stroke={`url(#arrow-${idx})`} strokeWidth="2" />
                      <polygon points="16,5 24,10 16,15" fill={STAGES[idx + 1].color} />
                    </svg>
                  </div>
                )}
              </div>
            );
          })}
        </div>

        {/* Detail panel */}
        {activeIdx !== null && (() => {
          const s = STAGES[activeIdx];
          return (
            <div style={{
              marginTop: '1.5rem', background: `${s.color}08`,
              border: `1px solid ${s.color}30`, borderRadius: '16px',
              padding: '1.5rem', animation: 'slideUp 0.3s ease',
            }}>
              <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: '1.5rem' }}>
                {/* Tables */}
                <div>
                  <p style={{ margin: '0 0 8px', fontWeight: 700, fontSize: '0.8rem', color: s.color, textTransform: 'uppercase', letterSpacing: '0.05em' }}>
                    Tables ({s.tables.length})
                  </p>
                  {s.tables.map(t => (
                    <div key={t.key} style={{
                      display: 'flex', justifyContent: 'space-between', alignItems: 'center',
                      padding: '8px 12px', borderRadius: '10px',
                      background: 'var(--bg-elevated)', marginBottom: '6px',
                      border: '1px solid var(--border)',
                    }}>
                      <span style={{ fontFamily: 'monospace', fontSize: '0.78rem', color: 'var(--text-main)' }}>{t.label || t.key}</span>
                      <span style={{
                        background: `${s.color}15`, color: s.color,
                        padding: '2px 10px', borderRadius: '8px',
                        fontWeight: 700, fontSize: '0.75rem', fontFamily: 'monospace'
                      }}>{fmt(stats[t.key])} lignes</span>
                    </div>
                  ))}
                </div>
                {/* Operations */}
                <div>
                  <p style={{ margin: '0 0 8px', fontWeight: 700, fontSize: '0.8rem', color: s.color, textTransform: 'uppercase', letterSpacing: '0.05em' }}>
                    Opérations
                  </p>
                  {s.ops.map(op => (
                    <div key={op} style={{
                      display: 'flex', alignItems: 'center', gap: '8px',
                      padding: '8px 12px', borderRadius: '10px',
                      background: 'var(--bg-elevated)', marginBottom: '6px',
                      border: '1px solid var(--border)',
                      fontSize: '0.8rem', color: 'var(--text-main)'
                    }}>
                      <span style={{ color: s.color, fontWeight: 800, fontSize: '0.9rem' }}>›</span> {op}
                    </div>
                  ))}
                </div>
                {/* Tech details */}
                <div>
                  <p style={{ margin: '0 0 8px', fontWeight: 700, fontSize: '0.8rem', color: s.color, textTransform: 'uppercase', letterSpacing: '0.05em' }}>
                    Détails techniques
                  </p>
                  <div style={{
                    padding: '12px 14px', borderRadius: '10px',
                    background: 'var(--bg-elevated)', border: '1px solid var(--border)',
                    fontSize: '0.8rem', color: 'var(--text-muted)', lineHeight: 1.6,
                    fontStyle: 'italic'
                  }}>
                    {s.techDetails}
                  </div>
                </div>
              </div>
            </div>
          );
        })()}
      </div>

      {/* Data flow visualization */}
      <div style={{
        background: 'var(--bg-elevated)', border: '1px solid var(--border)',
        borderRadius: '20px', padding: '2rem', marginBottom: '1.5rem',
      }}>
        <h2 style={{ margin: '0 0 1.5rem', fontSize: '1rem', fontWeight: 700, color: 'var(--text-main)' }}>
          Volume de données par couche
        </h2>
        <div style={{ display: 'flex', flexDirection: 'column', gap: '10px' }}>
          {STAGES.map((stage, idx) => {
            const stageTotal = stage.tables.reduce((sum, t) => sum + (typeof stats[t.key] === 'number' ? stats[t.key] : 0), 0);
            const pct = totalRows > 0 ? Math.max(3, (stageTotal / totalRows) * 100) : 0;
            return (
              <div key={stage.id} style={{ display: 'flex', alignItems: 'center', gap: '14px' }}>
                <div style={{ width: '140px', display: 'flex', alignItems: 'center', gap: '8px', flexShrink: 0 }}>
                  <div style={{
                    width: 28, height: 28, borderRadius: '8px', background: stage.gradient,
                    display: 'flex', alignItems: 'center', justifyContent: 'center',
                    fontSize: '0.7rem', fontWeight: 800, color: '#fff', flexShrink: 0
                  }}>{stage.step}</div>
                  <span style={{ fontSize: '0.75rem', fontWeight: 600, color: 'var(--text-main)', whiteSpace: 'nowrap' }}>{stage.title.split(' ')[0]}</span>
                </div>
                <div style={{ flex: 1, height: 24, background: 'var(--border)', borderRadius: 8, overflow: 'hidden', position: 'relative' }}>
                  <div style={{
                    height: '100%', borderRadius: 8, background: stage.gradient,
                    width: animatedBars ? `${pct}%` : '0%',
                    transition: 'width 1s cubic-bezier(0.4, 0, 0.2, 1)',
                    transitionDelay: `${idx * 200}ms`,
                    display: 'flex', alignItems: 'center', justifyContent: 'flex-end', paddingRight: '8px',
                  }}>
                    {pct > 15 && <span style={{ fontSize: '0.65rem', fontWeight: 700, color: '#fff' }}>{fmt(stageTotal)}</span>}
                  </div>
                </div>
                {pct <= 15 && <span style={{ fontSize: '0.72rem', fontWeight: 700, color: stage.color, minWidth: '60px' }}>{fmt(stageTotal)}</span>}
              </div>
            );
          })}
        </div>
      </div>

      {/* Transformation cards */}
      <div style={{
        background: 'var(--bg-elevated)', border: '1px solid var(--border)',
        borderRadius: '20px', padding: '2rem',
      }}>
        <h2 style={{ margin: '0 0 1.5rem', fontSize: '1rem', fontWeight: 700, color: 'var(--text-main)' }}>
          Transformations appliquées
        </h2>
        <div style={{ position: 'relative', paddingLeft: '28px' }}>
          {/* Vertical line */}
          <div style={{
            position: 'absolute', left: '12px', top: '8px', bottom: '8px', width: '2px',
            background: 'linear-gradient(to bottom, #0f2744, #1e3a5f, #2a5082, #3b6fa0, #4a8ec2, #5ba3d9)',
            borderRadius: '1px'
          }} />

          {STAGES.map((stage, idx) => (
            <div key={stage.id} style={{ position: 'relative', marginBottom: idx < STAGES.length - 1 ? '16px' : 0 }}>
              {/* Dot on line */}
              <div style={{
                position: 'absolute', left: '-22px', top: '18px',
                width: 12, height: 12, borderRadius: '50%',
                background: stage.gradient, border: '2px solid var(--bg-elevated)',
                boxShadow: `0 0 0 3px ${stage.color}30`,
              }} />

              <div style={{
                background: 'var(--bg-surface)', borderRadius: '14px',
                padding: '1.1rem 1.25rem', borderLeft: `3px solid ${stage.color}`,
                transition: 'all 0.2s ease',
              }}
              onMouseEnter={e => { e.currentTarget.style.background = `${stage.color}08`; e.currentTarget.style.transform = 'translateX(4px)'; }}
              onMouseLeave={e => { e.currentTarget.style.background = 'var(--bg-surface)'; e.currentTarget.style.transform = 'none'; }}
              >
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '6px' }}>
                  <h4 style={{ margin: 0, fontSize: '0.88rem', fontWeight: 700, color: 'var(--text-main)' }}>
                    Étape {idx + 1} — {stage.title}
                  </h4>
                  <span style={{
                    fontSize: '0.6rem', fontWeight: 800, letterSpacing: '0.06em',
                    background: `${stage.color}15`, color: stage.color,
                    padding: '2px 8px', borderRadius: '6px'
                  }}>{stage.badge}</span>
                </div>
                <p style={{ margin: '0 0 8px', fontSize: '0.78rem', color: 'var(--text-muted)', lineHeight: 1.5 }}>
                  {stage.techDetails}
                </p>
                <div style={{ display: 'flex', flexWrap: 'wrap', gap: '4px' }}>
                  {stage.ops.map(op => (
                    <span key={op} style={{
                      fontSize: '0.62rem', fontWeight: 700, letterSpacing: '0.04em',
                      background: `${stage.color}12`, color: stage.color,
                      padding: '3px 9px', borderRadius: '6px', fontFamily: 'monospace'
                    }}>{op}</span>
                  ))}
                </div>
              </div>
            </div>
          ))}
        </div>
      </div>

      <style>{`
        @keyframes slideUp {
          from { opacity: 0; transform: translateY(12px); }
          to   { opacity: 1; transform: translateY(0); }
        }
        @keyframes pulse {
          0%, 100% { opacity: 1; }
          50% { opacity: 0.4; }
        }
      `}</style>
    </div>
  );
}
