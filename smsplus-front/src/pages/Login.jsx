/* eslint-disable react/prop-types */
import { useState } from 'react';
import api from '../api/axios';

export default function Login({ onLogin, bootError = '' }) {
  const [email, setEmail]       = useState('');
  const [password, setPassword] = useState('');
  const [error, setError]       = useState('');
  const [loading, setLoading]   = useState(false);

  const handleSubmit = async () => {
    if (!email || !password) { setError('Veuillez remplir tous les champs'); return; }
    setLoading(true); setError('');
    try {
      const res = await api.post('/login', { email, password });
      localStorage.setItem('token', res.data.token);
      localStorage.setItem('user', JSON.stringify(res.data.user));
      onLogin(res.data.user);
    } catch {
      setError('Email ou mot de passe incorrect');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="login-shell">
      <div className="login-card-compact">
        <img src="/tt-logo.png" alt="Tunisie Telecom" className="tt-logo login-compact" />
        <h1 className="login-title">Connexion SMS+</h1>

        {(bootError || error) && (
          <div style={{
            background: '#ffebee', color: '#c62828', border: '1px solid #ef9a9a',
            padding: '0.65rem 0.8rem', borderRadius: '8px',
            marginBottom: '0.9rem', fontSize: '0.85rem', textAlign: 'center',
          }}>
            {bootError || error}
          </div>
        )}

        <div style={{ marginBottom: '0.7rem' }}>
          <input
            type="email"
            value={email}
            onChange={e => setEmail(e.target.value)}
            onKeyDown={e => e.key === 'Enter' && handleSubmit()}
            placeholder="Nom d'utilisateur"
            style={{ width: '100%', padding: '0.72rem 0.85rem', fontSize: '0.92rem', boxSizing: 'border-box' }}
          />
        </div>

        <div style={{ marginBottom: '0.6rem' }}>
          <input
            type="password"
            value={password}
            onChange={e => setPassword(e.target.value)}
            onKeyDown={e => e.key === 'Enter' && handleSubmit()}
            placeholder="Mot de passe"
            style={{ width: '100%', padding: '0.72rem 0.85rem', fontSize: '0.92rem', boxSizing: 'border-box' }}
          />
        </div>

        <div style={{ textAlign: 'right', marginBottom: '0.8rem' }}>
          <button type="button" className="btn" style={{ padding: 0, background: 'transparent', color: 'var(--text-muted)', fontSize: '0.8rem' }}>
            Mot de passe oublié ?
          </button>
        </div>

        <button
          onClick={handleSubmit}
          disabled={loading}
          className="btn btn-primary"
          style={{ width: '100%', padding: '0.72rem 1rem', fontSize: '0.92rem', opacity: loading ? 0.75 : 1 }}
        >
          {loading ? 'Connexion...' : 'Se connecter'}
        </button>

        <div style={{ marginTop: '1rem', fontSize: '0.72rem', color: 'var(--text-muted)', textAlign: 'center', lineHeight: 1.5 }}>
          © 2024 Tunisie Telecom. Tous droits réservés. | Plateforme de supervision VAS et analyse fraude
        </div>
      </div>
    </div>
  );
}