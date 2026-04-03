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
    <div style={{
      minHeight: '100vh', width: '100vw',
      display: 'flex', alignItems: 'center', justifyContent: 'center',
      background: 'linear-gradient(135deg, #0d1b6e 0%, #1565c0 50%, #0288d1 100%)',
      margin: 0, padding: 0, boxSizing: 'border-box',
    }}>
      <div style={{
        background: 'white', borderRadius: '20px',
        padding: '3rem 2.5rem', width: '100%', maxWidth: '420px',
        boxShadow: '0 20px 60px rgba(0,0,0,0.3)',
        margin: '0 1rem',
      }}>
        {/* Logo */}
        <div style={{ textAlign: 'center', marginBottom: '2rem' }}>
          <div style={{
            width: '70px', height: '70px', borderRadius: '16px',
            background: 'linear-gradient(135deg, #1a237e, #0288d1)',
            display: 'flex', alignItems: 'center', justifyContent: 'center',
            margin: '0 auto 1rem', fontSize: '2rem',
          }}>
            📡
          </div>
          <h1 style={{ color: '#1a237e', margin: 0, fontSize: '1.8rem', fontWeight: 800 }}>SMS+</h1>
          <p style={{ color: '#888', margin: '0.4rem 0 0', fontSize: '0.9rem' }}>
            Tunisie Telecom — Plateforme de supervision
          </p>
        </div>

        {/* Erreur */}
        {(bootError || error) && (
          <div style={{
            background: '#ffebee', color: '#c62828', border: '1px solid #ef9a9a',
            padding: '0.75rem 1rem', borderRadius: '8px',
            marginBottom: '1rem', fontSize: '0.9rem', textAlign: 'center',
          }}>
            ⚠️ {bootError || error}
          </div>
        )}

        {/* Email */}
        <div style={{ marginBottom: '1.2rem' }}>
          <label style={{ display: 'block', marginBottom: '0.5rem', color: '#333', fontWeight: 600, fontSize: '0.9rem' }}>
            Email
          </label>
          <input
            type="email"
            value={email}
            onChange={e => setEmail(e.target.value)}
            onKeyDown={e => e.key === 'Enter' && handleSubmit()}
            placeholder="admin@tt.tn"
            style={{
              width: '100%', padding: '0.85rem 1rem',
              border: '2px solid #e0e0e0', borderRadius: '10px',
              fontSize: '1rem', boxSizing: 'border-box',
              outline: 'none', transition: 'border 0.2s',
            }}
            onFocus={e => e.target.style.borderColor = '#1a237e'}
            onBlur={e => e.target.style.borderColor = '#e0e0e0'}
          />
        </div>

        {/* Mot de passe */}
        <div style={{ marginBottom: '2rem' }}>
          <label style={{ display: 'block', marginBottom: '0.5rem', color: '#333', fontWeight: 600, fontSize: '0.9rem' }}>
            Mot de passe
          </label>
          <input
            type="password"
            value={password}
            onChange={e => setPassword(e.target.value)}
            onKeyDown={e => e.key === 'Enter' && handleSubmit()}
            placeholder="••••••••"
            style={{
              width: '100%', padding: '0.85rem 1rem',
              border: '2px solid #e0e0e0', borderRadius: '10px',
              fontSize: '1rem', boxSizing: 'border-box',
              outline: 'none',
            }}
            onFocus={e => e.target.style.borderColor = '#1a237e'}
            onBlur={e => e.target.style.borderColor = '#e0e0e0'}
          />
        </div>

        {/* Bouton */}
        <button
          onClick={handleSubmit}
          disabled={loading}
          style={{
            width: '100%', padding: '1rem',
            background: loading ? '#90caf9' : 'linear-gradient(135deg, #1a237e, #0288d1)',
            color: 'white', border: 'none', borderRadius: '10px',
            fontSize: '1rem', cursor: loading ? 'not-allowed' : 'pointer',
            fontWeight: 700, letterSpacing: '0.5px',
            boxShadow: '0 4px 15px rgba(26,35,126,0.3)',
          }}
        >
          {loading ? '⏳ Connexion...' : '🔐 Se connecter'}
        </button>

        {/* Comptes test */}
        <div style={{
          marginTop: '1.5rem', padding: '1rem',
          background: '#f8f9ff', borderRadius: '10px',
          border: '1px solid #e8eaf6', fontSize: '0.82rem', color: '#555',
        }}>
          <strong style={{ color: '#1a237e' }}>🧪 Comptes de test :</strong><br /><br />
          <span style={{ display: 'block', marginBottom: '0.3rem' }}>👤 admin@tt.tn / <strong>admin123</strong></span>
          <span style={{ display: 'block', marginBottom: '0.3rem' }}>👤 analyste.op@tt.tn / <strong>op123</strong></span>
          <span style={{ display: 'block' }}>👤 analyste.buss@tt.tn / <strong>buss123</strong></span>
        </div>
      </div>
    </div>
  );
}