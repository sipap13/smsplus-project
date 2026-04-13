/* eslint-disable react/prop-types */
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
    <div className="page">
      <div className="page-header">
        <div>
          <h1 className="page-title">Gestion des utilisateurs</h1>
          <p className="page-subtitle">{users.length} utilisateur(s) — réservé aux administrateurs</p>
        </div>
        <button
          type="button"
          onClick={() => {
            setForm(emptyUserForm());
            setShowForm(true);
          }}
          className="btn btn-primary"
        >
          Ajouter un utilisateur
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
        <p style={{ textAlign: 'center', color: 'var(--text-muted)', padding: '3rem' }}>Chargement...</p>
      ) : (
        <div className="panel table-wrap" style={{ overflow: 'auto' }}>
          <table className="table-mobile table-dense table-clean" style={{ width: '100%', borderCollapse: 'collapse' }}>
            <thead>
              <tr>
                {['ID', 'Nom', 'Email', 'Numéro personnel', 'Rôle', 'Actif', 'Dernière connexion', 'Créé le'].map((h) => (
                  <th
                    key={h}
                    style={{
                      padding: '0.85rem 1rem',
                      textAlign: 'left',
                      fontSize: '0.82rem',
                      color: 'var(--text-muted)',
                      fontWeight: 600,
                      borderBottom: '2px solid var(--border)',
                      whiteSpace: 'nowrap',
                    }}
                  >
                    {h}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {users.map((u) => (
                <tr key={u.id}>
                  <td data-label="ID" className="mono" style={{ padding: '0.75rem 1rem', color: 'var(--text-muted)' }}>
                    {fmtId(u.id)}
                  </td>
                  <td data-label="Nom" style={{ padding: '0.75rem 1rem', color: 'var(--text-main)', fontWeight: 600 }}>
                    {u.nom || '—'}
                  </td>
                  <td data-label="Email" style={{ padding: '0.75rem 1rem', color: 'var(--text-muted)' }}>
                    {u.email}
                  </td>
                  <td data-label="Numéro personnel" className="mono" style={{ padding: '0.75rem 1rem', color: 'var(--text-muted)' }}>
                    {u.numero_personnel || '—'}
                  </td>
                  <td data-label="Rôle" style={{ padding: '0.75rem 1rem' }}>
                    <select
                      value={u.role}
                      onChange={(e) => changeRole(u, e.target.value)}
                      disabled={busyId === u.id}
                      className="role-select"
                    >
                      {ROLES.map((r) => (
                        <option key={r} value={r}>
                          {fmtRole(r)}
                        </option>
                      ))}
                    </select>
                  </td>
                  <td data-label="Actif" style={{ padding: '0.75rem 1rem' }}>
                    <label className="check">
                      <input
                        type="checkbox"
                        checked={!!u.actif}
                        disabled={busyId === u.id || (u.id === myId && u.actif)}
                        onChange={() => toggleActive(u)}
                      />
                      Actif
                    </label>
                  </td>
                  <td data-label="Dernière connexion" style={{ padding: '0.75rem 1rem', color: 'var(--text-muted)', fontSize: '0.88rem' }}>
                    {fmtDT(u.last_login_at)}
                  </td>
                  <td data-label="Créé le" style={{ padding: '0.75rem 1rem', color: 'var(--text-muted)', fontSize: '0.88rem' }}>
                    {u.created_at ? new Date(u.created_at).toLocaleDateString('fr-FR') : '—'}
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
