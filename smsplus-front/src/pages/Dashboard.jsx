/* eslint-disable react/prop-types */
import { useEffect, useState } from 'react';
import api from '../api/axios';
import {
  AreaChart, Area, XAxis, YAxis, Tooltip,
  ResponsiveContainer, CartesianGrid,
  BarChart, Bar,
} from 'recharts';
import { formatCompactNumber, formatDT } from '../lib/format';

export default function Dashboard({ user }) {
  const [stats, setStats] = useState(null);
  const [revenus, setRevenus] = useState([]);
  const [services, setServices] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [includeData, setIncludeData] = useState(false); // inclure call_type=DATA

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
          api.get(`/dashboard/revenus?days=30&limit=300&include_data=${includeData ? 1 : 0}`),
          api.get('/services'),
        ]);

        if (!mounted) return;

        const [revenusRes, servicesRes] = results;
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
    <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', height: '400px', color: '#888' }}>
      <div style={{ textAlign: 'center' }}>
        <div style={{ fontSize: '2rem', marginBottom: '0.5rem' }}>⏳</div>
        <p>Chargement du tableau de bord...</p>
      </div>
    </div>
  );

  if (!stats) {
    return (
      <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', height: '400px', color: '#666' }}>
        <div style={{ textAlign: 'center' }}>
          <div style={{ fontSize: '2rem', marginBottom: '0.5rem' }}>⚠️</div>
          <p style={{ marginBottom: '0.75rem' }}>Erreur de chargement.</p>
          <button
            onClick={() => window.location.reload()}
            style={{ border: 'none', borderRadius: '8px', padding: '0.5rem 0.9rem', cursor: 'pointer', background: '#e8eaf6', color: '#1a237e', fontWeight: 600 }}
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
    ? '📊 Revenus par Service (hors Trafic Data)'
    : '📊 Revenus par Service';

  const kpis = [
    {
      label: includeData ? 'Revenus Total (incl. Trafic Data)' : 'Revenus SMS+',
      value: formatDT(stats.total_revenus),
      icon: '💰',
      color: '#1a237e',
      trend: '+12%',
    },
    { label: 'Abonnés Actifs',   value: formatCompactNumber(stats.abonnes_actifs),  icon: '👥', color: '#0288d1', trend: '+5%' },
    { label: 'Services Actifs',  value: formatCompactNumber(stats.services_actifs), icon: '📋', color: '#00838f', trend: '—' },
    { label: "CDR Aujourd'hui",  value: formatCompactNumber(stats.cdr_du_jour),     icon: '📱', color: '#2e7d32', trend: '0' },
  ];

  return (
    <div style={{ padding: '2rem', background: '#f8f9ff', minHeight: '100%' }}>
      {error && (
        <div style={{ marginBottom: '1rem', background: '#fff3e0', color: '#e65100', border: '1px solid #ffcc80', borderRadius: '8px', padding: '0.75rem 1rem', fontSize: '0.9rem' }}>
          ⚠️ {error}
        </div>
      )}
      {/* Welcome */}
      <div style={{ marginBottom: '2rem' }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem', marginBottom: '1rem' }}>
          <label style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', cursor: 'pointer', userSelect: 'none' }}>
            <input
              type="checkbox"
              checked={includeData}
              onChange={(e) => setIncludeData(e.target.checked)}
            />
            Inclure `DATA` (Trafic Data)
          </label>
        </div>
        <h1 style={{ margin: 0, color: '#1a237e', fontSize: '1.6rem' }}>
          👋 Bonjour, <span style={{ color: '#0288d1' }}>{user?.email?.split('@')[0]}</span>
        </h1>
        <p style={{ margin: '0.3rem 0 0', color: '#888', fontSize: '0.9rem' }}>
          {new Date().toLocaleDateString('fr-FR', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })}
           — Tableau de bord SMS+ Tunisie Telecom
        </p>
      </div>

      {/* KPI Cards */}
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(4, 1fr)', gap: '1rem', marginBottom: '2rem' }}>
        {kpis.map(k => (
          <div key={k.label} style={{
            background: 'white', borderRadius: '16px', padding: '1.5rem',
            boxShadow: '0 4px 20px rgba(0,0,0,0.06)',
            borderTop: `4px solid ${k.color}`,
            transition: 'transform 0.2s, box-shadow 0.2s',
          }}
            onMouseEnter={e => { e.currentTarget.style.transform = 'translateY(-2px)'; e.currentTarget.style.boxShadow = '0 8px 30px rgba(0,0,0,0.12)'; }}
            onMouseLeave={e => { e.currentTarget.style.transform = 'translateY(0)'; e.currentTarget.style.boxShadow = '0 4px 20px rgba(0,0,0,0.06)'; }}
          >
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start' }}>
              <div>
                <p style={{ margin: 0, color: '#999', fontSize: '0.82rem', textTransform: 'uppercase', letterSpacing: '0.5px' }}>{k.label}</p>
                <h2 style={{ margin: '0.5rem 0 0', color: '#1a237e', fontSize: '1.6rem', fontWeight: 800 }}>{k.value}</h2>
              </div>
              <div style={{ width: '44px', height: '44px', borderRadius: '12px', background: `${k.color}18`, display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: '1.3rem' }}>
                {k.icon}
              </div>
            </div>
            <p style={{ margin: '1rem 0 0', fontSize: '0.82rem', color: k.trend.startsWith('+') ? '#2e7d32' : '#888' }}>
              {k.trend !== '—' && k.trend !== '0' ? `📈 ${k.trend} vs mois dernier` : '📊 Données actuelles'}
            </p>
          </div>
        ))}
      </div>

      <div style={{ display: 'grid', gridTemplateColumns: '2fr 1fr', gap: '1.5rem', marginBottom: '1.5rem' }}>
        {/* Area chart */}
        <div style={{ background: 'white', borderRadius: '16px', padding: '1.5rem', boxShadow: '0 4px 20px rgba(0,0,0,0.06)' }}>
          <h3 style={{ margin: '0 0 1.5rem', color: '#1a237e', fontSize: '1rem', fontWeight: 700 }}>📈 Évolution des Revenus</h3>
          {chartData.length === 0 ? (
            <p style={{ textAlign: 'center', color: '#ccc', padding: '3rem 0' }}>Aucune donnée disponible</p>
          ) : (
            <ResponsiveContainer width="100%" height={240}>
              <AreaChart data={chartData}>
                <defs>
                  <linearGradient id="colorTotal" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="5%" stopColor="#1a237e" stopOpacity={0.15} />
                    <stop offset="95%" stopColor="#1a237e" stopOpacity={0} />
                  </linearGradient>
                </defs>
                <CartesianGrid strokeDasharray="3 3" stroke="#f0f4ff" />
                <XAxis dataKey="date" tick={{ fontSize: 11, fill: '#888' }} />
                <YAxis tick={{ fontSize: 11, fill: '#888' }} tickFormatter={formatCompactNumber} />
                <Tooltip formatter={(v) => [formatDT(v), 'Revenus']} contentStyle={{ borderRadius: '8px', border: '1px solid #e8eaf6' }} />
                <Area type="monotone" dataKey="total" stroke="#1a237e" strokeWidth={2} fill="url(#colorTotal)" name="Revenus (DT)" />
              </AreaChart>
            </ResponsiveContainer>
          )}
        </div>

        {/* Top services */}
        <div style={{ background: 'white', borderRadius: '16px', padding: '1.5rem', boxShadow: '0 4px 20px rgba(0,0,0,0.06)' }}>
          <h3 style={{ margin: '0 0 1.5rem', color: '#1a237e', fontSize: '1rem', fontWeight: 700 }}>🏆 Top Services</h3>
          {topServicesData.length === 0 ? (
            <p style={{ textAlign: 'center', color: '#ccc', padding: '3rem 0' }}>Aucune donnée</p>
          ) : topServicesData.slice(0, 5).map((s, i) => {
            const max = topServicesData[0]?.total || 1;
            const pct = ((s.total / max) * 100).toFixed(0);
            return (
              <div key={s.name} style={{ marginBottom: '1rem' }}>
                <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '0.3rem' }}>
                  <span style={{ fontSize: '0.85rem', fontWeight: 600, color: '#333' }}>{s.name}</span>
                  <span style={{ fontSize: '0.82rem', color: '#1a237e', fontWeight: 700 }}>{formatDT(s.total)}</span>
                </div>
                <div style={{ background: '#f0f4ff', borderRadius: '6px', height: '6px' }}>
                  <div style={{ width: `${pct}%`, height: '100%', background: `hsl(${220 + i * 20}, 70%, ${50 + i * 5}%)`, borderRadius: '6px', transition: 'width 0.5s' }} />
                </div>
              </div>
            );
          })}
        </div>
      </div>

      {/* Bar chart by service */}
      {serviceChartData.length > 0 && (
        <div style={{ background: 'white', borderRadius: '16px', padding: '1.5rem', boxShadow: '0 4px 20px rgba(0,0,0,0.06)' }}>
          <h3 style={{ margin: '0 0 1.5rem', color: '#1a237e', fontSize: '1rem', fontWeight: 700 }}>{serviceChartTitle}</h3>
          <ResponsiveContainer width="100%" height={220}>
            <BarChart data={serviceChartData}>
              <CartesianGrid strokeDasharray="3 3" stroke="#f0f4ff" />
              <XAxis dataKey="name" tick={{ fontSize: 11, fill: '#888' }} />
              <YAxis tick={{ fontSize: 11, fill: '#888' }} tickFormatter={formatCompactNumber} />
              <Tooltip formatter={(v) => [formatDT(v), 'Revenus']} contentStyle={{ borderRadius: '8px' }} />
              <Bar dataKey="total" radius={[6, 6, 0, 0]} fill="#0288d1" name="Revenus (DT)" />
            </BarChart>
          </ResponsiveContainer>
        </div>
      )}
    </div>
  );
}