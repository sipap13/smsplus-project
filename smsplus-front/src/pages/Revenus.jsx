import { useEffect, useState } from 'react';
import api from '../api/axios';
import {
  BarChart, Bar, XAxis, YAxis, Tooltip, ResponsiveContainer,
  CartesianGrid, PieChart, Pie, Cell,
} from 'recharts';
import { formatCompactNumber, formatDT } from '../lib/format';

const COLORS = ['#1a237e', '#0288d1', '#00838f', '#2e7d32', '#e65100', '#6a1b9a'];

export default function Revenus() {
  const [data, setData]       = useState([]);
  const [services, setServices] = useState([]);
  const [loading, setLoading]  = useState(true);
  const [error, setError] = useState('');
  const [days, setDays] = useState(30);
  const [visibleRows, setVisibleRows] = useState(50);
  const [includeData, setIncludeData] = useState(false); // inclure call_type=DATA

  useEffect(() => {
    setLoading(true);
    setError('');
    setVisibleRows(50);
    let mounted = true;
    const load = async () => {
      try {
        const [revenusRes, servicesRes] = await Promise.all([
          api.get(`/dashboard/revenus?days=${days}&limit=1000&include_data=${includeData ? 1 : 0}`),
          api.get('/services'),
        ]);

        let revenus = revenusRes.data || [];

        if (!mounted) return;
        setData(revenus);
        setServices(servicesRes.data || []);
        setLoading(false);
      } catch {
        if (!mounted) return;
        setError("Impossible de charger les revenus. Verifie l'API.");
        setData([]);
        setServices([]);
        setLoading(false);
      }
    };

    load();
    return () => { mounted = false; };
  }, [days, includeData]);

  // Group by keyword for pie chart
  const byKeyword = data.reduce((acc, row) => {
    const key = row.keyword || 'Autre';
    const svc = services.find(s => s.keyword === key);
    const defaultLabel = key === 'DATA' ? 'Trafic Data' : key;
    const name = svc ? svc.nom_service : defaultLabel;
    if (!acc[name]) acc[name] = 0;
    acc[name] += parseFloat(row.total || 0);
    return acc;
  }, {});

  const pieData = Object.entries(byKeyword).map(([name, value]) => ({
    name, value: parseFloat(value.toFixed(3)),
  }));
  const totalPie = pieData.reduce((sum, row) => sum + row.value, 0);
  const dataPie = pieData.find((row) => row.name === 'Trafic Data')?.value || 0;
  const hideDataInPie = !includeData && totalPie > 0 && (dataPie / totalPie) >= 0.95;
  const filteredPieData = hideDataInPie ? pieData.filter((row) => row.name !== 'Trafic Data') : pieData;
  const pieDataForChart = filteredPieData.length >= 2 ? filteredPieData : pieData;

  const isHourMode = false;

  // Group by start_date (day mode) or hour (hour mode)
  const byTime = data.reduce((acc, row) => {
    const key = row.start_date ?? row.hour;
    if (key === undefined || key === null || key === '') return acc;

    const label = row.start_date
      ? row.start_date
      : `${String(row.hour).padStart(2, '0')}:00`;

    if (!acc[label]) acc[label] = { date: label, total: 0, nb_cdr: 0 };
    acc[label].total  += parseFloat(row.total || 0);
    acc[label].nb_cdr += parseInt(row.nb_cdr || 0);
    return acc;
  }, {});

  const barData = Object.values(byTime)
    .map(r => ({ ...r, total: parseFloat(r.total.toFixed(3)) }))
    .sort((a, b) => (a.date || '').localeCompare(b.date || ''));

  const totalRevenus  = data.reduce((s, r) => s + parseFloat(r.total || 0), 0);
  const totalCdr      = data.reduce((s, r) => s + parseInt(r.nb_cdr || 0), 0);

  if (loading) return <p style={{ padding: '3rem', textAlign: 'center', color: '#888' }}>Chargement...</p>;
  if (error) return <p style={{ padding: '3rem', textAlign: 'center', color: '#c62828' }}>⚠️ {error}</p>;

  return (
    <div style={{ padding: '2rem' }}>
      <h1 style={{ margin: '0 0 0.4rem', color: '#1a237e', fontSize: '1.6rem' }}>💰 Revenus Détaillés</h1>
      <p style={{ margin: '0 0 2rem', color: '#888', fontSize: '0.9rem' }}>Analyse des revenus SMS+ par service et par date</p>
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
      <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem', marginBottom: '1rem' }}>
        <span style={{ color: '#555', fontSize: '0.9rem' }}>Période :</span>
        {[7, 30, 90].map((value) => (
          <button
            key={value}
            onClick={() => setDays(value)}
            style={{
              padding: '0.4rem 0.8rem',
              borderRadius: '20px',
              border: 'none',
              cursor: 'pointer',
              background: days === value ? '#1a237e' : '#e8eaf6',
              color: days === value ? '#fff' : '#1a237e',
              fontWeight: 600,
            }}
          >
            {value} jours
          </button>
        ))}
      </div>

      {/* KPI summary */}
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: '1rem', marginBottom: '2rem' }}>
        {[
          {
            label: includeData ? 'Total Revenus (incl. Trafic Data)' : 'Total Revenus SMS+',
            value: formatDT(totalRevenus),
            color: '#1a237e',
            icon: '💰',
          },
          { label: 'Total Transactions', value: totalCdr.toLocaleString('fr-FR'), color: '#0288d1', icon: '📱' },
          { label: 'Services actifs', value: pieDataForChart.length, color: '#00838f', icon: '📋' },
        ].map(k => (
          <div key={k.label} style={{ background: 'white', borderRadius: '12px', padding: '1.5rem', boxShadow: '0 2px 12px rgba(0,0,0,0.08)', display: 'flex', alignItems: 'center', gap: '1rem' }}>
            <div style={{ width: '50px', height: '50px', borderRadius: '12px', background: k.color, display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: '1.4rem' }}>
              {k.icon}
            </div>
            <div>
              <p style={{ margin: 0, color: '#888', fontSize: '0.85rem' }}>{k.label}</p>
              <h3 style={{ margin: '0.2rem 0 0', color: '#1a237e', fontSize: '1.3rem' }}>{k.value}</h3>
            </div>
          </div>
        ))}
      </div>

      <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '1.5rem', marginBottom: '1.5rem' }}>
        {/* Bar chart by date */}
        <div style={{ background: 'white', borderRadius: '12px', padding: '1.5rem', boxShadow: '0 2px 12px rgba(0,0,0,0.08)' }}>
          <h3 style={{ margin: '0 0 1.5rem', color: '#1a237e', fontSize: '1rem' }}>
            📈 Revenus par {isHourMode ? 'heure' : 'date'}
          </h3>
          <ResponsiveContainer width="100%" height={250}>
            <BarChart data={barData}>
              <CartesianGrid strokeDasharray="3 3" stroke="#f0f0f0" />
              <XAxis dataKey="date" tick={{ fontSize: 11 }} />
              <YAxis tick={{ fontSize: 11 }} tickFormatter={formatCompactNumber} />
              <Tooltip formatter={(v) => [formatDT(v), 'Revenus']} />
              <Bar dataKey="total" fill="#1a237e" radius={[4, 4, 0, 0]} name="Revenus (DT)" />
            </BarChart>
          </ResponsiveContainer>
        </div>

        {/* Pie chart by service */}
        <div style={{ background: 'white', borderRadius: '12px', padding: '1.5rem', boxShadow: '0 2px 12px rgba(0,0,0,0.08)' }}>
          <h3 style={{ margin: '0 0 1.5rem', color: '#1a237e', fontSize: '1rem' }}>
            {hideDataInPie && pieDataForChart.length >= 2 ? '🥧 Répartition par service (hors Trafic Data)' : '🥧 Répartition par service'}
          </h3>
          <ResponsiveContainer width="100%" height={250}>
            <PieChart>
              <Pie data={pieDataForChart} dataKey="value" nameKey="name" cx="50%" cy="50%" outerRadius={90} label={({ name, percent }) => `${name} ${(percent * 100).toFixed(0)}%`}>
                {pieDataForChart.map((_, i) => <Cell key={i} fill={COLORS[i % COLORS.length]} />)}
              </Pie>
              <Tooltip formatter={(v) => formatDT(v)} />
            </PieChart>
          </ResponsiveContainer>
        </div>
      </div>

      {/* Detail table */}
      <div style={{ background: 'white', borderRadius: '12px', boxShadow: '0 2px 12px rgba(0,0,0,0.08)', overflow: 'hidden' }}>
        <div style={{ padding: '1rem 1.5rem', borderBottom: '1px solid #f0f0f0' }}>
          <h3 style={{ margin: 0, color: '#1a237e', fontSize: '1rem' }}>📊 Détail par date &amp; service</h3>
        </div>
        <table style={{ width: '100%', borderCollapse: 'collapse' }}>
          <thead>
            <tr style={{ background: '#f5f7ff' }}>
              {['Date', 'Keyword / Service', 'Nb Transactions', 'Revenus (DT)'].map(h => (
                <th key={h} style={{ padding: '0.875rem 1rem', textAlign: 'left', fontSize: '0.85rem', color: '#666', fontWeight: 600, borderBottom: '2px solid #e8eaf6' }}>
                  {h}
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {data.slice(0, visibleRows).map((row, i) => {
              const svc = services.find(s => s.keyword === row.keyword);
              const dateLabel = row.start_date ? row.start_date : `${String(row.hour).padStart(2, '0')}:00`;
              return (
                <tr key={i} style={{ background: i % 2 === 0 ? 'white' : '#fafafa' }}>
                  <td style={{ padding: '0.75rem 1rem', fontFamily: 'monospace', color: '#555' }}>{dateLabel}</td>
                  <td style={{ padding: '0.75rem 1rem' }}>
                    <span style={{ background: '#e8eaf6', color: '#1a237e', padding: '0.2rem 0.5rem', borderRadius: '20px', fontSize: '0.82rem', fontWeight: 600, marginRight: '0.5rem' }}>{row.keyword}</span>
                    <span style={{ color: '#666', fontSize: '0.88rem' }}>{svc?.nom_service || ''}</span>
                  </td>
                  <td style={{ padding: '0.75rem 1rem', color: '#333' }}>{parseInt(row.nb_cdr).toLocaleString('fr-FR')}</td>
                  <td style={{ padding: '0.75rem 1rem', fontWeight: 600, color: '#2e7d32' }}>{formatDT(row.total)}</td>
                </tr>
              );
            })}
          </tbody>
          <tfoot>
            <tr style={{ background: '#f5f7ff', fontWeight: 700 }}>
              <td colSpan={2} style={{ padding: '0.875rem 1rem', color: '#1a237e' }}>TOTAL</td>
              <td style={{ padding: '0.875rem 1rem', color: '#1a237e' }}>{totalCdr.toLocaleString('fr-FR')}</td>
              <td style={{ padding: '0.875rem 1rem', color: '#2e7d32' }}>{formatDT(totalRevenus)}</td>
            </tr>
          </tfoot>
        </table>
        {data.length > visibleRows && (
          <div style={{ padding: '1rem', textAlign: 'center', borderTop: '1px solid #f0f0f0' }}>
            <button
              onClick={() => setVisibleRows(v => v + 50)}
              style={{
                padding: '0.5rem 1rem',
                border: 'none',
                borderRadius: '8px',
                background: '#e8eaf6',
                color: '#1a237e',
                fontWeight: 600,
                cursor: 'pointer',
              }}
            >
              Charger plus
            </button>
          </div>
        )}
      </div>
    </div>
  );
}
