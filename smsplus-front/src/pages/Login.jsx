/* eslint-disable react/prop-types */
import { useState, useEffect, useRef, useCallback } from 'react';
import { Link } from 'react-router-dom';
import api from '../api/axios';

export default function Login({ onLogin, bootError = '' }) {
  const [step, setStep] = useState('credentials');
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [pendingEmail, setPendingEmail] = useState('');
  const [maskedEmail, setMaskedEmail] = useState('');
  const [maskedPhone, setMaskedPhone] = useState('');
  const [twoFaCode, setTwoFaCode] = useState(['', '', '', '', '', '']);
  const [twoFaMethod, setTwoFaMethod] = useState('email');
  const [availableMethods, setAvailableMethods] = useState([]);
  const [expiresAt, setExpiresAt] = useState(null);
  const [timeLeft, setTimeLeft] = useState(0);
  const [resendCooldown, setResendCooldown] = useState(0);
  const [attemptsRemaining, setAttemptsRemaining] = useState(null);
  const inputRefs = useRef([]);

  useEffect(() => {
    if (!expiresAt || step !== 'two_fa') return;
    const interval = setInterval(() => {
      const left = Math.max(0, Math.ceil((expiresAt - Date.now()) / 1000));
      setTimeLeft(left);
      if (left <= 0) clearInterval(interval);
    }, 1000);
    return () => clearInterval(interval);
  }, [expiresAt, step]);

  useEffect(() => {
    if (resendCooldown <= 0) return;
    const interval = setInterval(() => {
      setResendCooldown((prev) => {
        if (prev <= 1) { clearInterval(interval); return 0; }
        return prev - 1;
      });
    }, 1000);
    return () => clearInterval(interval);
  }, [resendCooldown]);

  const formatTime = (seconds) => {
    const m = Math.floor(seconds / 60).toString().padStart(2, '0');
    const s = (seconds % 60).toString().padStart(2, '0');
    return `${m}:${s}`;
  };

  const resetTwoFa = useCallback(() => {
    setTwoFaCode(['', '', '', '', '', '']);
    setExpiresAt(null);
    setTimeLeft(0);
    setAttemptsRemaining(null);
    setError('');
    inputRefs.current[0]?.focus();
  }, []);

  const handleCredentialsSubmit = async () => {
    if (!email || !password) { setError('Veuillez remplir tous les champs'); return; }
    setLoading(true); setError('');
    try {
      const res = await api.post('/login', { email, password });
      const data = res.data;
      if (data.step === 'two_fa_required') {
        setPendingEmail(email);
        setMaskedEmail(data.email || '');
        setMaskedPhone(data.phone || '');
        setTwoFaMethod(data.method || 'email');
        setAvailableMethods(data.available_methods || []);
        setExpiresAt(Date.now() + (data.expires_in || 600) * 1000);
        setTimeLeft(data.expires_in || 600);
        setResendCooldown(60);
        setStep('two_fa');
        setTwoFaCode(['', '', '', '', '', '']);
        setTimeout(() => inputRefs.current[0]?.focus(), 300);
      } else if (data.token) {
        localStorage.setItem('token', data.token);
        localStorage.setItem('user', JSON.stringify(data.user));
        onLogin(data.user);
      }
    } catch (err) {
      setError(err.response?.data?.message || 'Email ou mot de passe incorrect');
    } finally { setLoading(false); }
  };

  const handleVerifyTwoFa = async () => {
    const code = twoFaCode.join('');
    if (code.length !== 6) { setError('Veuillez saisir les 6 chiffres du code'); return; }
    setLoading(true); setError('');
    try {
      const res = await api.post('/verify-2fa', { email: pendingEmail, code });
      const data = res.data;
      localStorage.setItem('token', data.token);
      localStorage.setItem('user', JSON.stringify(data.user));
      onLogin(data.user);
    } catch (err) {
      const status = err.response?.status;
      const data = err.response?.data || {};
      const msg = data.message || 'Erreur de vérification';
      if (data.expired) setError('Code expiré, demandez un nouveau code');
      else if (data.blocked) setError('Trop de tentatives, réessayez dans ' + (data.blocked_until_minutes || 15) + ' min');
      else if (status === 401 && data.attempts_remaining !== undefined) {
        setError('Code invalide, ' + data.attempts_remaining + ' tentatives restantes');
        setAttemptsRemaining(data.attempts_remaining);
      } else setError(msg);
      setTwoFaCode(['', '', '', '', '', '']);
      inputRefs.current[0]?.focus();
    } finally { setLoading(false); }
  };

  const handleResendCode = async () => {
    if (resendCooldown > 0) return;
    setLoading(true); setError('');
    try {
      const res = await api.post('/resend-2fa', { email: pendingEmail });
      const data = res.data;
      setExpiresAt(Date.now() + (data.expires_in || 600) * 1000);
      setTimeLeft(data.expires_in || 600);
      setResendCooldown(60);
      setTwoFaCode(['', '', '', '', '', '']);
      setAttemptsRemaining(null);
      setTwoFaMethod(data.method || 'email');
      inputRefs.current[0]?.focus();
    } catch (err) {
      setError(err.response?.data?.message || 'Erreur lors du renvoi du code');
    } finally { setLoading(false); }
  };

  const handleDigitChange = (index, value) => {
    const digit = value.replace(/\D/g, '').slice(-1);
    const next = [...twoFaCode];
    next[index] = digit;
    setTwoFaCode(next);
    setError('');
    if (digit && index < 5) inputRefs.current[index + 1]?.focus();
  };

  const handleDigitKeyDown = (index, e) => {
    if (e.key === 'Backspace' && !twoFaCode[index] && index > 0) inputRefs.current[index - 1]?.focus();
    else if (e.key === 'ArrowLeft' && index > 0) inputRefs.current[index - 1]?.focus();
    else if (e.key === 'ArrowRight' && index < 5) inputRefs.current[index + 1]?.focus();
    else if (e.key === 'Enter') handleVerifyTwoFa();
  };

  const handlePaste = (e) => {
    e.preventDefault();
    const text = (e.clipboardData.getData('text') || '').replace(/\D/g, '').slice(0, 6);
    if (text.length === 6) {
      setTwoFaCode(text.split(''));
      inputRefs.current[5]?.focus();
      setTimeout(() => handleVerifyTwoFa(), 150);
    } else if (text.length > 0) {
      const next = [...twoFaCode];
      let focusIndex = 0;
      for (let i = 0; i < text.length && i < 6; i++) {
        const emptyIdx = next.findIndex((d) => d === '');
        if (emptyIdx === -1) break;
        next[emptyIdx] = text[i];
        focusIndex = emptyIdx;
      }
      setTwoFaCode(next);
      inputRefs.current[Math.min(focusIndex + 1, 5)]?.focus();
    }
  };

  const switchMethod = (method) => { setTwoFaMethod(method); handleResendCode(); };
  const goBackToCredentials = () => {
    setStep('credentials'); setError(''); resetTwoFa();
    setPendingEmail(''); setMaskedEmail(''); setMaskedPhone('');
  };

  const Svg = ({ w, h, cls, children }) => (
    <svg width={w} height={h} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className={cls || ''}>
      {children}
    </svg>
  );
  const IconUser = () => <Svg w={20} h={20}><path d="M20 21V19C20 17.9391 19.5786 16.9217 18.8284 16.1716C18.0783 15.4214 17.0609 15 16 15H8C6.93913 15 5.92172 15.4214 5.17157 16.1716C4.42143 16.9217 4 17.9391 4 19V21M16 7C16 9.20914 14.2091 11 12 11C9.79086 11 8 9.20914 8 7C8 4.79086 9.79086 3 12 3C14.2091 3 16 4.79086 16 7Z"/></Svg>;
  const IconLock = () => <Svg w={20} h={20}><path d="M12 15V17M6 21H18C19.1046 21 20 20.1046 20 19V13C20 11.8954 19.1046 11 18 11H6C4.89543 11 4 11.8954 4 13V19C4 20.1046 4.89543 21 6 21ZM16 11V7C16 4.79086 14.2091 3 12 3C9.79086 3 8 4.79086 8 7V11"/></Svg>;
  const IconEye = () => <Svg w={20} h={20}><path d="M1 12C1 12 5 4 12 4C19 4 23 12 23 12C23 12 19 20 12 20C5 20 1 12 1 12Z"/><circle cx="12" cy="12" r="3"/></Svg>;
  const IconEyeOff = () => <Svg w={20} h={20}><path d="M17.94 17.94C16.2306 19.243 14.1491 19.9649 12 20C5 20 1 12 1 12C2.24389 9.68192 4.96914 6.58901 8.06 5.06003L17.94 17.94Z"/><path d="M9.9 4.24C10.5883 4.07888 11.2931 3.99834 12 4C19 4 23 12 23 12C22.393 13.1356 21.6691 14.2047 20.84 15.19L9.9 4.24Z"/><path d="M1 1L23 23"/></Svg>;
  const IconAlert = () => <Svg w={16} h={16}><path d="M12 9V11M12 15H12.01M21 12C21 16.9706 16.9706 21 12 21C7.02944 21 3 16.9706 3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12Z"/></Svg>;
  const IconSpinner = () => <Svg w={20} h={20} cls="login-spinner"><path d="M4 12C4 10.8954 4.89543 10 6 10C7.10457 10 8 10.8954 8 12C8 13.1046 7.10457 14 6 14"/><path d="M8 6C8 4.89543 8.89543 4 10 4C11.1046 4 12 4.89543 12 6C12 7.10457 11.1046 8 10 8"/><path d="M12 18C12 16.8954 12.8954 16 14 16C15.1046 16 16 16.8954 16 18C16 19.1046 15.1046 20 14 20"/><path d="M16 12C16 10.8954 16.8954 10 18 10C19.1046 10 20 10.8954 20 12C20 13.1046 19.1046 14 18 14"/></Svg>;
  const IconCheck = () => <Svg w={18} h={18}><path d="M20 6L9 17L4 12" strokeWidth="2.5"/></Svg>;
  const IconShield = () => <Svg w={48} h={48}><path d="M12 22C12 22 20 18 20 12V5L12 2L4 5V12C4 18 12 22 12 22Z"/><path d="M9 12L11 14L15 10"/></Svg>;
  const IconMail = () => <Svg w={16} h={16}><path d="M4 4H20C21.1 4 22 4.9 22 6V18C22 19.1 21.1 20 20 20H4C2.9 20 2 19.1 2 18V6C2 4.9 2.9 4 4 4Z"/><path d="M22 6L12 13L2 6"/></Svg>;
  const IconPhone = () => <Svg w={16} h={16}><path d="M22 16.92V19.92C22.0016 20.483 21.8126 21.029 21.467 21.469C21.1214 21.909 20.6414 22.217 20.1 22.34C19.2546 22.5312 18.3939 22.6543 17.527 22.708C11.977 23.053 6.70365 20.321 3.414 15.586C2.22897 13.8044 1.36225 11.8207 0.855 9.725C0.354 7.651 0.096 5.538 0.085 3.418C0.084 3.056 0.251 2.711 0.546 2.479C0.841 2.247 1.225 2.152 1.592 2.224C3.225 2.538 4.779 3.135 6.193 3.992C6.53 4.197 6.777 4.534 6.88 4.925C6.983 5.316 6.933 5.731 6.74 6.084C6.215 7.061 5.82 8.11 5.568 9.2C5.445 9.76 5.617 10.346 6.026 10.75L7.561 12.286C8.651 13.376 10.179 13.964 11.755 13.9C13.331 13.836 14.802 13.125 15.8 11.95C16.1 11.6 16.526 11.38 16.99 11.34C17.454 11.3 17.913 11.445 18.27 11.74C19.471 12.736 20.525 13.897 21.405 15.19C21.68 15.586 21.784 16.075 21.695 16.548C21.606 17.021 21.331 17.439 20.93 17.71L22 16.92Z"/></Svg>;
  const IconArrowLeft = () => <Svg w={16} h={16}><path d="M19 12H5M12 19L5 12L12 5"/></Svg>;

  const isCodeComplete = twoFaCode.every((d) => d !== '');
  const progressPercent = expiresAt ? Math.max(0, Math.min(100, (timeLeft / 600) * 100)) : 0;
  const isExpired = timeLeft <= 0;

  return (
    <div className="login-shell-new">
      {/* Left Panel - Visual */}
      <div className="login-left">
        <div className="login-left-logo">
          <img src="/tt-logo.png" alt="Tunisie Telecom" />
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
      <div className="login-right">
        <div className="login-right-inner">
          <Link to="/" className="login-back"><IconArrowLeft />Retour à l&apos;accueil</Link>
          <div className="login-logo-mobile"><img src="/tt-logo.png" alt="Tunisie Telecom" /><span>SMS+ VAS</span></div>

          {/* Credentials Step */}
          <div style={{ display: step === 'credentials' ? 'block' : 'none' }}>
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
                <div className="login-input-wrap">
                  <IconLock />
                  <input type={showPassword ? 'text' : 'password'} value={password} onChange={(e) => setPassword(e.target.value)} placeholder="Votre mot de passe" className="login-input-new" required disabled={loading} />
                  <button type="button" className="toggle-pwd" onClick={() => setShowPassword(!showPassword)} disabled={loading}>
                    {showPassword ? <IconEyeOff /> : <IconEye />}
                  </button>
                </div>
              </div>
              <div className="login-forgot"><Link to="/forgot-password">Mot de passe oublié ?</Link></div>
              <button type="submit" disabled={loading} className="login-submit">
                {loading ? <><IconSpinner /><span>Connexion...</span></> : 'Se connecter'}
              </button>
            </form>
          </div>

          {/* 2FA Step */}
          <div style={{ display: step === 'two_fa' ? 'block' : 'none' }}>
            <div className="twofa-header">
              <div className="twofa-shield"><IconShield /></div>
              <h2 className="twofa-title">Vérification en deux étapes</h2>
              <p className="twofa-subtitle">Code envoyé à <strong>{twoFaMethod === 'sms' && maskedPhone ? maskedPhone : maskedEmail}</strong></p>
            </div>
            {error && (
              <div className="login-error-inline" style={{ marginBottom: 16, justifyContent: 'center' }}>
                <IconAlert /><span>{error}</span>
              </div>
            )}
            {availableMethods.length > 1 && (
              <div className="twofa-method-switch">
                {availableMethods.map((method) => (
                  <button key={method} type="button" className={'twofa-method-btn ' + (twoFaMethod === method ? 'twofa-method-active' : '')} onClick={() => switchMethod(method)} disabled={loading || resendCooldown > 0}>
                    {method === 'email' ? <IconMail /> : <IconPhone />}
                    {method === 'email' ? 'Email' : `+216 ${maskedPhone || ''}`}
                  </button>
                ))}
              </div>
            )}
            <div className="twofa-inputs-row" onPaste={handlePaste}>
              {twoFaCode.map((digit, i) => (
                <input key={i} ref={(el) => { inputRefs.current[i] = el; }} type="text" inputMode="numeric" maxLength={1} value={digit} onChange={(e) => handleDigitChange(i, e.target.value)} onKeyDown={(e) => handleDigitKeyDown(i, e)} className={'twofa-digit ' + (isExpired ? 'twofa-digit-expired' : '')} disabled={loading || isExpired} aria-label={'Chiffre ' + (i + 1)} />
              ))}
            </div>
            <div className="twofa-timer">
              <div className="twofa-timer-bar">
                <div className="twofa-timer-fill" style={{ width: `${progressPercent}%`, background: timeLeft < 120 ? '#dc2626' : '#1d4ed8' }} />
              </div>
              <span className={'twofa-timer-text ' + (timeLeft < 120 ? 'twofa-timer-urgent' : '')}>
                {isExpired ? 'Code expiré' : `Code expire dans ${formatTime(timeLeft)}`}
              </span>
            </div>
            <button type="button" disabled={!isCodeComplete || loading || isExpired} className="login-submit twofa-verify-btn" onClick={handleVerifyTwoFa}>
              {loading ? <><IconSpinner /><span>Vérification...</span></> : 'Vérifier'}
            </button>
            <div className="twofa-secondary-actions">
              <button type="button" className="twofa-resend-btn" onClick={handleResendCode} disabled={resendCooldown > 0 || loading}>
                {resendCooldown > 0 ? `Renvoyer dans ${resendCooldown}s` : 'Renvoyer le code'}
              </button>
              <button type="button" className="twofa-back-btn" onClick={goBackToCredentials} disabled={loading}>
                <IconArrowLeft />Changer de compte
              </button>
            </div>
          </div>

          <div className="login-footer-new">&copy; 2026 Tunisie Telecom</div>
        </div>
      </div>
    </div>
  );
}

