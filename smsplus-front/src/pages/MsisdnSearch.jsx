import { useState } from 'react';
import api from '../api/axios';

export default function MsisdnSearch() {
  const [msisdn, setMsisdn]     = useState('');
  const [results, setResults]   = useState(null);
  const [loading, setLoading]   = useState(false);
  const [error, setError]       = useState('');

  const search = async () => {
    if (!msisdn.trim()) { setError('Veuillez saisir un numéro MSISDN'); return; }
    setLoading(true); setError(''); setResults(null);
    try {
      const res = await api.get(`/reclamations/${msisdn.trim()}`);
      setResults(res.data);
    } catch {
      setError('Erreur lors de la recherche');
    } finally {
      setLoading(false);
    }
  };

  const statusColor = (s) => ({ ouverte: '#e65100', en_cours: '#0288d1', resolue: '#2e7d32' }[s] || '#666');
  const statusBg    = (s) => ({ ouverte: '#fff3e0', en_cours: '#e3f2fd', resolue: '#e8f5e9' }[s] || '#f5f5f5');

  return (
    <div style={{ padding: '2rem' }}>
      <h1 style={{ margin: '0 0 0.4rem', color: '#1a237e', fontSize: '1.6rem' }}>🔍 Recherche par MSISDN</h1>
      <p style={{ margin: '0 0 2rem', color: '#888', fontSize: '0.9rem' }}>Recherchez les réclamations d'un abonné par son numéro</p>

      {/* Search bar */}
      <div style={{
        background: 'white', borderRadius: '12px', padding: '1.5rem',
        boxShadow: '0 2px 12px rgba(0,0,0,0.08)', marginBottom: '1.5rem',
      }}>
        <div style={{ display: 'flex', gap: '1rem', alignItems: 'flex-end' }}>
          <div style={{ flex: 1 }}>
            <label style={{ display: 'block', marginBottom: '0.5rem', fontWeight: 600, fontSize: '0.9rem', color: '#444' }}>
              Numéro MSISDN
            </label>
            <input
              type="text"
              value={msisdn}
              onChange={e => setMsisdn(e.target.value)}
              onKeyDown={e => e.key === 'Enter' && search()}
              placeholder="ex: 21698542320"
              style={{
                width: '100%', padding: '0.85rem 1rem',
                border: '2px solid #e0e0e0', borderRadius: '10px',
                fontSize: '1rem', boxSizing: 'border-box',
                fontFamily: 'monospace',
              }}
            />
          </div>
          <button
            onClick={search}
            disabled={loading}
            style={{
              padding: '0.85rem 2rem',
              background: 'linear-gradient(135deg, #1a237e, #0288d1)',
              color: 'white', border: 'none', borderRadius: '10px',
              cursor: loading ? 'not-allowed' : 'pointer',
              fontWeight: 700, fontSize: '0.95rem',
              boxShadow: '0 4px 12px rgba(26,35,126,0.3)',
              whiteSpace: 'nowrap',
            }}
          >
            {loading ? '⏳ Recherche...' : '🔍 Rechercher'}
          </button>
        </div>
        {error && (
          <p style={{ margin: '0.75rem 0 0', color: '#c62828', fontSize: '0.9rem' }}>⚠️ {error}</p>
        )}
      </div>

      {/* Results */}
      {results !== null && (
        <div style={{ background: 'white', borderRadius: '12px', boxShadow: '0 2px 12px rgba(0,0,0,0.08)', overflow: 'hidden' }}>
          <div style={{ padding: '1rem 1.5rem', borderBottom: '1px solid #f0f0f0', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
            <h3 style={{ margin: 0, color: '#1a237e' }}>
              Résultats pour <span style={{ fontFamily: 'monospace', background: '#e8eaf6', padding: '0.2rem 0.5rem', borderRadius: '6px' }}>{msisdn}</span>
            </h3>
            <span style={{ background: results.length > 0 ? '#fff3e0' : '#e8f5e9', color: results.length > 0 ? '#e65100' : '#2e7d32', padding: '0.3rem 0.75rem', borderRadius: '20px', fontSize: '0.85rem', fontWeight: 600 }}>
              {results.length} réclamation(s)
            </span>
          </div>

          {results.length === 0 ? (
            <div style={{ padding: '3rem', textAlign: 'center', color: '#888' }}>
              <div style={{ fontSize: '3rem', marginBottom: '1rem' }}>✅</div>
              <p style={{ margin: 0, fontSize: '1rem' }}>Aucune réclamation pour ce numéro</p>
            </div>
          ) : (
            <table style={{ width: '100%', borderCollapse: 'collapse' }}>
              <thead>
                <tr style={{ background: '#f5f7ff' }}>
                  {['ID', 'Description', 'Service', 'Statut', 'Date'].map(h => (
                    <th key={h} style={{ padding: '1rem', textAlign: 'left', fontSize: '0.85rem', color: '#666', fontWeight: 600, borderBottom: '2px solid #e8eaf6' }}>
                      {h}
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {results.map((r, i) => (
                  <tr key={r.id} style={{ background: i % 2 === 0 ? 'white' : '#fafafa' }}>
                    <td style={{ padding: '0.875rem 1rem', fontFamily: 'monospace', color: '#888', fontSize: '0.85rem' }}>#{r.id}</td>
                    <td style={{ padding: '0.875rem 1rem', color: '#333' }}>{r.description}</td>
                    <td style={{ padding: '0.875rem 1rem', color: '#666', fontSize: '0.9rem' }}>{r.service?.nom_service || '—'}</td>
                    <td style={{ padding: '0.875rem 1rem' }}>
                      <span style={{ background: statusBg(r.statut), color: statusColor(r.statut), padding: '0.25rem 0.75rem', borderRadius: '20px', fontSize: '0.82rem', fontWeight: 600 }}>
                        {r.statut}
                      </span>
                    </td>
                    <td style={{ padding: '0.875rem 1rem', color: '#888', fontSize: '0.85rem' }}>
                      {new Date(r.created_at).toLocaleDateString('fr-FR')}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </div>
      )}
    </div>
  );
}
