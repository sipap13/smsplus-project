/* eslint-disable react/prop-types */
import { useEffect, useState } from 'react';
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

function IconLock() {
  const c = { width: 20, height: 20, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', strokeWidth: 1.5, strokeLinecap: 'round', strokeLinejoin: 'round' };
  return <svg {...c}><rect x="3" y="11" width="18" height="11" rx="2" ry="2" /><path d="M7 11V7a5 5 0 0 1 10 0v4" /></svg>;
}

function IconUsers() {
  const c = { width: 20, height: 20, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', strokeWidth: 1.5, strokeLinecap: 'round', strokeLinejoin: 'round' };
  return <svg {...c}><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" /><circle cx="9" cy="7" r="4" /><path d="M23 21v-2a4 4 0 0 0-3-3.87" /><path d="M16 3.13a4 4 0 0 1 0 7.75" /></svg>;
}

export default function Landing({ bootError = '' }) {
  const [theme, setTheme] = useState(() => {
    try {
      return localStorage.getItem('theme') || 'light';
    } catch {
      return 'light';
    }
  });

  useEffect(() => {
    applyTheme(theme);
  }, [theme]);

  const toggleTheme = () => setTheme((t) => (t === 'dark' ? 'light' : 'dark'));

  return (
    <div className="landing">
      <header className="landing-top">
        <div className="landing-brand">
          <img src="/tt-logo-sidebar-clean.png" alt="" className="landing-logo" />
          <div>
            <div className="landing-product">SMS+ VAS</div>
            <div className="landing-sub">Revenue Assurance &amp; Fraud Detection</div>
          </div>
        </div>
        <nav className="landing-nav">
          <a href="#features" className="landing-nav-link">Fonctionnalités</a>
          <a href="#roles" className="landing-nav-link">Rôles</a>
          <a href="#access" className="landing-nav-link">Accès</a>
        </nav>
        <div className="landing-actions">
          <button type="button" className="landing-icon-btn" onClick={toggleTheme} title={theme === 'dark' ? 'Mode clair' : 'Mode sombre'} aria-label="Thème">
            {theme === 'dark' ? <IconSun /> : <IconMoon />}
          </button>
          <Link to="/login" className="btn btn-primary landing-cta">Connexion</Link>
        </div>
      </header>

      {bootError ? (
        <div className="landing-flash" role="alert">
          {bootError}
          <Link to="/login" className="landing-flash-link">Se reconnecter</Link>
        </div>
      ) : null}

      <main className="landing-main">
        <section className="landing-hero surface">
          <p className="landing-kicker mono">Console opérationnelle</p>
          <h1 className="landing-title">
            SMS+ VAS : supervision du trafic, revenus et détection fraude en un seul tableau de bord.
          </h1>
          <p className="landing-lead">
            Tableaux denses et rapides, recherche MSISDN côte à côte, alertes d'anomalies, et vision consolidée OCC/MMG pour les équipes Assurance &amp; Fraude de Tunisie Telecom.
          </p>
          <div className="landing-hero-actions">
            <Link to="/login" className="btn btn-primary">Accéder à l'application</Link>
            <a href="#features" className="btn btn-ghost">Voir les modules</a>
          </div>
        </section>

        <section id="features" className="landing-section">
          <h2 className="landing-section-title">Modules clés</h2>
          <p className="landing-section-subtitle">Tout ce dont l'équipe Assurance Revenu a besoin sur une plateforme sécurisée.</p>
          <div className="landing-grid">
            {[
              { 
                icon: IconPieChart, 
                t: 'Tableau de bord', 
                d: 'KPI journaliers, revenus agrégés, comparaison MMG vs OCC et tendances sur 7-30 jours.',
                tag: 'Lecture seule'
              },
              { 
                icon: IconTable, 
                t: 'CDR & Journaux', 
                d: 'Journaux OCC/MMG paginés, appels/SMS/VAS détaillés, filtres rapides par service/date.',
                tag: 'Recherche'
              },
              { 
                icon: IconAlerts, 
                t: 'Alertes Fraude', 
                d: "Suivi des anomalies (+20% trafic vs moyenne 7J), seuils paramétrables, statuts ouverts/résolus.",
                tag: 'Seuils paramétrables'
              },
              { 
                icon: IconUsers, 
                t: 'MSISDN & Réclamations', 
                d: 'Recherche MSISDN avec résultats OCC/MMG côte à côte, historique transactions, SOS.',
                tag: 'Side-by-side'
              },
              { 
                icon: IconLock, 
                t: 'Administration', 
                d: 'Gestion services VAS, utilisateurs/rôles (ADMIN/OP/BUSS), audit et permissions.',
                tag: 'RBAC'
              },
              { 
                icon: IconLock, 
                t: 'Sécurité', 
                d: 'Authentification jeton, sessions courtes, logs d'accès, chiffrement données en transit.',
                tag: 'Enterprise'
              },
            ].map((item) => (
              <div key={item.t} className="landing-card surface">
                <div className="landing-card-icon">
                  <item.icon />
                </div>
                <div className="landing-card-tag mono">{item.tag}</div>
                <h3 className="landing-card-title">{item.t}</h3>
                <p className="landing-card-desc">{item.d}</p>
              </div>
            ))}
          </div>
        </section>

        <section className="landing-section">
          <h2 className="landing-section-title">Caractéristiques</h2>
          <p className="landing-section-subtitle">Conçu pour la vitesse, la densité et la précision analytique.</p>
          <div className="landing-features-grid">
            {[
              { 
                title: 'Tableaux denses', 
                desc: 'Colonnes compactes + pagination pour visualiser max de données sans scroll.' 
              },
              { 
                title: 'Recherche rapide', 
                desc: 'Cmd+K pour naviguer, filtres live sur tables, MSISDN côte à côte OCC/MMG.' 
              },
              { 
                title: 'Alertes en temps réel', 
                desc: 'Détection anomalies seuil, statuts ouverts/résolus, paramètres ajustables.' 
              },
              { 
                title: 'Split views', 
                desc: 'Voir OCC et MMG simultanément, comparer revenus/trafic côte à côte.' 
              },
              { 
                title: 'Dark mode', 
                desc: 'Thème clair &amp; sombre, transition smooth, parfait pour surveillance 24/7.' 
              },
              { 
                title: 'Export &amp; rapports', 
                desc: 'Télécharge CDR en CSV, synthèse revenus par service, logs audit.' 
              },
            ].map((feat) => (
              <div key={feat.title} className="landing-feature-item">
                <div className="landing-feature-icon">✓</div>
                <div className="landing-feature-content">
                  <h4 className="landing-feature-title">{feat.title}</h4>
                  <p className="landing-feature-desc">{feat.desc}</p>
                </div>
              </div>
            ))}
          </div>
        </section>

        <section id="roles" className="landing-section">
          <h2 className="landing-section-title">Pour qui ?</h2>
          <p className="landing-section-subtitle">Trois profils habilités, permissions granulaires.</p>
          <div className="landing-roles-grid">
            {[
              {
                badge: 'ADMIN',
                title: 'Administrateurs',
                desc: 'Gestion services, utilisateurs, rôles, accès complet à tous les modules.'
              },
              {
                badge: 'OP',
                title: 'Analystes Opérationnels',
                desc: 'Supervision trafic, CDR OCC/MMG, alertes, recherche MSISDN.'
              },
              {
                badge: 'BUSS',
                title: 'Analystes Business',
                desc: 'Revenus, KPI, tableaux de bord, alertes anomalies revenus.'
              },
            ].map((role) => (
              <div key={role.badge} className="landing-role-card surface">
                <div className="landing-role-badge">{role.badge}</div>
                <h3 className="landing-role-title">{role.title}</h3>
                <p className="landing-role-desc">{role.desc}</p>
              </div>
            ))}
          </div>
        </section>

        <section className="landing-section">
          <h2 className="landing-section-title">Dépôt et données</h2>
          <p className="landing-section-subtitle">Synchronisé avec Postgres, mises à jour quotidiennes MMG/OCC.</p>
          <div className="landing-stats">
            {[
              { v: '60+', l: 'jours de données' },
              { v: '450K+', l: 'enregistrements CDR' },
              { v: '3', l: 'sources (OCC/MMG/SOS)' },
              { v: '24/7', l: 'opérationnel' },
            ].map((stat) => (
              <div key={stat.l} className="landing-stat-item">
                <div className="landing-stat-value">{stat.v}</div>
                <p className="landing-stat-label">{stat.l}</p>
              </div>
            ))}
          </div>
        </section>

        <section id="access" className="landing-foot surface">
          <div className="landing-foot-content">
            <h2 className="landing-foot-title">Prêt à débuter ?</h2>
            <p className="landing-foot-desc">
              Accès sécurisé via authentification jeton. Réservé aux profils habilités Tunisie Telecom RA &amp; Fraude.
            </p>
          </div>
          <Link to="/login" className="btn btn-primary">Se connecter</Link>
        </section>
      </main>

      <footer className="landing-footer mono">
        <span>SMS+ VAS — console métier · Tunisie Telecom {new Date().getFullYear()}</span>
        <div className="landing-footer-links">
          <a href="mailto:support@tt.tn" className="landing-footer-link">Support</a>
          <a href="#" className="landing-footer-link">Docs</a>
          <a href="#" className="landing-footer-link">Statut</a>
        </div>
      </footer>
    </div>
  );
}
