import { useEffect, useState } from 'react';
import api from '../api/axios';

export default function Alerts() {
  const [alerts, setAlerts]   = useState([]);
  const [loading, setLoading] = useState(true);
  const [filter, setFilter]   = useState('all'); // all | ouverte | resolue

  const load = () => {
    setLoading(true);
    api.get('/alerts').then(r => { setAlerts(r.data); setLoading(false); })
      .catch(() => {
        // Fallback: demo data if endpoint not ready
        setAlerts([
          { id: 1, start_date: '2026-01-21', nom_service: 'SHOFHA', numero_court: '2168000', keyword: 'mb1', nom_fournisseur: 'TOPNET', seuil_pct: 25.00, count_nb_sms: 2707, motif: 'Volume anormalement élevé', status: false },
          { id: 2, start_date: '2026-01-22', nom_service: 'PLAY WIN', numero_court: '2168000', keyword: 'plw1', nom_fournisseur: 'TOPNET', seuil_pct: 15.00, count_nb_sms: 845, motif: 'Pic de trafic inhabituel', status: false },
          { id: 3, start_date: '2026-01-20', nom_service: 'MELODY', numero_court: '2168000', keyword: 'mel1', nom_fournisseur: 'TOPNET', seuil_pct: 10.50, count_nb_sms: 363, motif: 'Variation suspecte', status: true },
        ]);
        setLoading(false);
      });
  };

  useEffect(() => { load(); }, []);

  const filtered = filter === 'all' ? alerts : alerts.filter(a => filter === 'resolue' ? a.status : !a.status);

  const resolve = async (id) => {
    try {
      await api.put(`/alerts/${id}`, { status: true });
    } catch {
      // update locally for demo
    }
    setAlerts(prev => prev.map(a => a.id === id ? { ...a, status: true } : a));
  };

  const ouvertes = alerts.filter(a => !a.status).length;
  const resolues = alerts.filter(a => a.status).length;

  return (
    <div style={{ padding: '2rem' }}>
      <h1 style={{ margin: '0 0 0.4rem', color: '#1a237e', fontSize: '1.6rem' }}>🔔 Alertes</h1>
      <p style={{ margin: '0 0 2rem', color: '#888', fontSize: '0.9rem' }}>Surveillance des anomalies de trafic SMS+</p>

      {/* Stats */}
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: '1rem', marginBottom: '2rem' }}>
        {[
          { label: 'Total Alertes',   value: alerts.length, color: '#1a237e', bg: '#e8eaf6', icon: '🔔' },
          { label: 'Ouvertes',        value: ouvertes,       color: '#e65100', bg: '#fff3e0', icon: '⚠️' },
          { label: 'Résolues',        value: resolues,       color: '#2e7d32', bg: '#e8f5e9', icon: '✅' },
        ].map(k => (
          <div key={k.label} style={{ background: 'white', borderRadius: '12px', padding: '1.5rem', boxShadow: '0 2px 12px rgba(0,0,0,0.08)', display: 'flex', alignItems: 'center', gap: '1rem' }}>
            <div style={{ width: '50px', height: '50px', borderRadius: '12px', background: k.bg, display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: '1.4rem' }}>{k.icon}</div>
            <div>
              <p style={{ margin: 0, color: '#888', fontSize: '0.85rem' }}>{k.label}</p>
              <h3 style={{ margin: '0.2rem 0 0', color: k.color, fontSize: '1.6rem', fontWeight: 800 }}>{k.value}</h3>
            </div>
          </div>
        ))}
      </div>

      {/* Filter tabs */}
      <div style={{ display: 'flex', gap: '0.5rem', marginBottom: '1.5rem' }}>
        {[['all', 'Toutes'], ['ouverte', 'Ouvertes'], ['resolue', 'Résolues']].map(([val, label]) => (
          <button key={val} onClick={() => setFilter(val)} style={{
            padding: '0.5rem 1.25rem', borderRadius: '20px', border: 'none',
            background: filter === val ? '#1a237e' : '#f0f0f0',
            color: filter === val ? 'white' : '#666',
            cursor: 'pointer', fontWeight: 600, fontSize: '0.88rem',
          }}>{label}</button>
        ))}
      </div>

      {loading ? (
        <p style={{ textAlign: 'center', color: '#888', padding: '3rem' }}>Chargement...</p>
      ) : (
        <div style={{ display: 'flex', flexDirection: 'column', gap: '1rem' }}>
          {filtered.length === 0 ? (
            <div style={{ background: 'white', borderRadius: '12px', padding: '3rem', textAlign: 'center', color: '#888', boxShadow: '0 2px 12px rgba(0,0,0,0.08)' }}>
              <div style={{ fontSize: '3rem', marginBottom: '1rem' }}>✅</div>
              <p style={{ margin: 0 }}>Aucune alerte dans cette catégorie</p>
            </div>
          ) : filtered.map(alert => (
            <div key={alert.id} style={{
              background: 'white', borderRadius: '12px', padding: '1.25rem 1.5rem',
              boxShadow: '0 2px 12px rgba(0,0,0,0.08)',
              borderLeft: `4px solid ${alert.status ? '#4caf50' : '#ff6d00'}`,
            }}>
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start' }}>
                <div style={{ flex: 1 }}>
                  <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem', marginBottom: '0.5rem' }}>
                    <span style={{ fontSize: '1.1rem' }}>{alert.status ? '✅' : '⚠️'}</span>
                    <strong style={{ color: '#1a237e', fontSize: '1rem' }}>{alert.nom_service}</strong>
                    <span style={{ background: '#e8eaf6', color: '#1a237e', padding: '0.15rem 0.5rem', borderRadius: '20px', fontSize: '0.78rem', fontWeight: 600 }}>{alert.keyword}</span>
                    <span style={{ background: alert.status ? '#e8f5e9' : '#fff3e0', color: alert.status ? '#2e7d32' : '#e65100', padding: '0.15rem 0.5rem', borderRadius: '20px', fontSize: '0.78rem', fontWeight: 600 }}>
                      {alert.status ? 'Résolue' : 'Ouverte'}
                    </span>
                  </div>
                  <p style={{ margin: '0 0 0.5rem', color: '#555', fontSize: '0.9rem' }}>{alert.motif}</p>
                  <div style={{ display: 'flex', gap: '1.5rem', fontSize: '0.82rem', color: '#888' }}>
                    <span>📅 {alert.start_date}</span>
                    <span>🏢 {alert.nom_fournisseur}</span>
                    <span>📱 {parseInt(alert.count_nb_sms || 0).toLocaleString()} SMS</span>
                    <span>📊 Seuil: {parseFloat(alert.seuil_pct || 0).toFixed(2)}%</span>
                  </div>
                </div>
                {!alert.status && (
                  <button onClick={() => resolve(alert.id)} style={{
                    padding: '0.6rem 1.25rem', background: '#e8f5e9', color: '#2e7d32',
                    border: '1px solid #a5d6a7', borderRadius: '8px', cursor: 'pointer',
                    fontWeight: 600, fontSize: '0.85rem', whiteSpace: 'nowrap', marginLeft: '1rem',
                  }}>
                    ✅ Résoudre
                  </button>
                )}
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
