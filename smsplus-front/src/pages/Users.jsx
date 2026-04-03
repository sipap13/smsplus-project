import { useEffect, useState } from 'react';
import api from '../api/axios';

const ROLES = ['ADMIN', 'ANALYSTE_OP', 'ANALYSTE_BUSS'];

export default function Users() {
  const [users, setUsers]     = useState([]);
  const [loading, setLoading] = useState(true);
  const [showForm, setShowForm] = useState(false);
  const [form, setForm] = useState({ email: '', password: '', direction: 'Assurance et Fraude', role: 'ANALYSTE_OP', tel: '' });
  const [msg, setMsg]   = useState('');

  const load = () => {
    setLoading(true);
    api.get('/users').then(r => { setUsers(r.data); setLoading(false); })
      .catch(() => {
        // Demo fallback
        setUsers([
          { id: 1, email: 'admin@tt.tn', direction: 'Assurance et Fraude', role: 'ADMIN', tel: '+216 71 000 001', actif: true, created_at: '2026-03-31' },
          { id: 2, email: 'analyste.op@tt.tn', direction: 'Assurance et Fraude', role: 'ANALYSTE_OP', tel: '+216 71 000 002', actif: true, created_at: '2026-03-31' },
          { id: 3, email: 'analyste.buss@tt.tn', direction: 'Assurance et Fraude', role: 'ANALYSTE_BUSS', tel: '+216 71 000 003', actif: true, created_at: '2026-03-31' },
        ]);
        setLoading(false);
      });
  };

  useEffect(() => { load(); }, []);

  const save = async () => {
    if (!form.email || !form.password) { setMsg('❌ Email et mot de passe requis'); return; }
    try {
      await api.post('/users', form);
      setMsg('✅ Utilisateur créé avec succès');
      setShowForm(false);
      load();
    } catch {
      setMsg('❌ Erreur lors de la création');
    }
    setTimeout(() => setMsg(''), 3000);
  };

  const toggleActive = async (user) => {
    try {
      await api.put(`/users/${user.id}`, { actif: !user.actif });
    } catch {
      // ignore (UI optimistic)
    }
    setUsers(prev => prev.map(u => u.id === user.id ? { ...u, actif: !u.actif } : u));
  };

  const roleColor = (r) => ({ ADMIN: '#4a148c', ANALYSTE_OP: '#0288d1', ANALYSTE_BUSS: '#2e7d32' }[r] || '#666');
  const roleBg    = (r) => ({ ADMIN: '#f3e5f5', ANALYSTE_OP: '#e3f2fd', ANALYSTE_BUSS: '#e8f5e9' }[r] || '#f5f5f5');

  return (
    <div style={{ padding: '2rem' }}>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '1.5rem' }}>
        <div>
          <h1 style={{ margin: 0, color: '#1a237e', fontSize: '1.6rem' }}>👥 Gestion des Utilisateurs</h1>
          <p style={{ margin: '0.3rem 0 0', color: '#888', fontSize: '0.9rem' }}>{users.length} utilisateur(s) enregistré(s)</p>
        </div>
        <button onClick={() => { setShowForm(true); setForm({ email: '', password: '', direction: 'Assurance et Fraude', role: 'ANALYSTE_OP', tel: '' }); }} style={{
          background: 'linear-gradient(135deg, #1a237e, #0288d1)', color: 'white',
          border: 'none', borderRadius: '10px', padding: '0.75rem 1.5rem',
          cursor: 'pointer', fontWeight: 700, fontSize: '0.95rem',
          boxShadow: '0 4px 12px rgba(26,35,126,0.3)',
        }}>
          + Ajouter un utilisateur
        </button>
      </div>

      {msg && (
        <div style={{
          padding: '0.75rem 1rem', borderRadius: '8px', marginBottom: '1rem',
          background: msg.includes('✅') ? '#e8f5e9' : '#ffebee',
          color: msg.includes('✅') ? '#2e7d32' : '#c62828',
          border: `1px solid ${msg.includes('✅') ? '#a5d6a7' : '#ef9a9a'}`,
        }}>{msg}</div>
      )}

      {/* Modal */}
      {showForm && (
        <div style={{ position: 'fixed', inset: 0, background: 'rgba(0,0,0,0.5)', display: 'flex', alignItems: 'center', justifyContent: 'center', zIndex: 1000 }}>
          <div style={{ background: 'white', borderRadius: '16px', padding: '2rem', width: '100%', maxWidth: '450px', boxShadow: '0 20px 60px rgba(0,0,0,0.3)' }}>
            <h2 style={{ margin: '0 0 1.5rem', color: '#1a237e' }}>➕ Nouvel utilisateur</h2>
            {[
              { key: 'email', label: 'Email', placeholder: 'user@tt.tn', type: 'email' },
              { key: 'password', label: 'Mot de passe', placeholder: '••••••••', type: 'password' },
              { key: 'direction', label: 'Direction', placeholder: 'Assurance et Fraude' },
              { key: 'tel', label: 'Téléphone', placeholder: '+216 71 000 000' },
            ].map(f => (
              <div key={f.key} style={{ marginBottom: '1rem' }}>
                <label style={{ display: 'block', marginBottom: '0.4rem', fontWeight: 600, fontSize: '0.9rem', color: '#444' }}>{f.label}</label>
                <input type={f.type || 'text'} value={form[f.key]} onChange={e => setForm({ ...form, [f.key]: e.target.value })} placeholder={f.placeholder}
                  style={{ width: '100%', padding: '0.7rem 1rem', border: '2px solid #e0e0e0', borderRadius: '8px', fontSize: '0.95rem', boxSizing: 'border-box' }} />
              </div>
            ))}
            <div style={{ marginBottom: '1.5rem' }}>
              <label style={{ display: 'block', marginBottom: '0.4rem', fontWeight: 600, fontSize: '0.9rem', color: '#444' }}>Rôle</label>
              <select value={form.role} onChange={e => setForm({ ...form, role: e.target.value })}
                style={{ width: '100%', padding: '0.7rem 1rem', border: '2px solid #e0e0e0', borderRadius: '8px', fontSize: '0.95rem' }}>
                {ROLES.map(r => <option key={r}>{r}</option>)}
              </select>
            </div>
            <div style={{ display: 'flex', gap: '0.75rem', justifyContent: 'flex-end' }}>
              <button onClick={() => setShowForm(false)} style={{ padding: '0.7rem 1.5rem', border: '2px solid #e0e0e0', background: 'white', borderRadius: '8px', cursor: 'pointer', fontWeight: 600 }}>Annuler</button>
              <button onClick={save} style={{ padding: '0.7rem 1.5rem', background: 'linear-gradient(135deg, #1a237e, #0288d1)', color: 'white', border: 'none', borderRadius: '8px', cursor: 'pointer', fontWeight: 700 }}>Créer</button>
            </div>
          </div>
        </div>
      )}

      {/* Users cards */}
      {loading ? <p style={{ textAlign: 'center', color: '#888', padding: '3rem' }}>Chargement...</p> : (
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(320px, 1fr))', gap: '1rem' }}>
          {users.map(u => (
            <div key={u.id} style={{ background: 'white', borderRadius: '12px', padding: '1.5rem', boxShadow: '0 2px 12px rgba(0,0,0,0.08)', borderTop: `4px solid ${roleColor(u.role)}` }}>
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: '1rem' }}>
                <div style={{ width: '48px', height: '48px', borderRadius: '50%', background: roleBg(u.role), display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: '1.4rem' }}>
                  {u.role === 'ADMIN' ? '👑' : u.role === 'ANALYSTE_OP' ? '🔬' : '📊'}
                </div>
                <span style={{ background: u.actif ? '#e8f5e9' : '#ffebee', color: u.actif ? '#2e7d32' : '#c62828', padding: '0.25rem 0.75rem', borderRadius: '20px', fontSize: '0.8rem', fontWeight: 600 }}>
                  {u.actif ? 'Actif' : 'Inactif'}
                </span>
              </div>
              <p style={{ margin: '0 0 0.25rem', fontWeight: 700, color: '#1a237e', fontSize: '0.95rem' }}>{u.email}</p>
              <span style={{ background: roleBg(u.role), color: roleColor(u.role), padding: '0.2rem 0.6rem', borderRadius: '20px', fontSize: '0.78rem', fontWeight: 600 }}>{u.role.replace('_', ' ')}</span>
              <div style={{ marginTop: '1rem', fontSize: '0.82rem', color: '#888', lineHeight: '1.6' }}>
                {u.direction && <p style={{ margin: 0 }}>🏢 {u.direction}</p>}
                {u.tel && <p style={{ margin: 0 }}>📞 {u.tel}</p>}
                <p style={{ margin: 0 }}>📅 Créé le {new Date(u.created_at).toLocaleDateString('fr-FR')}</p>
              </div>
              {u.role !== 'ADMIN' && (
                <button onClick={() => toggleActive(u)} style={{
                  marginTop: '1rem', width: '100%', padding: '0.5rem',
                  background: u.actif ? '#ffebee' : '#e8f5e9',
                  color: u.actif ? '#c62828' : '#2e7d32',
                  border: `1px solid ${u.actif ? '#ef9a9a' : '#a5d6a7'}`,
                  borderRadius: '8px', cursor: 'pointer', fontWeight: 600, fontSize: '0.85rem',
                }}>
                  {u.actif ? '🚫 Désactiver' : '✅ Activer'}
                </button>
              )}
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
