/* eslint-disable react/prop-types */
import { useEffect, useMemo, useRef, useState, useCallback } from 'react';
import { useNavigate } from 'react-router-dom';
import api from '../api/axios';
import {
  ComposedChart, Bar, Line, Area, XAxis, YAxis, Tooltip,
  ResponsiveContainer, CartesianGrid, Legend, ReferenceLine,
  RadialBarChart, RadialBar,
} from 'recharts';
import { formatDT, formatCompactNumber } from '../lib/format';
import useServiceMapping from '../hooks/useServiceMapping';
import JobStatusBar from '../components/JobStatusBar';

const C_HISTO = '#3b82f6';
const C_PRED = '#f59e0b';
const C_CONF = 'rgba(245, 158, 11, 0.25)';
const C_MOY = '#94a3b8';
const JOURS = ['Dimanche','Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi'];
const JOURS_SHORT = ['Dim','Lun','Mar','Mer','Jeu','Ven','Sam','Dim'];

function useFadeIn() {
  const ref = useRef(null);
  const [visible, setVisible] = useState(false);
  useEffect(() => {
    const el = ref.current;
    if (!el) return;
    const obs = new IntersectionObserver(([entry]) => {
      if (entry.isIntersecting) { setVisible(true); obs.disconnect(); }
    });
    obs.observe(el);
    return () => obs.disconnect();
  }, []);
  return [ref, visible];
}

function FadeSection({ children }) {
  const [ref, visible] = useFadeIn();
  return (
    <div ref={ref} style={{ opacity: visible ? 1 : 0, transform: visible ? 'translateY(0)' : 'translateY(15px)', transition: '0.5s' }}>
      {children}
    </div>
  );
}

function SkeletonCard() {
  return (
    <div className="kpi-card" style={{ padding: '1.25rem' }}>
      <div className="skeleton" style={{ height: 14, width: '60%', marginBottom: 12 }} />
      <div className="skeleton" style={{ height: 32, width: '45%', marginBottom: 8 }} />
      <div className="skeleton" style={{ height: 14, width: '70%' }} />
    </div>
  );
}

function SkeletonChart() {
  return (
    <div className="surface surface-pad" style={{ height: 340 }}>
      <div className="skeleton" style={{ height: 20, width: '30%', marginBottom: 16 }} />
      <div className="skeleton" style={{ height: 260, width: '100%' }} />
    </div>
  );
}

function SkeletonTable() {
  return (
    <div className="panel table-wrap">
      <div style={{ padding: '1rem 1.5rem' }}>
        <div className="skeleton" style={{ height: 18, width: '25%' }} />
      </div>
      {Array.from({ length: 5 }).map((_, i) => (
        <div key={i} style={{ padding: '0.75rem 1.5rem', display: 'flex', gap: '1rem' }}>
          <div className="skeleton" style={{ height: 16, width: '18%' }} />
          <div className="skeleton" style={{ height: 16, width: '14%' }} />
          <div className="skeleton" style={{ height: 16, width: '16%' }} />
          <div className="skeleton" style={{ height: 16, width: '12%' }} />
          <div className="skeleton" style={{ height: 16, width: '20%' }} />
        </div>
      ))}
    </div>
  );
}

function ReliabilityBadge({ score }) {
  let color = '#dc2626'; let label = 'Faible';
  if (score >= 75) { color = '#16a34a'; label = 'Fiable'; }
  else if (score >= 50) { color = '#f59e0b'; label = 'Moyenne'; }
  return (
    <span className="badge" style={{ background: color + '15', borderColor: color + '50', color, fontWeight: 700, fontSize: '0.85rem' }}>
      <span className="status-dot" style={{ background: color }} />
      Fiabilite {score}% &mdash; {label}
    </span>
  );
}

function ConfidenceBar({ pct }) {
  let color = '#dc2626';
  if (pct >= 75) color = '#16a34a';
  else if (pct >= 50) color = '#f59e0b';
  return (
    <div style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
      <div style={{ flex: 1, height: 6, background: 'var(--border)', borderRadius: 3, overflow: 'hidden' }}>
        <div style={{ width: pct + '%', height: '100%', background: color, borderRadius: 3, transition: 'width 0.5s' }} />
      </div>
      <span style={{ fontSize: '0.78rem', fontWeight: 700, color, minWidth: 28, textAlign: 'right' }}>{pct}%</span>
    </div>
  );
}

function TrendIcon({ trend, variation }) {
  if (trend === 'hausse') return <span style={{ color: '#16a34a', fontWeight: 700 }}>↑ +{Math.abs(variation || 0).toFixed(1)}%</span>;
  if (trend === 'baisse') return <span style={{ color: '#dc2626', fontWeight: 700 }}>↓ -{Math.abs(variation || 0).toFixed(1)}%</span>;
  return <span style={{ color: '#94a3b8', fontWeight: 700 }}>→ {Math.abs(variation || 0).toFixed(1)}%</span>;
}

function VolatilityBadge({ vol }) {
  let label = 'Faible', cls = 'badge-ok';
  if (vol > 30) { label = 'Elevee'; cls = 'badge-danger'; }
  else if (vol > 15) { label = 'Moderee'; cls = 'badge-warn'; }
  return <span className={'badge ' + cls}>{label}</span>;
}

function ProviderBadge({ provider, model, fromCache, cachedAt }) {
  const [now, setNow] = useState(new Date());

  useEffect(() => {
    const timer = setInterval(() => setNow(new Date()), 60000);
    return () => clearInterval(timer);
  }, []);

  const getMinutesAgo = (date) => {
    if (!date) return 0;
    const diff = now - new Date(date);
    return Math.max(0, Math.round(diff / 60000));
  };

  const badgeStyle = {
    borderRadius: '999px',
    padding: '3px 12px',
    fontSize: '12px',
    fontWeight: 600,
    display: 'inline-flex',
    alignItems: 'center',
    gap: '6px'
  };

  let content = null;

  if (provider === 'groq') {
    content = (
      <span style={{ ...badgeStyle, background: 'var(--primary-soft)', color: 'var(--primary)', border: '1px solid var(--border)' }}>
        ⚡ Groq AI · {model}
      </span>
    );
  } else if (provider === 'gemini') {
    content = (
      <span style={{ ...badgeStyle, background: 'rgba(22,163,74,0.1)', color: 'var(--success)', border: '1px solid rgba(22,163,74,0.2)' }}>
        ✦ Gemini Flash
      </span>
    );
  } else if (provider === 'php_fallback') {
    content = (
      <span style={{ ...badgeStyle, background: 'rgba(217,119,6,0.1)', color: 'var(--warning)', border: '1px solid rgba(217,119,6,0.2)' }}>
        ⚠ Calcul statistique
      </span>
    );
  }

  return (
    <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
      {content}
      {fromCache && (
        <span style={{ color: '#94a3b8', fontSize: '11px' }}>
          · Depuis le cache · Actualisé il y a {getMinutesAgo(cachedAt)}min
        </span>
      )}
    </div>
  );
}

function Accordion({ title, icon, children, defaultOpen = false }) {
  const [open, setOpen] = useState(defaultOpen);
  return (
    <div className="surface" style={{ marginBottom: '0.75rem', overflow: 'hidden' }}>
      <button onClick={() => setOpen(!open)} style={{ width: '100%', textAlign: 'left', padding: '0.9rem 1.1rem', background: 'transparent', border: 'none', cursor: 'pointer', display: 'flex', alignItems: 'center', gap: '0.6rem', fontWeight: 700, fontSize: '0.95rem', color: 'var(--text-main)' }}>
        <span style={{ fontSize: '1.1rem' }}>{icon}</span>
        {title}
        <span style={{ marginLeft: 'auto', color: 'var(--text-muted)', fontSize: '0.8rem' }}>{open ? '▲' : '▼'}</span>
      </button>
      {open && (
        <div style={{ padding: '0 1.1rem 1rem', color: 'var(--text-main)', fontSize: '0.88rem', lineHeight: 1.6 }}>
          {children}
        </div>
      )}
    </div>
  );
}

function CustomTooltip({ active, payload, label }) {
  if (!active || !payload || !payload.length) return null;
  const p = payload.find(x => x.dataKey === 'prediction');
  const h = payload.find(x => x.dataKey === 'historique');
  const min = payload.find(x => x.dataKey === 'confMin');
  const max = payload.find(x => x.dataKey === 'confMax');
  const isPred = !!p;
  const dateObj = new Date(label);
  const jourIdx = dateObj.getDay();
  const jourNom = JOURS[jourIdx];
  return (
    <div style={{ background: 'var(--bg-elevated)', border: '1px solid var(--border)', borderRadius: 10, padding: '0.7rem 0.9rem', fontSize: '0.82rem', color: 'var(--text-main)', boxShadow: '0 4px 12px rgba(0,0,0,0.1)', maxWidth: 260 }}>
      <div style={{ fontWeight: 700, marginBottom: 4, color: 'var(--text-heading)' }}>
        {isPred ? '🔮' : '📅'} {label} {isPred && `· ${jourNom}`}
      </div>
      {h && h.value != null && (<div>Revenus reels : <strong>{formatDT(h.value)}</strong></div>)}
      {p && p.value != null && (
        <>
          <div>Predit : <strong>{formatDT(p.value)}</strong></div>
          {min && max && (<div style={{ color: 'var(--text-muted)', fontSize: '0.78rem' }}>Fourchette : {formatDT(min.value)} – {formatDT(max.value)}</div>)}
        </>
      )}
    </div>
  );
}

export default function Predictions() {
  const navigate = useNavigate();
  const [data, setData] = useState(null);
  const [services, setServices] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [lastUpdated, setLastUpdated] = useState(null);
  const [horizon, setHorizon] = useState(7);
  const [keywordFilter, setKeywordFilter] = useState('');
  const [source, setSource] = useState('groq');
  const [aiProvider, setAiProvider] = useState('groq');
  const [aiModel, setAiModel] = useState(null);
  const [providerInfo, setProviderInfo] = useState(null);

  const { services: mappedServices, getNom } = useServiceMapping();

  const load = useCallback(async (isManualRefresh = false) => {
    setLoading(true); setError('');
    try {
      if (isManualRefresh) {
        await api.delete(`/predictions/cache?horizon=${horizon}${keywordFilter ? '&keyword=' + encodeURIComponent(keywordFilter) : ''}`);
      }

      const [predRes, svcRes] = await Promise.allSettled([
        api.get('/predictions/revenus?horizon=' + horizon + (keywordFilter ? '&keyword=' + encodeURIComponent(keywordFilter) : '')),
        api.get('/services'),
      ]);

      if (predRes.status === 'fulfilled') {
        const resData = predRes.value.data;
        setData(resData);
        setSource(resData.source || resData.ai_provider || 'groq');
        setAiProvider(resData.ai_provider || resData.source || 'groq');
        setAiModel(resData.ai_model || null);
        setLastUpdated(new Date());

        // Mise à jour de providerInfo pour badge stable
        if (resData && !resData.cache_hit) {
          setProviderInfo({
            provider: resData.ai_provider,
            model: resData.ai_model,
            loadedAt: new Date(),
            fromCache: false
          });
        } else if (resData && resData.cache_hit && (!providerInfo || isManualRefresh)) {
          setProviderInfo({
            provider: resData.provider_original ?? resData.ai_provider,
            model: resData.ai_model,
            loadedAt: new Date(resData.cached_at),
            fromCache: true,
          });
        }
      } else { 
        setError('Erreur chargement predictions'); 
      }

      if (svcRes.status === 'fulfilled') { 
        setServices(svcRes.value.data || []); 
      }
    } catch (err) { 
      setError('Erreur chargement API'); 
    } finally { 
      setLoading(false); 
    }
  }, [horizon, keywordFilter, providerInfo]);

  useEffect(() => { 
    load(); 
  }, [horizon, keywordFilter]);

  const handleRefresh = () => {
    load(true);
  };

  const chartData = useMemo(() => {
    if (!data) return [];
    const hist = (data.historique || []).map(h => ({ label: h.start_date, historique: h.total_revenus, prediction: null, confMin: null, confMax: null }));
    const preds = (data.predictions || []).map(p => ({ label: p.date, historique: null, prediction: p.revenus_predit, confMin: p.revenus_min, confMax: p.revenus_max }));
    return [...hist, ...preds];
  }, [data]);

  const moyenneHistorique = useMemo(() => {
    if (!data?.historique?.length) return 0;
    const vals = data.historique.map(h => h.total_revenus);
    return vals.reduce((a, b) => a + b, 0) / vals.length;
  }, [data]);

  const todayLabel = useMemo(() => { const d = new Date(); return d.toISOString().split('T')[0]; }, []);

  const predictions = data?.predictions || [];
  const resume = data?.resume_semaine || {};
  const score = data?.score_fiabilite || 0;
  const metriques = data?.metriques_avancees || {};
  const analyse = data?.analyse_detaillee || {};
  const recommandations = data?.recommandations || [];
  const predServices = data?.predictions_par_service || [];
  const isFallback = source === 'fallback' || source === 'php_fallback';

  const kpiCards = [
    { title: 'Revenu predit demain', value: predictions[0] ? formatDT(predictions[0].revenus_predit) : '—', sub: predictions[0] ? <TrendIcon trend={predictions[0].tendance} variation={predictions[0].variation_pct} /> : null, color: '#3b82f6' },
    { title: `Total predit ${horizon}j`, value: resume.total_predit ? formatDT(resume.total_predit) : '—', sub: resume.comparaison_semaine_precedente_pct !== undefined ? <span style={{ color: resume.comparaison_semaine_precedente_pct >= 0 ? '#16a34a' : '#dc2626' }}>{resume.comparaison_semaine_precedente_pct >= 0 ? '↑' : '↓'} {Math.abs(resume.comparaison_semaine_precedente_pct).toFixed(1)}% vs sem. prec.</span> : null, color: '#8b5cf6' },
    { title: 'Meilleur jour predit', value: resume.meilleur_jour ? formatDT(resume.meilleur_jour.montant) : '—', sub: resume.meilleur_jour ? `${JOURS_SHORT[new Date(resume.meilleur_jour.date).getDay()]} ${resume.meilleur_jour.date.slice(8)}/${resume.meilleur_jour.date.slice(5, 7)}` : null, color: '#16a34a' },
    { title: 'Pire jour predit', value: resume.pire_jour ? formatDT(resume.pire_jour.montant) : '—', sub: resume.pire_jour ? `${JOURS_SHORT[new Date(resume.pire_jour.date).getDay()]} ${resume.pire_jour.date.slice(8)}/${resume.pire_jour.date.slice(5, 7)}` : null, color: '#dc2626' },
      { title: 'Score fiabilite IA', value: <div style={{ width: 80, height: 80, display: 'flex', alignItems: 'center', justifyContent: 'center' }}><RadialBarChart width={80} height={80} cx="50%" cy="50%" innerRadius="60%" outerRadius="90%" data={[{ name: 'Score', value: score, fill: score >= 75 ? '#16a34a' : score >= 50 ? '#f59e0b' : '#dc2626' }]}><RadialBar dataKey="value" background clockWise /></RadialBarChart></div>, sub: <span style={{ color: 'var(--text-muted)', fontSize: '0.8rem' }}>Fiabilite IA</span>, color: '#f59e0b' },
    { title: 'Volatilite historique', value: metriques.volatilite !== undefined ? `${metriques.volatilite}%` : '—', sub: metriques.volatilite !== undefined ? <VolatilityBadge vol={metriques.volatilite} /> : null, color: '#06b6d4' },
  ];

  if (loading) {
    return (
      <div className="page">
        <div className="page-header" style={{ marginBottom: '1.2rem' }}>
          <div>
            <h1 className="page-title">Predictions IA</h1>
            <p className="page-subtitle">Prevision des revenus SMS+</p>
          </div>
        </div>
        <div className="kpi-grid-3" style={{ marginBottom: '1.2rem' }}>
          {Array.from({ length: 6 }).map((_, i) => <SkeletonCard key={i} />)}
        </div>
        <SkeletonChart />
        <div style={{ marginTop: '1.2rem' }}><SkeletonTable /></div>
      </div>
    );
  }

  if (data?.insuffisant) {
    return (
      <div className="page">
        <div className="page-header">
          <div>
            <h1 className="page-title">Predictions IA</h1>
            <p className="page-subtitle">Prevision des revenus SMS+</p>
          </div>
        </div>
        <div className="surface surface-pad" style={{ textAlign: 'center', padding: '2.5rem 1.5rem', marginTop: '1.5rem' }}>
          <div style={{ fontSize: '2.5rem', marginBottom: '0.75rem' }}>⚠️</div>
          <h3 style={{ margin: '0 0 0.5rem', color: 'var(--text-heading)' }}>Donnees insuffisantes</h3>
          <p style={{ color: 'var(--text-muted)', marginBottom: '1.25rem', maxWidth: 500, margin: '0 auto 1.25rem' }}>{data.message}</p>
          <button className="btn btn-primary" onClick={() => navigate('/import')}>Importer des donnees</button>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="page">
        <p style={{ color: 'var(--danger)', padding: '1rem' }}>{error}</p>
        <button className="btn btn-soft" onClick={load}>Reessayer</button>
      </div>
    );
  }

  return (
    <div className="page">

      {/* HEADER */}
      <FadeSection>
        <div className="page-header" style={{ marginBottom: '0.8rem', alignItems: 'center' }}>
          <div>
            <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem', flexWrap: 'wrap', marginBottom: '0.35rem' }}>
              <h1 className="page-title" style={{ margin: 0 }}>Predictions IA</h1>
              <ReliabilityBadge score={score} />
              {isFallback && <span className="badge badge-warn">Fallback</span>}
              {providerInfo && (
                <ProviderBadge 
                  provider={providerInfo.provider} 
                  model={providerInfo.model} 
                  fromCache={providerInfo.fromCache}
                  cachedAt={providerInfo.loadedAt}
                />
              )}
            </div>
            <p className="page-subtitle" style={{ margin: 0 }}>
              Prediction IA &middot; Indicatif seulement
            </p>
          </div>
          <div style={{ display: 'flex', alignItems: 'center', gap: '0.6rem', flexWrap: 'wrap' }}>
            {lastUpdated && (
              <span style={{ color: 'var(--text-muted)', fontSize: '0.82rem' }}>
                Maj il y a {Math.max(1, Math.round((Date.now() - lastUpdated) / 60000))} min
              </span>
            )}
            <button className="btn btn-soft" onClick={handleRefresh} disabled={loading}>
              {loading ? '...' : '↻ Refresh'}
            </button>
          </div>
        </div>
      </FadeSection>

      {/* CONTROLS */}
      <FadeSection>
        <div className="toolbar" style={{ marginBottom: '1rem', gap: '0.75rem' }}>
          <div className="field" style={{ minWidth: 140 }}>
            <span className="field-label">Horizon</span>
            <select className="field-control" value={horizon} onChange={e => setHorizon(Number(e.target.value))}>
              <option value={7}>7 jours</option>
              <option value={14}>14 jours</option>
              <option value={30}>30 jours</option>
            </select>
          </div>
          <div className="field" style={{ minWidth: 180 }}>
            <span className="field-label">Service</span>
            <select className="field-control" value={keywordFilter} onChange={e => setKeywordFilter(e.target.value)}>
              <option value="">Tous les services</option>
              {mappedServices.map(s => (
                <option key={s.keyword} value={s.keyword}>{s.nom_service}</option>
              ))}
            </select>
          </div>
        </div>
      </FadeSection>

      {/* ETL Timeline */}
      <JobStatusBar
        mode="timeline"
        jobTypes={[
          'prediction_data_collect',
          'prediction_metrics_calc',
          'prediction_groq_call',
          'prediction_cache_save'
        ]}
        steps={[
          { jobName: 'prediction_data_collect', label: 'Collecte données historiques' },
          { jobName: 'prediction_metrics_calc', label: 'Calcul métriques & tendances' },
          { jobName: 'prediction_groq_call', label: 'Analyse IA Groq' },
          { jobName: 'prediction_cache_save', label: 'Mise en cache résultats' },
        ]}
      />

      {/* KPI CARDS */}
      <FadeSection>
        <div className="kpi-grid-3" style={{ marginBottom: '1.2rem' }}>
          {kpiCards.map((k, i) => (
            <div key={i} className="kpi-card" style={{ borderTop: `3px solid ${k.color}` }}>
              <p style={{ margin: 0, color: 'var(--text-muted)', fontSize: '0.85rem', fontWeight: 500 }}>{k.title}</p>
              <div style={{ margin: '0.4rem 0 0', fontSize: '1.4rem', fontWeight: 700, color: 'var(--text-heading)' }}>{k.value}</div>
              {k.sub && <div style={{ marginTop: '0.5rem', fontSize: '0.82rem' }}>{k.sub}</div>}
            </div>
          ))}
        </div>
      </FadeSection>

      {/* CHART */}
      <FadeSection>
        <div className="surface surface-pad" style={{ marginBottom: '1.2rem' }}>
          <h3 className="text-heading" style={{ margin: '0 0 1rem', fontSize: '1rem', fontWeight: 700 }}>Historique &amp; Predictions</h3>
          <div style={{ width: '100%', height: 320, minHeight: 320 }}>
            <ResponsiveContainer width="100%" height={320} minWidth={0} debounce={50}>
              <ComposedChart data={chartData} margin={{ top: 8, right: 8, left: 0, bottom: 0 }}>
                <CartesianGrid strokeDasharray="3 3" stroke="var(--border)" vertical={false} />
                <XAxis dataKey="label" tick={{ fontSize: 10, fill: 'var(--text-muted)' }} interval="preserveStartEnd" angle={-30} textAnchor="end" height={50} />
                <YAxis tick={{ fontSize: 10, fill: 'var(--text-muted)' }} tickFormatter={formatCompactNumber} />
                <Tooltip content={<CustomTooltip />} />
                <Legend wrapperStyle={{ fontSize: 11, color: 'var(--text-muted)' }} formatter={(value) => (value === 'historique' ? 'Historique reel' : value === 'prediction' ? 'Prediction centrale' : value === 'confMin' ? 'Intervalle confiance' : value)} />
                <ReferenceLine x={todayLabel} stroke="#666" strokeDasharray="4 4" label={{ value: 'Auj.', position: 'insideTopRight', fontSize: 10, fill: '#666' }} />
                <ReferenceLine y={moyenneHistorique} stroke={C_MOY} strokeDasharray="4 4" label={{ value: 'Moy. histo', position: 'insideBottomRight', fontSize: 10, fill: C_MOY }} />
                <Bar dataKey="historique" fill={C_HISTO} radius={[3, 3, 0, 0]} maxBarSize={28} name="historique" />
                <Area type="monotone" dataKey="confMax" stroke="none" fill={C_CONF} name="confMin" />
                <Area type="monotone" dataKey="confMin" stroke="none" fill="transparent" name="confMin2" />
                <Line type="monotone" dataKey="prediction" stroke={C_PRED} strokeWidth={2.5} dot={{ r: 3, fill: C_PRED }} activeDot={{ r: 5 }} name="prediction" />
              </ComposedChart>
            </ResponsiveContainer>
          </div>
          <div style={{ marginTop: '0.5rem', fontSize: '0.75rem', color: 'var(--text-muted)', display: 'flex', gap: '1rem', flexWrap: 'wrap' }}>
            <span>■ Historique reel (bleu)</span>
            <span>● Prediction centrale (orange)</span>
            <span>░ Intervalle de confiance (orange clair)</span>
            <span>— Moyenne historique (gris pointille)</span>
          </div>
        </div>
      </FadeSection>

      {/* TABLE */}
      <FadeSection>
        <div className="panel table-wrap" style={{ marginBottom: '1.2rem', overflow: 'hidden' }}>
          <div style={{ padding: '1rem 1.5rem', borderBottom: '1px solid var(--border)' }}>
            <h3 className="text-heading" style={{ margin: 0, fontSize: '1rem' }}>Details des predictions</h3>
          </div>
          <table className="table-mobile" style={{ width: '100%', borderCollapse: 'collapse' }}>
            <thead>
              <tr>
                {['Date','Jour','Predit','Min','Max','Confiance','Tendance','Facteurs'].map(h => (
                  <th key={h} style={{ padding: '0.75rem 0.85rem', textAlign: 'left', fontSize: '0.75rem', color: 'var(--text-muted)', fontWeight: 600, textTransform: 'uppercase', letterSpacing: '0.03em' }}>{h}</th>
                ))}
              </tr>
            </thead>
            <tbody>
              {predictions.map((p) => {
                const isBest = resume.meilleur_jour?.date === p.date;
                const isWorst = resume.pire_jour?.date === p.date;
                let rowBg = 'transparent';
                if (isBest) rowBg = 'rgba(22,163,74,0.12)';
                if (isWorst) rowBg = 'rgba(220,38,38,0.12)';
                return (
                  <tr key={p.date} style={{ background: rowBg, transition: 'background 0.2s' }}>
                    <td data-label="Date" style={{ padding: '0.65rem 0.85rem', fontFamily: 'monospace', fontSize: '0.82rem' }}>{p.date}</td>
                    <td data-label="Jour" style={{ padding: '0.65rem 0.85rem', color: 'var(--text-muted)', fontSize: '0.82rem' }}>{p.jour_semaine}</td>
                    <td data-label="Predit" style={{ padding: '0.65rem 0.85rem', fontWeight: 700, color: 'var(--text-heading)', fontSize: '0.85rem' }}>{formatDT(p.revenus_predit)}</td>
                    <td data-label="Min" style={{ padding: '0.65rem 0.85rem', color: 'var(--text-muted)', fontSize: '0.82rem' }}>{formatDT(p.revenus_min)}</td>
                    <td data-label="Max" style={{ padding: '0.65rem 0.85rem', color: 'var(--text-muted)', fontSize: '0.82rem' }}>{formatDT(p.revenus_max)}</td>
                    <td data-label="Confiance" style={{ padding: '0.65rem 0.85rem', minWidth: 100 }}><ConfidenceBar pct={p.confidence_pct || 65} /></td>
                    <td data-label="Tendance" style={{ padding: '0.65rem 0.85rem' }}><TrendIcon trend={p.tendance} variation={p.variation_pct} /></td>
                    <td data-label="Facteurs" style={{ padding: '0.65rem 0.85rem' }}>
                      {p.facteurs?.length > 0 ? (
                        <span title={p.facteurs.join(', ')} style={{ cursor: 'help', fontSize: '0.78rem', color: 'var(--primary)' }}>Voir ({p.facteurs.length})</span>
                      ) : '—'}
                    </td>
                  </tr>
                );
              })}
            </tbody>
            <tfoot>
              <tr style={{ fontWeight: 700, background: 'var(--table-head-bg)' }}>
                <td colSpan={2} style={{ padding: '0.75rem 0.85rem', color: 'var(--text-heading)' }}>Total {horizon}j</td>
                <td style={{ padding: '0.75rem 0.85rem', color: 'var(--text-heading)' }}>{formatDT(resume.total_predit || 0)}</td>
                <td colSpan={5} />
              </tr>
            </tfoot>
          </table>
        </div>
      </FadeSection>

      {/* PREDICTIONS PAR SERVICE */}
      {predServices.length > 0 && (
        <FadeSection>
          <div style={{ marginBottom: '1.2rem' }}>
            <h3 className="text-heading" style={{ margin: '0 0 0.75rem', fontSize: '1rem', fontWeight: 700 }}>Predictions par service</h3>
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(240px, 1fr))', gap: '0.85rem' }}>
              {predServices.map((s, i) => {
                const totalAll = predServices.reduce((sum, x) => sum + (x.revenus_predit_7j || 0), 0);
                const part = totalAll > 0 ? Math.round((s.revenus_predit_7j / totalAll) * 100) : 0;
                const borderColor = s.tendance === 'hausse' ? '#16a34a' : s.tendance === 'baisse' ? '#dc2626' : '#94a3b8';
                return (
                  <div key={i} className="kpi-card" style={{ borderLeft: `3px solid ${borderColor}` }}>
                    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 4 }}>
                      <span style={{ fontWeight: 700, fontSize: '0.9rem', color: 'var(--text-heading)' }}>{s.keyword} &middot; {s.nom_service}</span>
                      <span className="badge" style={{ fontSize: '0.7rem' }}>{part}% du total</span>
                    </div>
                    <div style={{ fontSize: '1.25rem', fontWeight: 700, color: 'var(--text-heading)', marginBottom: 4 }}>{formatDT(s.revenus_predit_7j)}</div>
                    <div style={{ fontSize: '0.82rem' }}>
                      <TrendIcon trend={s.tendance} variation={s.variation_pct} />
                      <span style={{ color: 'var(--text-muted)', marginLeft: 8 }}>sur {horizon}j</span>
                    </div>
                  </div>
                );
              })}
            </div>
          </div>
        </FadeSection>
      )}

      {/* ANALYSE IA */}
      {analyse && (
        <FadeSection>
          <div style={{ marginBottom: '1.2rem' }}>
            <h3 className="text-heading" style={{ margin: '0 0 0.75rem', fontSize: '1rem', fontWeight: 700 }}>Analyse IA detaillee</h3>
            <Accordion title="Tendance generale" icon="📈" defaultOpen>
              <p style={{ margin: 0 }}>{analyse.tendance_generale || 'Aucune analyse disponible.'}</p>
            </Accordion>
            <Accordion title="Facteurs positifs" icon="✅">
              {analyse.facteurs_positifs?.length > 0 ? (
                <ul style={{ margin: 0, paddingLeft: '1.2rem' }}>
                  {analyse.facteurs_positifs.map((f, i) => (
                    <li key={i} style={{ color: '#16a34a', marginBottom: 4 }}>{typeof f === 'string' ? f : f.facteur}</li>
                  ))}
                </ul>
              ) : <p style={{ margin: 0, color: 'var(--text-muted)' }}>Aucun facteur positif identifie.</p>}
            </Accordion>
            <Accordion title="Facteurs de risque" icon="⚠️">
              {analyse.facteurs_risque?.length > 0 ? (
                <ul style={{ margin: 0, paddingLeft: '1.2rem' }}>
                  {analyse.facteurs_risque.map((f, i) => (
                    <li key={i} style={{ color: '#dc2626', marginBottom: 4 }}>{typeof f === 'string' ? f : f.risque}</li>
                  ))}
                </ul>
              ) : <p style={{ margin: 0, color: 'var(--text-muted)' }}>Aucun risque majeur identifie.</p>}
            </Accordion>
            <Accordion title="Opportunites" icon="💡">
              {analyse.opportunites?.length > 0 ? (
                <ul style={{ margin: 0, paddingLeft: '1.2rem' }}>
                  {analyse.opportunites.map((f, i) => (
                    <li key={i} style={{ color: '#3b82f6', marginBottom: 4 }}>{typeof f === 'string' ? f : f.opportunite}</li>
                  ))}
                </ul>
              ) : <p style={{ margin: 0, color: 'var(--text-muted)' }}>Aucune opportunite identifiee.</p>}
            </Accordion>
          </div>
        </FadeSection>
      )}

      {/* RECOMMANDATIONS */}
      {recommandations.length > 0 && (
        <FadeSection>
          <div style={{ marginBottom: '1.2rem' }}>
            <h3 className="text-heading" style={{ margin: '0 0 0.75rem', fontSize: '1rem', fontWeight: 700 }}>Recommandations IA</h3>
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(280px, 1fr))', gap: '0.85rem' }}>
              {recommandations.map((r, i) => {
                const priorityColor = r.priorite === 'haute' ? '#dc2626' : r.priorite === 'moyenne' ? '#f59e0b' : '#16a34a';
                return (
                  <div key={i} className="kpi-card" style={{ borderLeft: `3px solid ${priorityColor}` }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', marginBottom: 6 }}>
                      <span className="badge" style={{ background: priorityColor + '15', borderColor: priorityColor + '50', color: priorityColor, fontSize: '0.7rem', textTransform: 'uppercase' }}>
                        {r.priorite}
                      </span>
                    </div>
                    <p style={{ margin: '0 0 0.4rem', fontWeight: 600, color: 'var(--text-heading)', fontSize: '0.9rem' }}>{r.action}</p>
                    <p style={{ margin: '0 0 0.3rem', fontSize: '0.82rem', color: 'var(--text-muted)' }}><strong>Impact :</strong> {r.impact_estime}</p>
                    <p style={{ margin: 0, fontSize: '0.78rem', color: 'var(--text-muted)' }}><strong>Delai :</strong> {r.delai}</p>
                  </div>
                );
              })}
            </div>
          </div>
        </FadeSection>
      )}

      {/* METHODOLOGIE */}
      <FadeSection>
        <Accordion title="Methodologie & Avertissement" icon="📋">
          <p style={{ margin: '0 0 0.75rem' }}>{data?.methodologie || 'Analyse basee sur les tendances historiques.'}</p>
          <div style={{ background: 'rgba(245,158,11,0.1)', border: '1px solid rgba(245,158,11,0.3)', borderRadius: 8, padding: '0.75rem 1rem', fontSize: '0.82rem', color: 'var(--text-muted)', marginBottom: '0.75rem' }}>
            ⚠️ Les predictions sont indicatives et basees sur l historique disponible. Elles ne constituent pas une garantie de performance future.
          </div>
          <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
            <span className="badge badge-ok">Propulse par Groq AI &middot; Llama3</span>
            {isFallback && <span className="badge badge-warn">Mode fallback actif</span>}
          </div>
        </Accordion>
      </FadeSection>

    </div>
  );
}
