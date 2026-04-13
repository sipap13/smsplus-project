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
  const [view, setView] = useState('day'); // day | month
  const [monthly, setMonthly] = useState([]);
  const [top20, setTop20] = useState([]);
  const [byFournisseur, setByFournisseur] = useState([]);
  const [exporting, setExporting] = useState(false);

  useEffect(() => {
    setLoading(true);
    setError('');
    setVisibleRows(50);
    let mounted = true;
    const load = async () => {
      try {
        const results = await Promise.allSettled([
          api.get(`/dashboard/revenus?days=${days}&limit=5000&include_data=${includeData ? 1 : 0}`),
          api.get(`/dashboard/revenus-monthly?months=12&include_data=${includeData ? 1 : 0}`),
          api.get(`/dashboard/top-services?days=${days}&topN=20&include_data=${includeData ? 1 : 0}`),
          api.get(`/dashboard/revenus-fournisseur?days=${days}&topN=20&include_data=${includeData ? 1 : 0}`),
          api.get('/services'),
        ]);

        const revenusRes = results[0];
        const monthlyRes = results[1];
        const topRes = results[2];
        const fourRes = results[3];
        const servicesRes = results[4];

        const revenus = revenusRes.status === 'fulfilled' ? (revenusRes.value.data || []) : [];

        if (!mounted) return;
        setData(revenus);
        setMonthly(monthlyRes.status === 'fulfilled' ? (monthlyRes.value.data || []) : []);
        setTop20(topRes.status === 'fulfilled' ? (topRes.value.data || []) : []);
        setByFournisseur(fourRes.status === 'fulfilled' ? (fourRes.value.data || []) : []);
        setServices(servicesRes.status === 'fulfilled' ? (servicesRes.value.data || []) : []);
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

  const displayDate = (raw) => {
    if (!raw || !/^\d{4}-\d{2}-\d{2}$/.test(raw)) return raw || '—';
    const [y, m, d] = raw.split('-');
    return `${d}/${m}/${y}`;
  };

  const totalRevenus  = data.reduce((s, r) => s + parseFloat(r.total || 0), 0);
  const totalCdr      = data.reduce((s, r) => s + parseInt(r.nb_cdr || 0), 0);

  const monthlyChart = (monthly || []).map((r) => ({
    period: r.month,
    total: Number(r.total || 0),
    nb_cdr: Number(r.nb_cdr || 0),
  }));

  const exportCsv = async () => {
    try {
      setExporting(true);
      const rows = data.map((row) => {
        const svc = services.find((s) => s.keyword === row.keyword);
        const dateLabel = row.start_date ? row.start_date : `${String(row.hour).padStart(2, '0')}:00`;
        return {
          date: dateLabel,
          fournisseur: svc?.nom_fournisseur || '',
          service: svc?.nom_service || '',
          keyword: row.keyword || '',
          nb_cdr: row.nb_cdr || 0,
          revenus_dt: Number(row.total || 0).toFixed(3),
        };
      });

      const header = ['date', 'fournisseur', 'service', 'keyword', 'nb_cdr', 'revenus_dt'];
      const lines = [
        header.join(';'),
        ...rows.map((r) => [r.date, r.fournisseur, r.service, r.keyword, r.nb_cdr, r.revenus_dt]
          .map((v) => `"${String(v).replaceAll('"', '""')}"`).join(';')),
      ];
      const csv = `\uFEFF${lines.join('\n')}`;
      const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
      const href = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = href;
      a.download = `revenus_${days}j_${includeData ? 'with_data' : 'smsplus'}.csv`;
      document.body.appendChild(a);
      a.click();
      a.remove();
      URL.revokeObjectURL(href);
    } catch {
      setError("Export CSV impossible. Verifie la connexion et les permissions.");
    } finally {
      setExporting(false);
    }
  };

  if (loading) return <p style={{ padding: '3rem', textAlign: 'center', color: 'var(--text-muted)' }}>Chargement...</p>;
  if (error) return <p style={{ padding: '3rem', textAlign: 'center', color: 'var(--danger)' }}>{error}</p>;

  return (
    <div className="page">
      <div className="page-header">
        <div>
          <h1 className="page-title">Analyse des revenus</h1>
          <p className="page-subtitle">Analyse des revenus SMS+ par service et par date</p>
        </div>
      </div>
      <div className="toolbar">
        <label style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', cursor: 'pointer', userSelect: 'none' }}>
          <input
            type="checkbox"
            checked={includeData}
            onChange={(e) => setIncludeData(e.target.checked)}
          />
          Inclure `DATA` (Trafic Data)
        </label>
        <button
          type="button"
          onClick={exportCsv}
          disabled={exporting}
          className="btn btn-soft"
          style={{ marginLeft: 'auto' }}
        >
          {exporting ? 'Export en cours...' : 'Exporter CSV'}
        </button>
      </div>
      <div className="toolbar">
        <span style={{ color: 'var(--text-muted)', fontSize: '0.9rem' }}>Période :</span>
        {[7, 30, 90].map((value) => (
          <button
            type="button"
            key={value}
            onClick={() => setDays(value)}
            className="btn btn-pill"
            style={{
              background: days === value ? 'var(--primary)' : 'var(--chip-bg)',
              color: days === value ? '#fff' : 'var(--text-heading)',
              fontWeight: 600,
            }}
          >
            {value} jours
          </button>
        ))}
        <div style={{ marginLeft: 'auto', display: 'flex', gap: '0.5rem', alignItems: 'center' }}>
          <span style={{ color: 'var(--text-muted)', fontSize: '0.9rem' }}>Vue :</span>
          {['day', 'month'].map((v) => (
            <button
              type="button"
              key={v}
              onClick={() => setView(v)}
              className="btn btn-pill"
              style={{
                background: view === v ? '#0d9488' : 'var(--chip-bg)',
                color: view === v ? '#fff' : 'var(--text-heading)',
                fontWeight: 600,
              }}
            >
              {v === 'day' ? 'Jour' : 'Mois'}
            </button>
          ))}
        </div>
      </div>

      {/* KPI summary */}
      <div className="kpi-grid-3" style={{ marginBottom: '1.2rem' }}>
        {[
          {
            label: includeData ? 'Total Revenus (incl. Trafic Data)' : 'Total Revenus SMS+',
            value: formatDT(totalRevenus),
            color: '#1a237e',
            icon: 'RV',
          },
          { label: 'Total transactions', value: totalCdr.toLocaleString('fr-FR'), color: '#0288d1', icon: 'TR' },
          { label: 'Services actifs', value: pieDataForChart.length, color: '#00838f', icon: 'SV' },
        ].map(k => (
          <div key={k.label} className="kpi-card" style={{ display: 'flex', alignItems: 'center', gap: '1rem' }}>
            <div style={{ width: '50px', height: '50px', borderRadius: '12px', background: k.color, display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: '1.4rem' }}>
              {k.icon}
            </div>
            <div>
              <p style={{ margin: 0, color: 'var(--text-muted)', fontSize: '0.85rem' }}>{k.label}</p>
              <h3 className="text-heading" style={{ margin: '0.2rem 0 0', fontSize: '1.3rem' }}>{k.value}</h3>
            </div>
          </div>
        ))}
      </div>

      <div className="grid-2" style={{ marginBottom: '1.2rem' }}>
        {/* Bar chart by date */}
        <div className="saas-surface" style={{ padding: '1.5rem' }}>
          <h3 className="text-heading" style={{ margin: '0 0 1.5rem', fontSize: '1rem' }}>
            Revenus par {view === 'month' ? 'mois' : (isHourMode ? 'heure' : 'date')}
          </h3>
          <ResponsiveContainer width="100%" height={250}>
            <BarChart data={view === 'month' ? monthlyChart : barData}>
              <CartesianGrid strokeDasharray="3 3" stroke="var(--border)" />
              <XAxis dataKey={view === 'month' ? 'period' : 'date'} tick={{ fontSize: 11 }} />
              <YAxis tick={{ fontSize: 11 }} tickFormatter={formatCompactNumber} />
              <Tooltip formatter={(v) => [formatDT(v), 'Revenus']} />
              <Bar dataKey="total" fill="var(--primary)" radius={[4, 4, 0, 0]} name="Revenus (DT)" />
            </BarChart>
          </ResponsiveContainer>
        </div>

        {/* Pie chart by service */}
        <div className="saas-surface" style={{ padding: '1.5rem' }}>
          <h3 className="text-heading" style={{ margin: '0 0 1.5rem', fontSize: '1rem' }}>
            {hideDataInPie && pieDataForChart.length >= 2 ? 'Répartition par service (hors Trafic Data)' : 'Répartition par service'}
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

      <div className="grid-2" style={{ marginBottom: '1.2rem' }}>
        <div className="saas-surface" style={{ padding: '1.5rem' }}>
          <h3 className="text-heading" style={{ margin: '0 0 1rem', fontSize: '1rem' }}>Top 20 services</h3>
          {top20.length === 0 ? <p style={{ color: 'var(--text-muted)' }}>Aucune donnée</p> : (
            <ol style={{ margin: 0, paddingLeft: '1.25rem', color: 'var(--text-main)' }}>
              {top20.map((r, idx) => (
                <li key={idx} style={{ marginBottom: '0.35rem' }}>
                  <strong>{r.service}</strong> <span style={{ color: 'var(--text-muted)' }}>({r.fournisseur})</span> — <span style={{ color: 'var(--success)', fontWeight: 700 }}>{formatDT(r.total)}</span>
                </li>
              ))}
            </ol>
          )}
        </div>

        <div className="saas-surface" style={{ padding: '1.5rem' }}>
          <h3 className="text-heading" style={{ margin: '0 0 1rem', fontSize: '1rem' }}>Revenus par fournisseur (Top 20)</h3>
          {byFournisseur.length === 0 ? <p style={{ color: 'var(--text-muted)' }}>Aucune donnée</p> : (
            <ol style={{ margin: 0, paddingLeft: '1.25rem', color: 'var(--text-main)' }}>
              {byFournisseur.map((r, idx) => (
                <li key={idx} style={{ marginBottom: '0.35rem' }}>
                  <strong>{r.fournisseur}</strong> — <span style={{ color: 'var(--success)', fontWeight: 700 }}>{formatDT(r.total)}</span>
                </li>
              ))}
            </ol>
          )}
        </div>
      </div>

      {/* Detail table */}
      <div className="panel table-wrap" style={{ overflow: 'hidden' }}>
        <div style={{ padding: '1rem 1.5rem', borderBottom: '1px solid var(--border)' }}>
          <h3 className="text-heading" style={{ margin: 0, fontSize: '1rem' }}>Synthèse journalière des revenus</h3>
        </div>
        <table className="table-mobile" style={{ width: '100%', borderCollapse: 'collapse' }}>
          <thead>
            <tr>
              {['Date', 'Nombre de transactions', 'Revenus (DT)'].map(h => (
                <th key={h} style={{ padding: '0.875rem 1rem', textAlign: 'left', fontSize: '0.85rem', color: 'var(--text-muted)', fontWeight: 600, borderBottom: '2px solid var(--border)' }}>
                  {h}
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {barData.slice().reverse().slice(0, visibleRows).map((row, i) => {
              const dateLabel = row.date;
              return (
                <tr key={i}>
                  <td data-label="Date" style={{ padding: '0.75rem 1rem', fontFamily: 'monospace', color: 'var(--text-muted)' }}>{displayDate(dateLabel)}</td>
                  <td data-label="Nb Transactions" style={{ padding: '0.75rem 1rem', color: 'var(--text-main)' }}>{parseInt(row.nb_cdr, 10).toLocaleString('fr-FR')}</td>
                  <td data-label="Revenus (DT)" style={{ padding: '0.75rem 1rem', fontWeight: 600, color: '#2e7d32' }}>{formatDT(row.total)}</td>
                </tr>
              );
            })}
          </tbody>
          <tfoot>
            <tr style={{ fontWeight: 700 }}>
              <td className="text-heading" style={{ padding: '0.875rem 1rem' }}>TOTAL</td>
              <td className="text-heading" style={{ padding: '0.875rem 1rem' }}>{totalCdr.toLocaleString('fr-FR')}</td>
              <td style={{ padding: '0.875rem 1rem', color: '#2e7d32' }}>{formatDT(totalRevenus)}</td>
            </tr>
          </tfoot>
        </table>
        {barData.length > visibleRows && (
          <div style={{ padding: '1rem', textAlign: 'center', borderTop: '1px solid var(--border)' }}>
            <button
              type="button"
              onClick={() => setVisibleRows(v => v + 50)}
              className="btn btn-soft"
            >
              Charger plus
            </button>
          </div>
        )}
      </div>
    </div>
  );
}
