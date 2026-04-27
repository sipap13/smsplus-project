/* eslint-disable react/prop-types */
import { useEffect, useState, useRef } from 'react';
import { Link } from 'react-router-dom';
import { applyTheme } from '../theme';

function IconMoon() {
  const c = { width: 18, height: 18, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', strokeWidth: 1.9, strokeLinecap: 'round', strokeLinejoin: 'round' };
  return <svg {...c}><path d="M21 12.8A8.5 8.5 0 1 1 11.2 3a6.5 6.5 0 0 0 9.8 9.8z" /></svg>;
}

function IconSun() {
  const c = { width: 18, height: 18, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', strokeWidth: 1.9, strokeLinecap: 'round', strokeLinejoin: 'round' };
  return (
    <svg {...c}>
      <circle cx="12" cy="12" r="4" />
      <path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4" />
    </svg>
  );
}

function IconPieChart() {
  const c = { width: 20, height: 20, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', strokeWidth: 1.5, strokeLinecap: 'round', strokeLinejoin: 'round' };
  return <svg {...c}><path d="M12 2v10M12 12h10a10 10 0 1 1-10-10z" /></svg>;
}

function IconAlerts() {
  const c = { width: 20, height: 20, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', strokeWidth: 1.5, strokeLinecap: 'round', strokeLinejoin: 'round' };
  return <svg {...c}><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3.04h16.94a2 2 0 0 0 1.71-3.04l-8.47-14.14a2 2 0 0 0-3.42 0z" /><line x1="12" y1="9" x2="12" y2="13" /><line x1="12" y1="17" x2="12.01" y2="17" /></svg>;
}

function IconTable() {
  const c = { width: 20, height: 20, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', strokeWidth: 1.5, strokeLinecap: 'round', strokeLinejoin: 'round' };
  return <svg {...c}><path d="M3 9h18v12a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V9z" /><path d="M3 9V7a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v2M3 15h18M3 21h18" /></svg>;
}

function IconSearch() {
  const c = { width: 20, height: 20, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', strokeWidth: 1.5, strokeLinecap: 'round', strokeLinejoin: 'round' };
  return <svg {...c}><circle cx="11" cy="11" r="8" /><path d="M21 21l-4.35-4.35" /></svg>;
}

function IconMenu() {
  const c = { width: 20, height: 20, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', strokeWidth: 2, strokeLinecap: 'round', strokeLinejoin: 'round' };
  return <svg {...c}><line x1="3" y1="12" x2="21" y2="12" /><line x1="3" y1="6" x2="21" y2="6" /><line x1="3" y1="18" x2="21" y2="18" /></svg>;
}

function IconChart() {
  const c = { width: 20, height: 20, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', strokeWidth: 1.5 };
  return <svg {...c}><path d="M3 3v18h18" /><path d="M18 17V9" /><path d="M13 17V5" /><path d="M8 17v-3" /></svg>;
}

function IconZap() {
  const c = { width: 20, height: 20, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', strokeWidth: 1.5 };
  return <svg {...c}><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2" /></svg>;
}

function IconActivity() {
  const c = { width: 20, height: 20, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', strokeWidth: 1.5 };
  return <svg {...c}><polyline points="22 12 18 12 15 21 9 3 6 12 2 12" /></svg>;
}

function IconServer() {
  const c = { width: 20, height: 20, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', strokeWidth: 1.5 };
  return <svg {...c}><rect x="2" y="2" width="20" height="8" rx="2" ry="2" /><rect x="2" y="14" width="20" height="8" rx="2" ry="2" /><line x1="6" y1="6" x2="6.01" y2="6" /><line x1="6" y1="18" x2="6.01" y2="18" /></svg>;
}

function IconShieldRole() {
  const c = { width: 20, height: 20, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', strokeWidth: 1.5 };
  return <svg {...c}><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" /></svg>;
}

const MODULES = [
  {
    icon: IconPieChart,
    title: 'Tableau de bord',
    desc: 'KPI journaliers, revenus agrégés, comparaison MMG vs OCC et tendances sur 7-30 jours.',
    tag: 'Lecture seule',
    color: '#3b82f6',
    bg: '#eff6ff'
  },
  {
    icon: IconTable,
    title: 'CDR & Journaux',
    desc: 'Journaux OCC/MMG paginés, appels/SMS/VAS détaillés, filtres rapides par service/date.',
    tag: 'Recherche',
    color: '#8b5cf6',
    bg: '#f5f3ff'
  },
  {
    icon: IconAlerts,
    title: 'Alertes Fraude',
    desc: 'Suivi des anomalies (+20% trafic vs moyenne 7J), seuils paramétrables, statuts ouverts/résolus.',
    tag: 'Seuils paramétrables',
    color: '#f59e0b',
    bg: '#fffbeb'
  },
  {
    icon: IconSearch,
    title: 'MSISDN & Réclamations',
    desc: 'Recherche MSISDN avec résultats OCC/MMG côte à côte, historique transactions, SOS.',
    tag: 'Side-by-side',
    color: '#10b981',
    bg: '#ecfdf5'
  },
];

const FEATURES = [
  { icon: IconTable, title: 'Tableaux denses', desc: 'Colonnes compactes + pagination pour visualiser max de données sans scroll.' },
  { icon: IconSearch, title: 'Recherche rapide', desc: 'Cmd+K pour naviguer, filtres live sur tables, MSISDN côte à côte OCC/MMG.' },
  { icon: IconAlerts, title: 'Alertes en temps réel', desc: 'Détection anomalies seuil, statuts ouverts/résolus, paramètres ajustables.' },
  { icon: IconChart, title: 'Split views', desc: 'Voir OCC et MMG simultanément, comparer revenus/trafic côte à côte.' },
  { icon: IconMoon, title: 'Dark mode', desc: 'Thème clair & sombre, transition smooth, parfait pour surveillance 24/7.' },
  { icon: IconZap, title: 'Export & rapports', desc: 'Télécharge CDR en CSV, synthèse revenus par service, logs audit.' },
];

const ROLES = [
  {
    badge: 'ADMIN',
    title: 'Administrateurs',
    desc: 'Gestion services, utilisateurs, rôles, accès complet à tous les modules.',
    color: '#dc2626',
    bg: '#fef2f2',
    border: '#fecaca',
    list: ['Gestion complète des utilisateurs', 'Configuration des seuils', 'Audit et logs système']
  },
  {
    badge: 'OP',
    title: 'Analystes Opérationnels',
    desc: 'Supervision trafic, CDR OCC/MMG, alertes, recherche MSISDN.',
    color: '#d97706',
    bg: '#fffbeb',
    border: '#fde68a',
    list: ['Supervision temps réel', 'Gestion des alertes', 'Recherche approfondie']
  },
  {
    badge: 'BUSS',
    title: 'Analystes Business',
    desc: 'Revenus, KPI, tableaux de bord, alertes anomalies revenus.',
    color: '#16a34a',
    bg: '#f0fdf4',
    border: '#bbf7d0',
    list: ['Analyse des revenus', 'Rapports business', 'Prédictions et tendances']
  },
];

const STATS = [
  { icon: IconActivity, value: '3M+', label: 'CDR analysés' },
  { icon: IconChart, value: '7 486', label: 'DT revenus suivis' },
  { icon: IconServer, value: '24/7', label: 'Monitoring actif' },
  { icon: IconZap, value: '< 2s', label: 'Temps de réponse' },
];

export default function Landing({ bootError = '' }) {
  const [theme, setTheme] = useState(() => {
    try { return localStorage.getItem('theme') || 'light'; } catch { return 'light'; }
  });
  const [isScrolled, setIsScrolled] = useState(false);
  const [menuOpen, setMenuOpen] = useState(false);
  const statsRef = useRef(null);
  const [statsVisible, setStatsVisible] = useState(false);

  useEffect(() => { applyTheme(theme); }, [theme]);

  useEffect(() => {
    const handleScroll = () => setIsScrolled(window.scrollY > 10);
    window.addEventListener('scroll', handleScroll);
    return () => window.removeEventListener('scroll', handleScroll);
  }, []);

  useEffect(() => {
    const observer = new IntersectionObserver(
      ([entry]) => { if (entry.isIntersecting) setStatsVisible(true); },
      { threshold: 0.3 }
    );
    if (statsRef.current) observer.observe(statsRef.current);
    return () => observer.disconnect();
  }, []);

  const toggleTheme = () => setTheme((t) => (t === 'dark' ? 'light' : 'dark'));

  return (
    <div className="landing">
      {/* Navbar */}
      <header className={`landing-top ${isScrolled ? 'scrolled' : ''}`}>
        <div className="landing-brand">
          <img src="/tt-logo-sidebar-clean.png" alt="" className="landing-logo" style={{ width: 36, height: 36 }} />
          <div>
            <div className="landing-product" style={{ fontSize: '0.95rem', fontWeight: 700 }}>SMS+ VAS</div>
            <div className="landing-sub" style={{ fontSize: '0.7rem', color: 'var(--text-muted)' }}>by Tunisie Telecom</div>
          </div>
        </div>
        <nav className="landing-nav" style={{ gap: '1.5rem' }}>
          <a href="#modules" className="landing-nav-link" style={{ fontSize: '0.9375rem', color: '#374151' }}>Modules</a>
          <a href="#roles" className="landing-nav-link" style={{ fontSize: '0.9375rem', color: '#374151' }}>Rôles</a>
          <a href="#stats" className="landing-nav-link" style={{ fontSize: '0.9375rem', color: '#374151' }}>Statistiques</a>
          <a href="#access" className="landing-nav-link" style={{ fontSize: '0.9375rem', color: '#374151' }}>Accès</a>
        </nav>
        <div className="landing-actions" style={{ gap: '0.75rem' }}>
          <button type="button" className="landing-icon-btn" onClick={toggleTheme} title={theme === 'dark' ? 'Mode clair' : 'Mode sombre'}>
            {theme === 'dark' ? <IconSun /> : <IconMoon />}
          </button>
          <Link to="/login" className="landing-cta-outline">Connexion</Link>
          <button type="button" className="landing-menu-btn" onClick={() => setMenuOpen(!menuOpen)}>
            <IconMenu />
          </button>
        </div>
      </header>

      {menuOpen && (
        <div className="landing-mobile-drawer">
          <a href="#modules" className="landing-mobile-link" onClick={() => setMenuOpen(false)}>Modules</a>
          <a href="#roles" className="landing-mobile-link" onClick={() => setMenuOpen(false)}>Rôles</a>
          <a href="#stats" className="landing-mobile-link" onClick={() => setMenuOpen(false)}>Statistiques</a>
          <a href="#access" className="landing-mobile-link" onClick={() => setMenuOpen(false)}>Accès</a>
          <Link to="/login" className="landing-mobile-cta" onClick={() => setMenuOpen(false)}>Connexion</Link>
        </div>
      )}

      {bootError ? (
        <div className="landing-flash" role="alert">
          {bootError}
          <Link to="/login" className="landing-flash-link">Se reconnecter</Link>
        </div>
      ) : null}

      <main className="landing-main">
        {/* Hero */}
        <section className="landing-hero">
          <div className="landing-hero-content">
            <div className="landing-live-badge">
              <span className="landing-live-dot" />
              LIVE · Monitoring en temps réel
            </div>
            <h1 className="landing-title" style={{ fontSize: 'clamp(2rem, 5vw, 3.25rem)', fontWeight: 800, lineHeight: 1.15, letterSpacing: '-0.02em' }}>
              Supervision réseau et détection fraude avec <span style={{ color: 'var(--color-primary)' }}>SMS+ VAS</span>
            </h1>
            <p className="landing-lead" style={{ fontSize: '1.125rem', color: 'var(--text-muted)', lineHeight: 1.7, maxWidth: 560 }}>
              Plateforme enterprise de revenue assurance et détection de fraude pour Tunisie Telecom. Analyse temps réel des CDRs OCC/MMG.
            </p>
            <div className="landing-hero-actions" style={{ gap: '0.75rem' }}>
              <Link to="/login" className="landing-btn-primary">Accéder à l&apos;application</Link>
              <a href="#modules" className="landing-btn-secondary">Voir les modules</a>
            </div>
          </div>
        </section>

        {/* Modules */}
        <section id="modules" className="landing-section">
          <div style={{ textAlign: 'center', marginBottom: '0.5rem' }}>
            <h2 className="landing-section-title">Modules clés</h2>
            <p className="landing-section-subtitle">Tout ce dont l&apos;équipe Assurance Revenu a besoin.</p>
          </div>
          <div className="landing-grid">
            {MODULES.map((item) => (
              <div key={item.title} className="landing-card" style={{ borderTopColor: item.color }}>
                <div className="landing-card-icon" style={{ background: item.bg, color: item.color }}>
                  <item.icon />
                </div>
                <div className="landing-card-tag">{item.tag}</div>
                <h3 className="landing-card-title">{item.title}</h3>
                <p className="landing-card-desc">{item.desc}</p>
              </div>
            ))}
          </div>
        </section>

        {/* Features */}
        <section className="landing-section">
          <div style={{ textAlign: 'center', marginBottom: '0.5rem' }}>
            <h2 className="landing-section-title">Caractéristiques</h2>
            <p className="landing-section-subtitle">Conçu pour la vitesse, la densité et la précision analytique.</p>
          </div>
          <div className="landing-features-grid">
            {FEATURES.map((feat) => (
              <div key={feat.title} className="landing-feature-item">
                <div className="landing-feature-icon">
                  <feat.icon />
                </div>
                <div className="landing-feature-content">
                  <h4 className="landing-feature-title">{feat.title}</h4>
                  <p className="landing-feature-desc">{feat.desc}</p>
                </div>
              </div>
            ))}
          </div>
        </section>

        {/* Roles */}
        <section id="roles" className="landing-section">
          <div style={{ textAlign: 'center', marginBottom: '0.5rem' }}>
            <h2 className="landing-section-title">Pour qui ?</h2>
            <p className="landing-section-subtitle">Trois profils habilités, permissions granulaires.</p>
          </div>
          <div className="landing-roles-grid">
            {ROLES.map((role) => (
              <div key={role.badge} className="landing-role-card" style={{ borderLeftColor: role.color }}>
                <div className="landing-role-badge" style={{ background: role.bg, color: role.color, borderColor: role.border }}>{role.badge}</div>
                <h3 className="landing-role-title">{role.title}</h3>
                <p className="landing-role-desc">{role.desc}</p>
                <ul className="landing-role-list">
                  {role.list.map((li) => (
                    <li key={li}>{li}</li>
                  ))}
                </ul>
              </div>
            ))}
          </div>
        </section>

        {/* Stats */}
        <section id="stats" ref={statsRef} className="landing-stats-section">
          <div className="landing-stats-grid">
            {STATS.map((stat, i) => (
              <div key={stat.label} className="landing-stat-item">
                <stat.icon />
                <div className="landing-stat-value">{statsVisible ? stat.value : '0'}</div>
                <p className="landing-stat-label">{stat.label}</p>
              </div>
            ))}
          </div>
        </section>

        {/* CTA */}
        <section id="access" className="landing-cta-section">
          <div className="landing-cta-content">
            <h2 className="landing-cta-title">Prêt à débuter ?</h2>
            <p className="landing-cta-desc">Accès sécurisé via authentification jeton. Réservé aux profils habilités Tunisie Telecom RA &amp; Fraude.</p>
          </div>
          <Link to="/login" className="landing-btn-primary">Se connecter</Link>
        </section>
      </main>

      {/* Footer */}
      <footer className="landing-footer-new">
        <div className="landing-footer-brand">
          <img src="/tt-logo-sidebar-clean.png" alt="" className="landing-footer-logo" />
          <span>SMS+ VAS</span>
        </div>
        <div className="landing-footer-links">
          <a href="mailto:support@tt.tn" className="landing-footer-link">Support</a>
          <a href="#" className="landing-footer-link">Docs</a>
          <a href="#" className="landing-footer-link">Statut</a>
        </div>
        <div className="landing-footer-copy">&copy; {new Date().getFullYear()} Tunisie Telecom</div>
      </footer>
    </div>
  );
}

