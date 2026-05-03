import { useEffect, useState } from 'react';
import api from '../api/axios';

const DataCoverage = () => {
  const [report, setReport] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    api.get('/dashboard/data-coverage')
      .then(res => setReport(res.data))
      .catch(console.error)
      .finally(() => setLoading(false));
  }, []);

  if (loading) return <div className="page" style={{ padding: '24px' }}>Chargement du rapport...</div>;

  return (
    <div className="page" style={{ padding: '24px', backgroundColor: 'var(--bg-page)', minHeight: '100vh' }}>
      <div style={{ marginBottom: '24px' }}>
        <h1 style={{ margin: 0, fontSize: '24px', fontWeight: 800, color: 'var(--text-main)' }}>Audit de Couverture des Données</h1>
        <p style={{ margin: '4px 0 0', color: 'var(--text-muted)' }}>Diagnostic d'utilisation des colonnes et tables dans le dashboard</p>
      </div>

      <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '24px' }}>
        {report && Object.entries(report.tables).map(([tableName, stats]) => (
          <div key={tableName} className="surface surface-pad">
            <h3 style={{ margin: '0 0 16px', fontSize: '1.1rem', fontWeight: 700, color: 'var(--text-main)', display: 'flex', justifyContent: 'space-between' }}>
              <span>{tableName}</span>
              <span style={{ fontSize: '0.8rem', background: 'var(--bg-surface)', padding: '2px 8px', borderRadius: '4px', color: 'var(--text-muted)' }}>{stats.total_lignes.toLocaleString()} lignes</span>
            </h3>
            
            <div style={{ marginBottom: '15px' }}>
               <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: '0.9rem', marginBottom: '4px', color: 'var(--text-main)' }}>
                  <span>Taux d'utilisation</span>
                  <span style={{ fontWeight: 700 }}>{stats.taux_utilisation}</span>
               </div>
               <div style={{ height: '8px', background: 'var(--border)', borderRadius: '4px', overflow: 'hidden' }}>
                  <div style={{ width: stats.taux_utilisation, height: '100%', background: 'var(--primary)' }} />
               </div>
            </div>

            <h4 style={{ fontSize: '0.85rem', color: 'var(--text-muted)', textTransform: 'uppercase', marginBottom: '8px' }}>Colonnes utilisées</h4>
            <div style={{ display: 'flex', flexWrap: 'wrap', gap: '6px' }}>
               {stats.colonnes_utilisees.map(col => (
                 <span key={col} style={{ fontSize: '0.75rem', background: 'var(--primary-soft)', color: 'var(--primary)', border: '1px solid var(--border)', padding: '2px 8px', borderRadius: '999px' }}>
                   {col}
                 </span>
               ))}
            </div>
          </div>
        ))}
      </div>

      <div className="surface surface-pad" style={{ marginTop: '24px', border: '1px solid var(--primary)', background: 'var(--primary-soft)' }}>
         <h3 style={{ margin: '0 0 12px', fontSize: '1.1rem', fontWeight: 700, color: 'var(--primary)' }}>🚀 Recommandations d'Amélioration</h3>
         <ul style={{ margin: 0, paddingLeft: '20px', display: 'flex', flexDirection: 'column', gap: '8px', color: 'var(--text-main)' }}>
            {report && report.recommandations.map((rec, i) => (
              <li key={i}>{rec}</li>
            ))}
         </ul>
      </div>

      <style>{`
        .surface { background: var(--bg-elevated); border-radius: 12px; border: 1px solid var(--border); }
        .surface-pad { padding: 20px; }
      `}</style>
    </div>
  );
};

export default DataCoverage;
