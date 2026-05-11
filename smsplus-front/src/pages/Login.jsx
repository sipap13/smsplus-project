/* eslint-disable react/prop-types */
import { useState } from 'react';
import { Link } from 'react-router-dom';
import api from '../api/axios';

export default function Login({ onLogin, bootError = '' }) {
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);

  const handleCredentialsSubmit = async () => {
    if (!email || !password) {
      setError('Veuillez remplir tous les champs');
      return;
    }
    setLoading(true);
    setError('');
    try {
      const res = await api.post('/login', { email, password });
      const data = res.data;
      if (data.token) {
        localStorage.setItem('token', data.token);
        localStorage.setItem('user', JSON.stringify(data.user));
        onLogin(data.user);
      } else {
        setError('Une erreur est survenue lors de la connexion.');
      }
    } catch (err) {
      setError(err.response?.data?.message || 'Email ou mot de passe incorrect');
    } finally {
      setLoading(false);
    }
  };

  const Svg = ({ w, h, cls, children }) => (
    <svg width={w} height={h} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className={cls || ''}>
      {children}
    </svg>
  );
  const IconUser = () => <Svg w={20} h={20}><path d="M20 21V19C20 17.9391 19.5786 16.9217 18.8284 16.1716C18.0783 15.4214 17.0609 15 16 15H8C6.93913 15 5.92172 15.4214 5.17157 16.1716C4.42143 16.9217 4 17.9391 4 19V21M16 7C16 9.20914 14.2091 11 12 11C9.79086 11 8 9.20914 8 7C8 4.79086 9.79086 3 12 3C14.2091 3 16 4.79086 16 7Z" /></Svg>;
  const IconLock = () => <Svg w={20} h={20}><path d="M12 15V17M6 21H18C19.1046 21 20 20.1046 20 19V13C20 11.8954 19.1046 11 18 11H6C4.89543 11 4 11.8954 4 13V19C4 20.1046 4.89543 21 6 21ZM16 11V7C16 4.79086 14.2091 3 12 3C9.79086 3 8 4.79086 8 7V11" /></Svg>;
  const IconEye = () => <Svg w={20} h={20}><path d="M1 12C1 12 5 4 12 4C19 4 23 12 23 12C23 12 19 20 12 20C5 20 1 12 1 12Z" /><circle cx="12" cy="12" r="3" /></Svg>;
  const IconEyeOff = () => <Svg w={20} h={20}><path d="M17.94 17.94C16.2306 19.243 14.1491 19.9649 12 20C5 20 1 12 1 12C2.24389 9.68192 4.96914 6.58901 8.06 5.06003L17.94 17.94Z" /><path d="M9.9 4.24C10.5883 4.07888 11.2931 3.99834 12 4C19 4 23 12 23 12C22.393 13.1356 21.6691 14.2047 20.84 15.19L9.9 4.24Z" /><path d="M1 1L23 23" /></Svg>;
  const IconAlert = () => <Svg w={16} h={16}><path d="M12 9V11M12 15H12.01M21 12C21 16.9706 16.9706 21 12 21C7.02944 21 3 16.9706 3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12Z" /></Svg>;
  const IconSpinner = () => <Svg w={20} h={20} cls="login-spinner"><path d="M4 12C4 10.8954 4.89543 10 6 10C7.10457 10 8 10.8954 8 12C8 13.1046 7.10457 14 6 14" /><path d="M8 6C8 4.89543 8.89543 4 10 4C11.1046 4 12 4.89543 12 6C12 7.10457 11.1046 8 10 8" /><path d="M12 18C12 16.8954 12.8954 16 14 16C15.1046 16 16 16.8954 16 18C16 19.1046 15.1046 20 14 20" /><path d="M16 12C16 10.8954 16.8954 10 18 10C19.1046 10 20 10.8954 20 12C20 13.1046 19.1046 14 18 14" /></Svg>;
  const IconCheck = () => <Svg w={18} h={18}><path d="M20 6L9 17L4 12" strokeWidth="2.5" /></Svg>;
  const IconArrowLeft = () => <Svg w={16} h={16}><path d="M19 12H5M12 19L5 12L12 5" /></Svg>;

  return (
    <div className="login-shell-new" style={{
      display: 'flex',
      position: 'fixed',
      top: 0,
      left: 0,
      width: '100%',
      height: '100%',
      zIndex: 9999,
      margin: 0,
      padding: 0,
      overflow: 'hidden',
      background: 'linear-gradient(to right, #0f172a 51%, #ffffff 51%)'
    }}>
      {/* Left Panel - Visual */}
      <div className="login-left" style={{ flex: '0 0 51%', minHeight: '100vh', overflow: 'hidden', background: '#0f172a', position: 'relative', display: 'flex', flexDirection: 'column', justifyContent: 'space-between', padding: '48px' }}>
        <div style={{
          display: 'flex',
          alignItems: 'center',
          gap: '12px',
          marginBottom: '48px',
        }}>
          <div style={{
            width: '48px',
            height: '48px',
            backgroundColor: 'white',
            borderRadius: '50%',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            overflow: 'hidden',
            boxShadow: '0 4px 12px rgba(0,0,0,0.15)',
            flexShrink: 0,
          }}>
            <img
              src="/tt-logo.png"
              alt="Tunisie Telecom"
              style={{
                width: '80%',
                height: '80%',
                objectFit: 'contain',
              }}
            />
          </div>
          <div>
            <div style={{
              color: 'white',
              fontWeight: 700,
              fontSize: '15px',
              lineHeight: '1.2',
              letterSpacing: '0.2px',
            }}>
              Tunisie Telecom
            </div>
            <div style={{
              color: 'rgba(255,255,255,0.5)',
              fontSize: '11px',
              fontWeight: 400,
              letterSpacing: '0.5px',
              textTransform: 'uppercase',
            }}>
              SMS+ VAS
            </div>
          </div>
        </div>
        <div className="login-left-content">
          <h2 className="login-left-title">Supervision SMS+ VAS</h2>
          <p className="login-left-subtitle">Contrôle revenue &amp; détection fraude en temps réel pour Tunisie Telecom</p>
          <div className="login-left-features">
            <div className="login-left-feature"><IconCheck /><span>Analyse CDR OCC/MMG en temps réel</span></div>
            <div className="login-left-feature"><IconCheck /><span>Détection automatique des anomalies</span></div>
            <div className="login-left-feature"><IconCheck /><span>Prédictions revenus par IA</span></div>
          </div>
        </div>
        <div className="login-left-stats">
          <div className="login-stat"><span className="login-stat-value">3M+</span><span className="login-stat-label">CDR analysés</span></div>
          <div className="login-stat"><span className="login-stat-value">7 486 DT</span><span className="login-stat-label">Revenus suivis</span></div>
          <div className="login-stat"><span className="login-stat-value">24/7</span><span className="login-stat-label">Monitoring</span></div>
        </div>
      </div>

      {/* Right Panel - Form */}
      <div className="login-right" style={{ flex: '1', minHeight: '100vh', overflowY: 'auto', display: 'flex', alignItems: 'center', justifyContent: 'center', background: '#ffffff' }}>
        <div className="login-right-inner">
          <Link to="/" className="login-back"><IconArrowLeft />Retour à l&apos;accueil</Link>

          <div>
            <h1 className="login-right-title">Connexion</h1>
            <p className="login-right-subtitle">Direction Assurance &amp; Fraude</p>
            <div className="login-divider" />
            {(bootError || error) && (
              <div className="login-error-inline" style={{ marginBottom: 16 }}>
                <IconAlert /><span>{bootError || error}</span>
              </div>
            )}
            <form className="login-form-new" onSubmit={(e) => { e.preventDefault(); handleCredentialsSubmit(); }}>
              <div className="login-field-new">
                <label>Email</label>
                <div className="login-input-wrap">
                  <IconUser />
                  <input type="email" value={email} onChange={(e) => setEmail(e.target.value)} placeholder="votre.email@tt.tn" className="login-input-new" required disabled={loading} />
                </div>
              </div>
              <div className="login-field-new">
                <label>Mot de passe</label>
                <div style={{ position: 'relative', width: '100%' }}>
                  <div style={{
                    position: 'absolute',
                    left: '14px',
                    top: '50%',
                    transform: 'translateY(-50%)',
                    color: '#94a3b8',
                    display: 'flex',
                    alignItems: 'center',
                    pointerEvents: 'none',
                    zIndex: 1,
                  }}>
                    <IconLock />
                  </div>
                  <input
                    type={showPassword ? 'text' : 'password'}
                    value={password}
                    onChange={(e) => setPassword(e.target.value)}
                    placeholder="Votre mot de passe"
                    className="login-input-new"
                    required
                    disabled={loading}
                    style={{
                      width: '100%',
                      height: '44px',
                      padding: '0 44px 0 40px',
                      border: '1.5px solid #e2e8f0',
                      borderRadius: '8px',
                      fontSize: '15px',
                      boxSizing: 'border-box',
                      outline: 'none',
                      lineHeight: '44px',
                    }}
                  />
                  <button
                    type="button"
                    className="toggle-pwd"
                    onClick={() => setShowPassword(!showPassword)}
                    disabled={loading}
                    style={{
                      position: 'absolute',
                      right: '12px',
                      top: '50%',
                      transform: 'translateY(-50%)',
                      background: 'none',
                      border: 'none',
                      cursor: 'pointer',
                      padding: '4px',
                      display: 'flex',
                      alignItems: 'center',
                      justifyContent: 'center',
                      color: '#94a3b8',
                      borderRadius: '4px',
                      zIndex: 1,
                    }}
                    aria-label={showPassword ? 'Masquer le mot de passe' : 'Afficher le mot de passe'}
                  >
                    {showPassword ? <IconEyeOff /> : <IconEye />}
                  </button>
                </div>
              </div>
              <button type="submit" disabled={loading} className="login-submit">
                {loading ? <><IconSpinner /><span>Connexion...</span></> : 'Se connecter'}
              </button>
            </form>
          </div>

          <div className="login-footer-new">&copy; 2026 Tunisie Telecom</div>
        </div>
      </div>
    </div>
  );
}

