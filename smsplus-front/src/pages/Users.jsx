 
import { useEffect, useState } from 'react';
import api from '../api/axios';
import Modal from '../components/Modal';

const ROLES = ['ADMIN', 'ANALYSTE_OP', 'ANALYSTE_BUSS'];

const emptyUserForm = () => ({
  nom: '',
  email: '',
  password: '',
  numero_personnel: '',
  direction: 'Assurance et Fraude',
  role: 'ANALYSTE_OP',
  tel: '',
});

export default function Users({ user: currentUser }) {
  const [users, setUsers] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [showForm, setShowForm] = useState(false);
  const [form, setForm] = useState(emptyUserForm);
  const [msg, setMsg] = useState('');
  const [busyId, setBusyId] = useState(null);

  const myId = currentUser?.id;

  const load = () => {
    setLoading(true);
    setError('');
    api
      .get('/users')
      .then((r) => {
        setUsers(r.data);
        setLoading(false);
      })
      .catch(() => {
        setError("Impossible de charger les utilisateurs. Vérifie l'API.");
        setUsers([]);
        setLoading(false);
      });
  };

  useEffect(() => {
    load();
  }, []);

  const saveNew = async () => {
    if (!form.email?.trim() || !form.password) {
      setMsg('Email et mot de passe requis');
      setTimeout(() => setMsg(''), 3000);
      return;
    }
    try {
      await api.post('/users', form);
      setMsg('Utilisateur créé avec succès');
      setShowForm(false);
      setForm(emptyUserForm());
      load();
    } catch {
      setMsg('Erreur lors de la création (email déjà utilisé ?)');
    }
    setTimeout(() => setMsg(''), 4000);
  };

  const toggleActive = async (u) => {
    if (u.id === myId && u.actif) {
      setMsg('Tu ne peux pas désactiver ton propre compte');
      setTimeout(() => setMsg(''), 3500);
      return;
    }
    setBusyId(u.id);
    try {
      await api.put(`/users/${u.id}`, { actif: !u.actif });
      setUsers((prev) => prev.map((row) => (row.id === u.id ? { ...row, actif: !row.actif } : row)));
      setMsg('Statut mis à jour');
    } catch {
      setMsg('Erreur lors du changement de statut');
    } finally {
      setBusyId(null);
    }
    setTimeout(() => setMsg(''), 3000);
  };

  const changeRole = async (u, newRole) => {
    if (newRole === u.role) return;
    setBusyId(u.id);
    try {
      await api.put(`/users/${u.id}`, { role: newRole });
      setUsers((prev) => prev.map((row) => (row.id === u.id ? { ...row, role: newRole } : row)));
      setMsg('Rôle mis à jour');
    } catch {
      setMsg('Impossible de modifier le rôle');
    } finally {
      setBusyId(null);
    }
    setTimeout(() => setMsg(''), 3000);
  };

  const bannerOk = !/erreur|impossible|requis/i.test(msg);

  const fmtId = (id) => `USR-${String(id).padStart(3, '0')}`;
  const fmtRole = (r) => ({ ADMIN: 'Admin', ANALYSTE_OP: 'Analyste OP', ANALYSTE_BUSS: 'Analyste BUSS' }[r] || r);
  const fmtDT = (v) => {
    if (!v) return '—';
    const d = new Date(v);
    if (Number.isNaN(d.getTime())) return '—';
    return d.toLocaleString('fr-FR', { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' }).replace(',', '');
  };

  return (
    <div className="page" style={{ padding: '2rem', maxWidth: '1200px', margin: '0 auto' }}>

      {/* Header */}
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: '1.5rem', flexWrap: 'wrap', gap: '1rem' }}>
        <div>
          <h1 style={{ fontSize: '1.75rem', fontWeight: 800, color: 'var(--text-main)', margin: '0 0 0.25rem' }}>Gestion des Utilisateurs</h1>
          <div style={{ display: 'flex', gap: '0.75rem', marginTop: '0.5rem' }}>
            <span style={{ fontSize: '0.8rem', fontWeight: 600, padding: '3px 12px', borderRadius: '99px', background: 'rgba(30, 58, 95, 0.12)', color: '#1e3a5f' }}>{users.length} utilisateurs</span>
            <span style={{ fontSize: '0.8rem', fontWeight: 600, padding: '3px 12px', borderRadius: '99px', background: 'rgba(16,185,129,0.12)', color: '#10b981' }}>{users.filter(u => u.actif).length} actifs</span>
            <span style={{ fontSize: '0.8rem', fontWeight: 600, padding: '3px 12px', borderRadius: '99px', background: 'rgba(239,68,68,0.12)', color: '#ef4444' }}>{users.filter(u => u.role === 'ADMIN').length} admins</span>
          </div>
        </div>
        <button type="button" onClick={() => { setForm(emptyUserForm()); setShowForm(true); }} className="btn btn-primary" style={{ height: '40px', fontWeight: 600 }}>
          + Ajouter un utilisateur
        </button>
      </div>

      {msg && (
        <div
          style={{
            padding: '0.75rem 1rem',
            borderRadius: '8px',
            marginBottom: '1rem',
            background: bannerOk ? 'rgba(46, 125, 50, 0.12)' : 'rgba(198, 40, 40, 0.12)',
            color: 'var(--text-main)',
            border: `1px solid ${bannerOk ? 'rgba(46, 125, 50, 0.35)' : 'rgba(198, 40, 40, 0.35)'}`,
          }}
        >
          {msg}
        </div>
      )}
      {error && (
        <div
          style={{
            padding: '0.75rem 1rem',
            borderRadius: '8px',
            marginBottom: '1rem',
            background: 'rgba(198, 40, 40, 0.1)',
            color: 'var(--text-main)',
            border: '1px solid rgba(198, 40, 40, 0.35)',
          }}
        >
          {error}
        </div>
      )}

      {showForm && (
        <Modal title="Nouvel utilisateur" onClose={() => setShowForm(false)} wide>
          {[
            { key: 'nom', label: 'Nom', placeholder: 'ex: Ahmed Ben Ali' },
            { key: 'email', label: 'Email', placeholder: 'user@tt.tn', type: 'email' },
            { key: 'password', label: 'Mot de passe', placeholder: '••••••••', type: 'password' },
            { key: 'numero_personnel', label: 'Numéro personnel', placeholder: 'ex: USR-001' },
            { key: 'direction', label: 'Direction', placeholder: 'Assurance et Fraude' },
            { key: 'tel', label: 'Téléphone', placeholder: '+216 71 000 000' },
          ].map((f) => (
            <div key={f.key} style={{ marginBottom: '1rem' }}>
              <label style={{ display: 'block', marginBottom: '0.4rem', fontWeight: 600, fontSize: '0.9rem', color: 'var(--text-main)' }}>
                {f.label}
              </label>
              <input
                type={f.type || 'text'}
                value={form[f.key]}
                onChange={(e) => setForm({ ...form, [f.key]: e.target.value })}
                placeholder={f.placeholder}
                style={{ width: '100%', padding: '0.7rem 1rem', fontSize: '0.95rem', boxSizing: 'border-box' }}
              />
            </div>
          ))}
          <div style={{ marginBottom: '1.5rem' }}>
            <label style={{ display: 'block', marginBottom: '0.4rem', fontWeight: 600, fontSize: '0.9rem', color: 'var(--text-main)' }}>
              Rôle
            </label>
            <select
              value={form.role}
              onChange={(e) => setForm({ ...form, role: e.target.value })}
              style={{ width: '100%', padding: '0.7rem 1rem', fontSize: '0.95rem' }}
            >
              {ROLES.map((r) => (
                <option key={r} value={r}>
                  {r.replace('_', ' ')}
                </option>
              ))}
            </select>
          </div>
          <div style={{ display: 'flex', gap: '0.75rem', justifyContent: 'flex-end' }}>
            <button type="button" onClick={() => setShowForm(false)} className="btn btn-ghost">
              Annuler
            </button>
            <button type="button" onClick={saveNew} className="btn btn-primary">
              Créer
            </button>
          </div>
        </Modal>
      )}

      {loading ? (
        <div style={{ display: 'flex', justifyContent: 'center', padding: '4rem' }}><div className="spinner" /></div>
      ) : (
        <div style={{ background: 'var(--bg-elevated)', border: '1px solid var(--border)', borderRadius: '14px', overflow: 'hidden' }}>
          <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: '0.85rem' }}>
            <thead>
              <tr style={{ background: 'var(--bg-surface)' }}>
                {['', 'Nom', 'Email', 'N° Personnel', 'Rôle', 'Statut', 'Dernière connexion', 'Créé le'].map(h => (
                  <th key={h} style={{ padding: '0.7rem 1rem', textAlign: 'left', fontSize: '0.75rem', color: 'var(--text-muted)', fontWeight: 600, borderBottom: '1px solid var(--border)', whiteSpace: 'nowrap', textTransform: 'uppercase', letterSpacing: '0.04em' }}>{h}</th>
                ))}
              </tr>
            </thead>
            <tbody>
              {users.map(u => {
                const roleColors = { ADMIN: '#ef4444', ANALYSTE_OP: '#3b6fa0', ANALYSTE_BUSS: '#2a5082' };
                const rColor = roleColors[u.role] || '#64748b';
                const initials = (u.nom || u.email || '?').slice(0, 2).toUpperCase();
                const avatarColors = ['#0f2744', '#1e3a5f', '#2a5082', '#3b6fa0', '#4a8ec2', '#5ba3d9'];
                const aColor = avatarColors[(u.id || 0) % avatarColors.length];
                return (
                  <tr key={u.id} style={{ borderBottom: '1px solid var(--border)', transition: 'background 0.1s' }}
                    onMouseEnter={e => e.currentTarget.style.background = 'var(--bg-surface)'}
                    onMouseLeave={e => e.currentTarget.style.background = 'transparent'}
                  >
                    <td style={{ padding: '0.7rem 1rem' }}>
                      <div style={{ width: 34, height: 34, borderRadius: '50%', background: aColor, color: '#fff', display: 'flex', alignItems: 'center', justifyContent: 'center', fontWeight: 700, fontSize: '0.8rem' }}>{initials}</div>
                    </td>
                    <td style={{ padding: '0.7rem 1rem', fontWeight: 600, color: 'var(--text-main)' }}>{u.nom || '—'}</td>
                    <td style={{ padding: '0.7rem 1rem', color: 'var(--text-muted)', fontSize: '0.82rem' }}>{u.email}</td>
                    <td style={{ padding: '0.7rem 1rem', fontFamily: 'monospace', fontSize: '0.78rem', color: 'var(--text-muted)' }}>{u.numero_personnel || '—'}</td>
                    <td style={{ padding: '0.7rem 1rem' }}>
                      <select value={u.role} onChange={e => changeRole(u, e.target.value)} disabled={busyId === u.id}
                        style={{ border: `1px solid ${rColor}40`, borderRadius: '8px', padding: '4px 8px', fontSize: '0.78rem', fontWeight: 700, color: rColor, background: `${rColor}10`, cursor: 'pointer' }}>
                        {ROLES.map(r => <option key={r} value={r}>{fmtRole(r)}</option>)}
                      </select>
                    </td>
                    <td style={{ padding: '0.7rem 1rem' }}>
                      <button onClick={() => toggleActive(u)} disabled={busyId === u.id || (u.id === myId && u.actif)}
                        style={{ padding: '3px 12px', borderRadius: '99px', fontSize: '0.72rem', fontWeight: 700, cursor: 'pointer', border: 'none',
                          background: u.actif ? 'rgba(16,185,129,0.15)' : 'rgba(239,68,68,0.1)',
                          color: u.actif ? '#10b981' : '#ef4444'
                        }}>
                        {u.actif ? 'Actif' : 'Inactif'}
                      </button>
                    </td>
                    <td style={{ padding: '0.7rem 1rem', color: 'var(--text-muted)', fontSize: '0.78rem', fontFamily: 'monospace' }}>{fmtDT(u.last_login_at)}</td>
                    <td style={{ padding: '0.7rem 1rem', color: 'var(--text-muted)', fontSize: '0.78rem' }}>{u.created_at ? new Date(u.created_at).toLocaleDateString('fr-FR') : '—'}</td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}
