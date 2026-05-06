import { useState, useEffect } from 'react';
import api from '../api/axios';

const STEPS = [
  {
    id: 'file',
    title: 'Fichiers CDR Bruts',
    desc: 'Fichiers CSV/XLS provenant des switchs (OCC/MMG).',
    icon: '📄',
    color: '#3b82f6',
    tables: ['Fichier .csv', 'Fichier .xls']
  },
  {
    id: 'tmp',
    title: 'Zone Tampon (TMP)',
    desc: 'Données chargées en masse sans transformation.',
    icon: '📥',
    color: '#8b5cf6',
    tables: ['ra_t_tmp_occ', 'ra_t_tmp_mmg']
  },
  {
    id: 'normalized',
    title: 'Détails Normalisés',
    desc: 'Mapping des champs, typage des dates, filtrage des erreurs.',
    icon: '⚙️',
    color: '#10b981',
    tables: ['ra_t_occ_cdr_detail', 'ra_t_mmg_cdr_det']
  },
  {
    id: 'aggregated',
    title: 'Agrégats BI',
    desc: 'Données groupées par heure/service pour le dashboard.',
    icon: '📊',
    color: '#f59e0b',
    tables: ['ra_t_occ_agg', 'ra_t_mmg_agg']
  }
];

export default function DataLineage() {
  const [active, setActive] = useState(null);
  const [stats, setStats] = useState({});

  useEffect(() => {
    api.get('/etl/lineage-stats')
      .then(res => setStats(res.data))
      .catch(err => console.error("Could not load lineage stats", err));
  }, []);

  return (
    <div className="page" style={{ padding: '2rem', maxWidth: '1200px', margin: '0 auto' }}>
      <div style={{ marginBottom: '3rem', textAlign: 'center' }}>
        <h1 style={{ fontSize: '2rem', fontWeight: 800, color: 'var(--text-main)', margin: '0 0 0.5rem' }}>
          Interactive Data Lineage
        </h1>
        <p style={{ color: 'var(--text-muted)', fontSize: '1.1rem' }}>
          Visualisation du flux de données de l'import jusqu'au reporting
        </p>
      </div>

      <div style={{ 
        display: 'flex', 
        justifyContent: 'space-between', 
        alignItems: 'flex-start',
        position: 'relative',
        gap: '2rem',
        padding: '2rem 0'
      }}>
        {/* Connection Line */}
        <div style={{ 
          position: 'absolute', 
          top: '85px', 
          left: '10%', 
          right: '10%', 
          height: '4px', 
          background: 'var(--border)', 
          zIndex: 0,
          borderRadius: '2px'
        }} />

        {STEPS.map((step, idx) => (
          <div 
            key={step.id}
            style={{ 
              flex: 1, 
              display: 'flex', 
              flexDirection: 'column', 
              alignItems: 'center', 
              zIndex: 1,
              cursor: 'pointer',
              animation: `fadeInUp 0.5s ease forwards ${idx * 0.1}s`,
              opacity: 0
            }}
            onClick={() => setActive(step === active ? null : step)}
          >
            <div style={{ 
              width: '70px', 
              height: '70px', 
              borderRadius: '20px', 
              background: step.color, 
              display: 'flex', 
              alignItems: 'center', 
              justifyContent: 'center',
              fontSize: '2rem',
              boxShadow: `0 0 20px ${step.color}44`,
              border: active === step ? '4px solid white' : 'none',
              transition: 'all 0.2s ease',
              transform: active === step ? 'scale(1.1)' : 'none'
            }}>
              {step.icon}
            </div>
            <h3 style={{ marginTop: '1rem', fontSize: '1.1rem', fontWeight: 700, textAlign: 'center', color: 'var(--text-main)' }}>{step.title}</h3>
            <p style={{ 
              fontSize: '0.85rem', 
              color: 'var(--text-muted)', 
              textAlign: 'center', 
              marginTop: '0.5rem',
              maxWidth: '180px'
            }}>
              {step.desc}
            </p>

            {active === step && (
              <div 
                style={{ 
                  marginTop: '1.5rem', 
                  background: 'var(--bg-elevated)', 
                  padding: '1rem', 
                  borderRadius: '12px',
                  border: `1px solid ${step.color}`,
                  width: '260px',
                  boxShadow: '0 8px 32px rgba(0,0,0,0.15)',
                  animation: 'popIn 0.3s ease'
                }}
              >
                <p style={{ fontWeight: 600, fontSize: '0.8rem', marginBottom: '0.5rem', color: step.color }}>TABLES :</p>
                {step.tables.map(t => {
                  const count = stats[t];
                  return (
                    <div key={t} style={{ fontFamily: 'monospace', fontSize: '0.8rem', padding: '4px 0', color: 'var(--text-main)', display: 'flex', justifyContent: 'space-between' }}>
                      <span>• {t}</span>
                      {count !== undefined && <span style={{ color: 'var(--text-muted)' }}>{count.toLocaleString('fr-FR')} lignes</span>}
                    </div>
                  );
                })}
              </div>
            )}
          </div>
        ))}
      </div>

      <div style={{ marginTop: '4rem', padding: '2rem', background: 'var(--bg-elevated)', borderRadius: '24px', border: '1px solid var(--border)' }}>
        <h2 style={{ fontSize: '1.3rem', fontWeight: 700, marginBottom: '1.5rem', color: 'var(--text-main)' }}>Processus de transformation</h2>
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(300px, 1fr))', gap: '2rem' }}>
          <div className="saas-surface" style={{ padding: '1.5rem', borderLeft: '4px solid #8b5cf6', background: 'var(--bg-surface)' }}>
            <h4 style={{ margin: '0 0 0.5rem', color: 'var(--text-main)' }}>1. Injection (ETL Batch)</h4>
            <p style={{ fontSize: '0.9rem', color: 'var(--text-muted)' }}>
              Les fichiers sont analysés ligne par ligne. Les doublons sont détectés dès cette étape pour éviter la pollution de la zone tampon.
            </p>
          </div>
          <div className="saas-surface" style={{ padding: '1.5rem', borderLeft: '4px solid #10b981', background: 'var(--bg-surface)' }}>
            <h4 style={{ margin: '0 0 0.5rem', color: 'var(--text-main)' }}>2. Normalisation (Mapping)</h4>
            <p style={{ fontSize: '0.9rem', color: 'var(--text-muted)' }}>
              Conversion des formats propriétaires switch (OCC/MMG) vers un format standard SQL. Calcul automatique de la commission TT et identification des services VAS.
            </p>
          </div>
          <div className="saas-surface" style={{ padding: '1.5rem', borderLeft: '4px solid #f59e0b', background: 'var(--bg-surface)' }}>
            <h4 style={{ margin: '0 0 0.5rem', color: 'var(--text-main)' }}>3. Agrégation (Real-time sync)</h4>
            <p style={{ fontSize: '0.9rem', color: 'var(--text-muted)' }}>
              Résumé des millions de lignes de détails en quelques milliers de lignes d'agrégats pour permettre une consultation ultra-rapide des graphiques.
            </p>
          </div>
        </div>
      </div>

      <style>{`
        @keyframes fadeInUp {
          from { opacity: 0; transform: translateY(20px); }
          to { opacity: 1; transform: translateY(0); }
        }
        @keyframes popIn {
          from { opacity: 0; transform: scale(0.9); }
          to { opacity: 1; transform: scale(1); }
        }
      `}</style>
    </div>
  );
}
