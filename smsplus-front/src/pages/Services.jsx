import { useEffect, useState } from 'react';
import api from '../api/axios';

const TYPE_OPTIONS = ['Service', 'jeu'];

export default function Services() {
  const [services, setServices] = useState([]);
  const [loading, setLoading]   = useState(true);
  const [error, setError] = useState('');
  const [showForm, setShowForm] = useState(false);
  const [editing, setEditing]   = useState(null);
  const [form, setForm] = useState({
    nom_fournisseur: '', nom_service: '', numero_court: '',
    keyword: '', type_service: 'Service', prix: '',
  });
  const [msg, setMsg] = useState('');

  const load = () => {
    setLoading(true);
    setError('');
    api.get('/services')
      .then(r => { setServices(r.data); setLoading(false); })
      .catch(() => {
        setError("Impossible de charger les services. Verifie l'API.");
        setLoading(false);
      });
  };

  useEffect(() => { load(); }, []);

  const openNew = () => {
    setEditing(null);
    setForm({ nom_fournisseur: '', nom_service: '', numero_court: '', keyword: '', type_service: 'Service', prix: '' });
    setShowForm(true);
  };

  const openEdit = (s) => {
    setEditing(s.id);
    setForm({ nom_fournisseur: s.nom_fournisseur, nom_service: s.nom_service, numero_court: s.numero_court, keyword: s.keyword, type_service: s.type_service, prix: s.prix });
    setShowForm(true);
  };

  const save = async () => {
    try {
      if (editing) {
        await api.put(`/services/${editing}`, form);
        setMsg('✅ Service modifié avec succès');
      } else {
        await api.post('/services', form);
        setMsg('✅ Service ajouté avec succès');
      }
      setShowForm(false);
      load();
      setTimeout(() => setMsg(''), 3000);
    } catch (e) {
      setMsg('❌ Erreur lors de l\'enregistrement');
    }
  };

  const del = async (id) => {
    if (!window.confirm('Supprimer ce service ?')) return;
    try {
      await api.delete(`/services/${id}`);
      setMsg('✅ Service supprimé');
      load();
    } catch {
      setMsg('❌ Erreur lors de la suppression');
    }
    setTimeout(() => setMsg(''), 3000);
  };

  return (
    <div style={{ padding: '2rem' }}>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '1.5rem' }}>
        <div>
          <h1 style={{ margin: 0, color: '#1a237e', fontSize: '1.6rem' }}>📋 Gestion des Services SMS+</h1>
          <p style={{ margin: '0.3rem 0 0', color: '#888', fontSize: '0.9rem' }}>{services.length} service(s) au total</p>
        </div>
        <button onClick={openNew} style={{
          background: 'linear-gradient(135deg, #1a237e, #0288d1)', color: 'white',
          border: 'none', borderRadius: '10px', padding: '0.75rem 1.5rem',
          cursor: 'pointer', fontWeight: 700, fontSize: '0.95rem',
          boxShadow: '0 4px 12px rgba(26,35,126,0.3)',
        }}>
          + Ajouter un service
        </button>
      </div>

      {msg && (
        <div style={{
          padding: '0.75rem 1rem', borderRadius: '8px', marginBottom: '1rem',
          background: msg.includes('✅') ? '#e8f5e9' : '#ffebee',
          color: msg.includes('✅') ? '#2e7d32' : '#c62828',
          border: `1px solid ${msg.includes('✅') ? '#a5d6a7' : '#ef9a9a'}`,
        }}>
          {msg}
        </div>
      )}
      {error && (
        <div style={{ padding: '0.75rem 1rem', borderRadius: '8px', marginBottom: '1rem', background: '#ffebee', color: '#c62828', border: '1px solid #ef9a9a' }}>
          ⚠️ {error}
        </div>
      )}

      {/* Modal Form */}
      {showForm && (
        <div style={{
          position: 'fixed', inset: 0, background: 'rgba(0,0,0,0.5)',
          display: 'flex', alignItems: 'center', justifyContent: 'center', zIndex: 1000,
        }}>
          <div style={{
            background: 'white', borderRadius: '16px', padding: '2rem',
            width: '100%', maxWidth: '500px', boxShadow: '0 20px 60px rgba(0,0,0,0.3)',
          }}>
            <h2 style={{ margin: '0 0 1.5rem', color: '#1a237e' }}>
              {editing ? '✏️ Modifier le service' : '➕ Nouveau service'}
            </h2>
            {[
              { key: 'nom_fournisseur', label: 'Fournisseur', placeholder: 'ex: TOPNET' },
              { key: 'nom_service', label: 'Nom du service', placeholder: 'ex: SHOFHA' },
              { key: 'numero_court', label: 'Numéro court', placeholder: 'ex: 2168000' },
              { key: 'keyword', label: 'Keyword', placeholder: 'ex: mb1' },
              { key: 'prix', label: 'Prix (DT)', placeholder: 'ex: 0.500', type: 'number' },
            ].map(f => (
              <div key={f.key} style={{ marginBottom: '1rem' }}>
                <label style={{ display: 'block', marginBottom: '0.4rem', fontWeight: 600, fontSize: '0.9rem', color: '#444' }}>
                  {f.label}
                </label>
                <input
                  type={f.type || 'text'}
                  value={form[f.key]}
                  onChange={e => setForm({ ...form, [f.key]: e.target.value })}
                  placeholder={f.placeholder}
                  style={{
                    width: '100%', padding: '0.7rem 1rem',
                    border: '2px solid #e0e0e0', borderRadius: '8px',
                    fontSize: '0.95rem', boxSizing: 'border-box',
                  }}
                />
              </div>
            ))}
            <div style={{ marginBottom: '1.5rem' }}>
              <label style={{ display: 'block', marginBottom: '0.4rem', fontWeight: 600, fontSize: '0.9rem', color: '#444' }}>Type</label>
              <select value={form.type_service} onChange={e => setForm({ ...form, type_service: e.target.value })}
                style={{ width: '100%', padding: '0.7rem 1rem', border: '2px solid #e0e0e0', borderRadius: '8px', fontSize: '0.95rem' }}>
                {TYPE_OPTIONS.map(t => <option key={t}>{t}</option>)}
              </select>
            </div>
            <div style={{ display: 'flex', gap: '0.75rem', justifyContent: 'flex-end' }}>
              <button onClick={() => setShowForm(false)} style={{
                padding: '0.7rem 1.5rem', border: '2px solid #e0e0e0',
                background: 'white', borderRadius: '8px', cursor: 'pointer', fontWeight: 600,
              }}>Annuler</button>
              <button onClick={save} style={{
                padding: '0.7rem 1.5rem', background: 'linear-gradient(135deg, #1a237e, #0288d1)',
                color: 'white', border: 'none', borderRadius: '8px', cursor: 'pointer', fontWeight: 700,
              }}>Enregistrer</button>
            </div>
          </div>
        </div>
      )}

      {/* Table */}
      {loading ? (
        <p style={{ color: '#888', textAlign: 'center', padding: '3rem' }}>Chargement...</p>
      ) : (
        <div style={{ background: 'white', borderRadius: '12px', boxShadow: '0 2px 12px rgba(0,0,0,0.08)', overflow: 'hidden' }}>
          <table style={{ width: '100%', borderCollapse: 'collapse' }}>
            <thead>
              <tr style={{ background: '#f5f7ff' }}>
                {['Fournisseur', 'Service', 'N° Court', 'Keyword', 'Type', 'Prix', 'Statut', 'Actions'].map(h => (
                  <th key={h} style={{ padding: '1rem', textAlign: 'left', fontSize: '0.85rem', color: '#666', fontWeight: 600, borderBottom: '2px solid #e8eaf6' }}>
                    {h}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {services.map((s, i) => (
                <tr key={s.id} style={{ background: i % 2 === 0 ? 'white' : '#fafafa' }}>
                  <td style={{ padding: '0.875rem 1rem', fontWeight: 600, color: '#333' }}>{s.nom_fournisseur}</td>
                  <td style={{ padding: '0.875rem 1rem', color: '#333' }}>{s.nom_service}</td>
                  <td style={{ padding: '0.875rem 1rem', fontFamily: 'monospace', color: '#1a237e', fontWeight: 600 }}>{s.numero_court}</td>
                  <td style={{ padding: '0.875rem 1rem' }}>
                    <span style={{ background: '#e8eaf6', color: '#1a237e', padding: '0.2rem 0.6rem', borderRadius: '20px', fontSize: '0.82rem', fontWeight: 600 }}>
                      {s.keyword}
                    </span>
                  </td>
                  <td style={{ padding: '0.875rem 1rem' }}>
                    <span style={{ background: s.type_service === 'jeu' ? '#fff3e0' : '#e3f2fd', color: s.type_service === 'jeu' ? '#e65100' : '#0288d1', padding: '0.2rem 0.6rem', borderRadius: '20px', fontSize: '0.82rem' }}>
                      {s.type_service}
                    </span>
                  </td>
                  <td style={{ padding: '0.875rem 1rem', fontWeight: 600, color: '#2e7d32' }}>{parseFloat(s.prix).toFixed(3)} DT</td>
                  <td style={{ padding: '0.875rem 1rem' }}>
                    <span style={{ background: s.actif ? '#e8f5e9' : '#ffebee', color: s.actif ? '#2e7d32' : '#c62828', padding: '0.2rem 0.6rem', borderRadius: '20px', fontSize: '0.82rem' }}>
                      {s.actif ? 'Actif' : 'Inactif'}
                    </span>
                  </td>
                  <td style={{ padding: '0.875rem 1rem' }}>
                    <div style={{ display: 'flex', gap: '0.5rem' }}>
                      <button onClick={() => openEdit(s)} style={{ padding: '0.4rem 0.75rem', background: '#e3f2fd', color: '#0288d1', border: 'none', borderRadius: '6px', cursor: 'pointer', fontSize: '0.82rem', fontWeight: 600 }}>
                        ✏️ Modifier
                      </button>
                      <button onClick={() => del(s.id)} style={{ padding: '0.4rem 0.75rem', background: '#ffebee', color: '#c62828', border: 'none', borderRadius: '6px', cursor: 'pointer', fontSize: '0.82rem', fontWeight: 600 }}>
                        🗑️ Suppr.
                      </button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}
