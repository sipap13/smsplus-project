/* eslint-disable react/prop-types */
import { useEffect, useState } from 'react';
import api from '../api/axios';
import {
  AreaChart, Area, XAxis, YAxis, Tooltip,
  ResponsiveContainer, CartesianGrid,
  BarChart, Bar, Legend,
} from 'recharts';
import { formatCompactNumber, formatDT } from '../lib/format';

export default function Dashboard({ user }) {
  const [stats, setStats] = useState(null);
  const [revenus, setRevenus] = useState([]);
  const [services, setServices] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [includeData, setIncludeData] = useState(false); // inclure call_type=DATA
  const [mmgVsOcc, setMmgVsOcc] = useState([]);

  useEffect(() => {
    let mounted = true;

    const loadDashboard = async () => {
      setLoading(true);
      setError('');
      try {
        const statsRes = await api.get(`/dashboard/stats?include_data=${includeData ? 1 : 0}`);
        if (!mounted) return;
        setStats(statsRes.data);

        const results = await Promise.allSettled([
          api.get(`/dashboard/revenus?days=30&limit=4000&include_data=${includeData ? 1 : 0}`),
          api.get('/services'),
          api.get(`/dashboard/mmg-vs-occ?days=10&include_data=${includeData ? 1 : 0}`),
        ]);

        if (!mounted) return;

        const [revenusRes, servicesRes, mmgVsOccRes] = results;
        setMmgVsOcc(mmgVsOccRes.status === 'fulfilled' ? mmgVsOccRes.value.data : []);
        let dayRevenus = revenusRes.status === 'fulfilled' ? revenusRes.value.data : [];
        setServices(servicesRes.status === 'fulfilled' ? servicesRes.value.data : []);

        if (revenusRes.status !== 'fulfilled' || dayRevenus.length === 0) {
          setRevenus([]);
          if (results.some((r) => r.status === 'rejected')) {
            setError("Certaines donnees n'ont pas pu etre chargees. Verifie l'API.");
          }
        } else {
          setRevenus(dayRevenus);
          if (results.some((r) => r.status === 'rejected')) {
            setError("Certaines donnees n'ont pas pu etre chargees. Verifie l'API.");
          }
        }
      } catch {
        if (!mounted) return;
        setError("Impossible de charger le dashboard. Verifie que l'API est demarree.");
      } finally {
        if (mounted) setLoading(false);
      }
    };

    loadDashboard();
    return () => { mounted = false; };
  }, [includeData]);

  if (loading) return (
    <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', height: '400px', color: 'var(--text-muted)' }}>
      <div style={{ textAlign: 'center' }}>
        <p>Chargement du tableau de bord...</p>
      </div>
    </div>
  );

  if (!stats) {
    return (
      <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', height: '400px', color: 'var(--text-muted)' }}>
        <div style={{ textAlign: 'center' }}>
          <p style={{ marginBottom: '0.75rem' }}>Erreur de chargement.</p>
          <button
            onClick={() => window.location.reload()}
            className="btn btn-soft"
            type="button"
          >
            Reessayer
          </button>
        </div>
      </div>
    );
  }

  // Aggregate revenues by day (start_date) or by hour (hour) for area chart.
  const revenusParTime = revenus.reduce((acc, row) => {
    const isHourRow = row.hour !== undefined && row.hour !== null;
    const key = isHourRow ? String(row.hour) : row.start_date;
    if (key === null || key === undefined || key === '') return acc;

    const label = isHourRow
      ? `${String(row.hour).padStart(2, '0')}:00`
      : row.start_date;

    if (!acc[key]) acc[key] = { date: label, total: 0, nb_cdr: 0 };
    acc[key].total  += parseFloat(row.total  || 0);
    acc[key].nb_cdr += parseInt(row.nb_cdr || 0);
    return acc;
  }, {});

  const chartData = Object.values(revenusParTime)
    .map(r => ({ ...r, total: parseFloat(r.total.toFixed(3)) }))
    .sort((a, b) => (a.date || '').localeCompare(b.date || ''));

  // Revenus by service for bar chart
  const revenusParService = revenus.reduce((acc, row) => {
    const svc = services.find(s => s.keyword === row.keyword);
    const defaultLabel = row.keyword === 'DATA' ? 'Trafic Data' : row.keyword;
    const name = svc ? svc.nom_service : (defaultLabel || 'Autre');
    if (!acc[name]) acc[name] = 0;
    acc[name] += parseFloat(row.total || 0);
    return acc;
  }, {});
  const barData = Object.entries(revenusParService)
    .map(([name, total]) => ({ name, total: parseFloat(total.toFixed(3)) }))
    .sort((a, b) => b.total - a.total);
  const totalServicesRevenue = barData.reduce((sum, item) => sum + item.total, 0);
  const dataRevenue = barData.find((s) => s.name === 'Trafic Data')?.total || 0;
  const dataShare = totalServicesRevenue > 0 ? dataRevenue / totalServicesRevenue : 0;
  const hideDataForCharts = !includeData && dataShare >= 0.95;
  const nonDominantBarData = barData.filter((s) => s.name !== 'Trafic Data');
  const serviceChartData = hideDataForCharts && nonDominantBarData.length >= 2 ? nonDominantBarData : barData;
  const topServicesData = hideDataForCharts && nonDominantBarData.length >= 2 ? nonDominantBarData : barData;
  const serviceChartTitle = hideDataForCharts && nonDominantBarData.length >= 2
    ? 'Revenus par Service (hors Trafic Data)'
    : 'Revenus par Service';

  const kpis = [
    {
      label: includeData ? 'Revenus totaux (incl. Data)' : 'Revenus totaux',
      value: formatDT(stats.total_revenus),
      icon: '↗',
      color: '#1a237e',
      trend: '+12%',
    },
    { label: 'Abonnés actifs',   value: formatCompactNumber(stats.abonnes_actifs),  icon: '↗', color: '#0288d1', trend: '+5%' },
    { label: 'Total CDR',  value: `${formatCompactNumber(stats.cdr_du_jour)}+`, icon: '•', color: '#00838f', trend: '—' },
    { label: 'Anomalies détectées',  value: formatCompactNumber((mmgVsOcc || []).filter((x) => Math.abs((x.mmg || 0) - (x.occ || 0)) > 1000).length),     icon: 'AL', color: '#c62828', trend: '0' },
  ];

  const tooltipStyle = { borderRadius: 8, border: '1px solid var(--border)', background: 'var(--bg-surface)', color: 'var(--text-main)' };

  return (
    <div className="page" style={{ minHeight: '100%' }}>
      {error && (
        <div style={{ marginBottom: '1rem', background: 'rgba(245, 158, 11, 0.12)', color: 'var(--text-main)', border: '1px solid rgba(245, 158, 11, 0.35)', borderRadius: '8px', padding: '0.75rem 1rem', fontSize: '0.9rem' }}>
          {error}
        </div>
      )}
      <div className="page-header tt-page-head" style={{ marginBottom: '1.2rem' }}>
        <div>
          <h1 className="page-title">Tableau de bord</h1>
          <p className="page-subtitle">
            {user?.email?.split('@')[0]} — Assurance & Fraude — SMS+ VAS
          </p>
        </div>
        <label className="btn btn-ghost" style={{ display: 'inline-flex', alignItems: 'center', gap: '0.55rem' }}>
          <input
            type="checkbox"
            checked={includeData}
            onChange={(e) => setIncludeData(e.target.checked)}
          />
          Inclure DATA
        </label>
      </div>

      {/* KPI Cards */}
      <div className="kpi-grid-4" style={{ marginBottom: '1.2rem' }}>
        {kpis.map(k => (
          <div key={k.label} className="kpi-card tt-kpi" style={{ padding: '1.25rem', borderTopColor: 'transparent' }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start' }}>
              <div>
                <p style={{ margin: 0, color: 'var(--text-muted)', fontSize: '0.95rem', fontWeight: 500 }}>{k.label}</p>
                <h2 className="text-heading num" style={{ margin: '0.4rem 0 0', fontSize: '2rem', fontWeight: 700, color: 'var(--text-main)' }}>{k.value}</h2>
              </div>
              <span className="badge" style={{ background: 'var(--bg-surface)', color: 'var(--text-muted)' }}>{k.icon}</span>
            </div>
            <p style={{ margin: '0.7rem 0 0', fontSize: '0.85rem', color: 'var(--text-muted)' }}>
              {k.trend !== '—' && k.trend !== '0' ? `${k.trend} vs période précédente` : 'Volume traité actuel'}
            </p>
          </div>
        ))}
      </div>

      {/* MMG vs OCC — aligné rapport « Trafic SMS+ » */}
      <div className="surface surface-pad" style={{ marginBottom: '1.2rem' }}>
        <div style={{ marginBottom: '1rem' }}>
          <h3 className="text-heading" style={{ margin: 0, fontSize: '1.1rem', fontWeight: 700, color: 'var(--text-main)' }}>Trafic MMG vs OCC</h3>
          <p style={{ margin: '0.25rem 0 0', fontSize: '0.85rem', color: 'var(--text-muted)' }}>Volume CDR journalier (10 derniers jours)</p>
        </div>
        {mmgVsOcc.length === 0 ? (
          <p style={{ textAlign: 'center', color: 'var(--text-muted)', padding: '2rem 0' }}>Aucune donnée MMG/OCC sur cette période</p>
        ) : (
          <div style={{ width: '100%', minHeight: 300 }}>
            <ResponsiveContainer width="100%" height={280} minWidth={0} minHeight={0}>
              <BarChart data={mmgVsOcc} margin={{ top: 8, right: 8, left: 0, bottom: 0 }}>
                <CartesianGrid strokeDasharray="3 3" stroke="var(--border)" vertical={false} />
                <XAxis dataKey="label" tick={{ fontSize: 11, fill: 'var(--text-muted)' }} interval={0} angle={-35} textAnchor="end" height={56} />
                <YAxis tick={{ fontSize: 11, fill: 'var(--text-muted)' }} tickFormatter={formatCompactNumber} />
                <Tooltip
                  formatter={(v) => [formatCompactNumber(v), '']}
                  labelFormatter={(l) => `Date : ${l}`}
                  contentStyle={tooltipStyle}
                />
                <Legend wrapperStyle={{ fontSize: 12, color: 'var(--text-muted)' }} />
                <Bar dataKey="mmg" name="MMG" fill="#1f2f74" radius={[4, 4, 0, 0]} maxBarSize={28} />
                <Bar dataKey="occ" name="OCC" fill="#5c6c9e" radius={[4, 4, 0, 0]} maxBarSize={28} />
              </BarChart>
            </ResponsiveContainer>
          </div>
        )}
      </div>

      <div className="grid-2" style={{ gridTemplateColumns: '2fr 1fr', marginBottom: '1.2rem' }}>
        {/* Area chart */}
        <div className="surface surface-pad">
          <h3 className="text-heading" style={{ margin: '0 0 1rem', fontSize: '1rem', fontWeight: 700, color: 'var(--text-main)' }}>Revenus par service</h3>
          {chartData.length === 0 ? (
            <p style={{ textAlign: 'center', color: 'var(--text-muted)', padding: '3rem 0' }}>Aucune donnée disponible</p>
          ) : (
            <div style={{ width: '100%', minHeight: 260 }}>
            <ResponsiveContainer width="100%" height={240} minWidth={0} minHeight={0}>
              <AreaChart data={chartData}>
                <defs>
                  <linearGradient id="colorTotal" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="5%" stopColor="#243776" stopOpacity={0.18} />
                    <stop offset="95%" stopColor="#243776" stopOpacity={0} />
                  </linearGradient>
                </defs>
                <CartesianGrid strokeDasharray="3 3" stroke="var(--border)" />
                <XAxis dataKey="date" tick={{ fontSize: 11, fill: 'var(--text-muted)' }} />
                <YAxis tick={{ fontSize: 11, fill: 'var(--text-muted)' }} tickFormatter={formatCompactNumber} />
                <Tooltip formatter={(v) => [formatDT(v), 'Revenus']} contentStyle={tooltipStyle} />
                <Area type="monotone" dataKey="total" stroke="#243776" strokeWidth={2.2} fill="url(#colorTotal)" name="Revenus (DT)" />
              </AreaChart>
            </ResponsiveContainer>
            </div>
          )}
        </div>

        {/* Top services */}
        <div className="surface surface-pad">
          <h3 className="text-heading" style={{ margin: '0 0 1rem', fontSize: '1rem', fontWeight: 700, color: 'var(--text-main)' }}>Meilleurs services</h3>
          {topServicesData.length === 0 ? (
            <p style={{ textAlign: 'center', color: 'var(--text-muted)', padding: '3rem 0' }}>Aucune donnée</p>
          ) : topServicesData.slice(0, 5).map((s, i) => {
            const max = topServicesData[0]?.total || 1;
            const pct = ((s.total / max) * 100).toFixed(0);
            return (
              <div key={s.name} style={{ marginBottom: '1rem' }}>
                <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '0.3rem' }}>
                  <span style={{ fontSize: '0.85rem', fontWeight: 600, color: 'var(--text-main)' }}>{s.name}</span>
                  <span className="text-heading" style={{ fontSize: '0.82rem', fontWeight: 700 }}>{formatDT(s.total)}</span>
                </div>
                <div style={{ background: 'var(--table-head-bg)', borderRadius: '6px', height: '6px' }}>
                  <div style={{ width: `${pct}%`, height: '100%', background: i % 2 ? '#5c6c9e' : '#1f2f74', borderRadius: '6px', transition: 'width 0.5s' }} />
                </div>
              </div>
            );
          })}
        </div>
      </div>

      {/* Bar chart by service */}
      {serviceChartData.length > 0 && (
        <div className="saas-surface" style={{ borderRadius: '10px', padding: '1.2rem' }}>
          <h3 className="text-heading" style={{ margin: '0 0 1.5rem', fontSize: '1rem', fontWeight: 700 }}>{serviceChartTitle}</h3>
          <div style={{ width: '100%', minHeight: 240 }}>
          <ResponsiveContainer width="100%" height={220} minWidth={0} minHeight={0}>
            <BarChart data={serviceChartData}>
              <CartesianGrid strokeDasharray="3 3" stroke="var(--border)" />
              <XAxis dataKey="name" tick={{ fontSize: 11, fill: 'var(--text-muted)' }} />
              <YAxis tick={{ fontSize: 11, fill: 'var(--text-muted)' }} tickFormatter={formatCompactNumber} />
              <Tooltip formatter={(v) => [formatDT(v), 'Revenus']} contentStyle={tooltipStyle} />
              <Bar dataKey="total" radius={[6, 6, 0, 0]} fill="#1f2f74" name="Revenus (DT)" />
            </BarChart>
          </ResponsiveContainer>
          </div>
        </div>
      )}
    </div>
  );
}