/* eslint-disable react/prop-types */
import { useEffect, useMemo, useState } from 'react';
import api from '../api/axios';
import { formatCompactNumber, formatDT } from '../lib/format';
import {
  AreaChart, Area, XAxis, YAxis, Tooltip, ResponsiveContainer, CartesianGrid,
  PieChart, Pie, Cell,
} from 'recharts';

const COLORS = ['#1a237e', '#0288d1', '#e65100'];

export default function SosDashboard() {
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [type, setType] = useState('ALL'); // ALL | SOLDE | DATA
  const [granularity, setGranularity] = useState('day'); // day | month
  const [days, setDays] = useState(30);
  const [payload, setPayload] = useState(null);

  useEffect(() => {
    let mounted = true;
    const load = async () => {
      setLoading(true);
      setError('');
      try {
        const res = await api.get(`/sos/kpis?type=${type}&granularity=${granularity}&days=${days}`);
        if (!mounted) return;
        setPayload(res.data);
      } catch {
        if (!mounted) return;
        setError("Impossible de charger le dashboard SOS. Vérifie l'API.");
        setPayload(null);
      } finally {
        if (mounted) setLoading(false);
      }
    };
    load();
    return () => { mounted = false; };
  }, [type, granularity, days]);

  const summary = payload?.summary || {};
  const series = payload?.series || [];

  const chartData = useMemo(() => (
    series.map((r) => ({
      period: r.period,
      credit: Number(r.credit || 0),
      repaid: Number(r.repaid || 0),
      fees: Number(r.fees || 0),
      nb: Number(r.nb || 0),
    }))
  ), [series]);

  const statusPie = useMemo(() => {
    const a = Number(summary.rembourses || 0);
    const b = Number(summary.partiels || 0);
    const c = Number(summary.impayes || 0);
    const rows = [
      { name: 'Remboursés', value: a },
      { name: 'Partiels', value: b },
      { name: 'Impayés', value: c },
    ].filter((x) => x.value > 0);
    return rows.length ? rows : [{ name: 'Aucune donnée', value: 1 }];
  }, [summary]);

  if (loading) return <p style={{ padding: '3rem', textAlign: 'center', color: 'var(--text-muted)' }}>Chargement...</p>;
  if (error) return <p style={{ padding: '3rem', textAlign: 'center', color: 'var(--danger)' }}>{error}</p>;
  if (!payload) return <p style={{ padding: '3rem', textAlign: 'center', color: 'var(--text-muted)' }}>Aucune donnée.</p>;

  const kpis = [
    { label: 'Parc SOS (MSISDN)', value: formatCompactNumber(summary.parc_msisdn), color: '#1a237e', icon: 'MS' },
    { label: 'Total SOS', value: formatCompactNumber(summary.total_sos), color: '#0288d1', icon: 'TS' },
    { label: 'Crédits octroyés', value: formatDT(summary.credit_total), color: '#00838f', icon: 'CR' },
    { label: 'Remboursements', value: formatDT(summary.repaid_total), color: '#2e7d32', icon: 'RB' },
    { label: 'Frais (fees)', value: formatDT(summary.fees_total), color: '#6a1b9a', icon: 'FR' },
    { label: 'Bad debts (3m)', value: formatDT(payload.bad_debts?.after_3_months?.unpaid_total), color: '#e65100', icon: 'BD' },
  ];

  return (
    <div className="page">
      <div className="page-header">
        <div>
          <h1 className="page-title">SOS Supervision &amp; Analytics</h1>
          <p className="page-subtitle">Monitoring synthétique (KPIs + bad debts) — données de test</p>
        </div>
      </div>

      <div className="toolbar toolbar-sticky-mobile" style={{ marginBottom: '1.25rem' }}>
        <div style={{ display: 'flex', gap: '0.5rem', alignItems: 'center' }}>
          <span style={{ color: 'var(--text-muted)', fontSize: '0.9rem' }}>Type:</span>
          {['ALL', 'SOLDE', 'DATA'].map((t) => (
            <button type="button" key={t} onClick={() => setType(t)} className="btn btn-pill" style={{
              background: type === t ? 'var(--primary)' : 'var(--chip-bg)',
              color: type === t ? '#fff' : 'var(--text-heading)',
            }}>{t}</button>
          ))}
        </div>

        <div style={{ display: 'flex', gap: '0.5rem', alignItems: 'center' }}>
          <span style={{ color: 'var(--text-muted)', fontSize: '0.9rem' }}>Période:</span>
          {[7, 30, 90, 180].map((d) => (
            <button type="button" key={d} onClick={() => setDays(d)} className="btn btn-pill" style={{
              background: days === d ? 'var(--primary-2)' : 'var(--chip-bg)',
              color: days === d ? '#fff' : 'var(--text-heading)',
            }}>{d}j</button>
          ))}
        </div>

        <div style={{ display: 'flex', gap: '0.5rem', alignItems: 'center' }}>
          <span style={{ color: 'var(--text-muted)', fontSize: '0.9rem' }}>Granularité:</span>
          {['day', 'month'].map((g) => (
            <button type="button" key={g} onClick={() => setGranularity(g)} className="btn btn-pill" style={{
              background: granularity === g ? '#0d9488' : 'var(--chip-bg)',
              color: granularity === g ? '#fff' : 'var(--text-heading)',
            }}>{g === 'day' ? 'Jour' : 'Mois'}</button>
          ))}
        </div>
      </div>

      <div className="kpi-grid-3" style={{ marginBottom: '1.2rem' }}>
        {kpis.map((k) => (
          <div key={k.label} className="kpi-card" style={{ padding: '1.25rem', display: 'flex', alignItems: 'center', gap: '1rem', borderTopColor: k.color }}>
            <div style={{ width: '46px', height: '46px', borderRadius: '12px', background: `${k.color}18`, display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: '1.3rem' }}>{k.icon}</div>
            <div>
              <p style={{ margin: 0, color: 'var(--text-muted)', fontSize: '0.85rem' }}>{k.label}</p>
              <h3 className="text-heading" style={{ margin: '0.2rem 0 0', fontSize: '1.25rem' }}>{k.value}</h3>
            </div>
          </div>
        ))}
      </div>

      <div className="grid-2" style={{ gridTemplateColumns: '2fr 1fr', gap: '1rem' }}>
        <div className="saas-surface" style={{ padding: '1.25rem' }}>
          <h3 className="text-heading" style={{ margin: '0 0 1rem', fontSize: '1rem' }}>Crédits vs Remboursements</h3>
          <div style={{ width: '100%', minHeight: 260 }}>
            <ResponsiveContainer width="100%" height={240}>
              <AreaChart data={chartData}>
                <defs>
                  <linearGradient id="sosCredit" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="5%" stopColor="#0288d1" stopOpacity={0.18} />
                    <stop offset="95%" stopColor="#0288d1" stopOpacity={0} />
                  </linearGradient>
                </defs>
                <CartesianGrid strokeDasharray="3 3" stroke="var(--border)" />
                <XAxis dataKey="period" tick={{ fontSize: 11, fill: 'var(--text-muted)' }} />
                <YAxis tick={{ fontSize: 11, fill: 'var(--text-muted)' }} tickFormatter={formatCompactNumber} />
                <Tooltip formatter={(v, n) => [formatDT(v), n]} />
                <Area type="monotone" dataKey="credit" stroke="#0288d1" strokeWidth={2} fill="url(#sosCredit)" name="Crédit" />
                <Area type="monotone" dataKey="repaid" stroke="#2e7d32" strokeWidth={2} fillOpacity={0} name="Remboursé" />
              </AreaChart>
            </ResponsiveContainer>
          </div>
        </div>

        <div className="saas-surface" style={{ padding: '1.25rem' }}>
          <h3 className="text-heading" style={{ margin: '0 0 1rem', fontSize: '1rem' }}>Statuts</h3>
          <ResponsiveContainer width="100%" height={240}>
            <PieChart>
              <Pie data={statusPie} dataKey="value" nameKey="name" cx="50%" cy="50%" outerRadius={85} label={({ name, percent }) => `${name} ${(percent * 100).toFixed(0)}%`}>
                {statusPie.map((_, i) => <Cell key={i} fill={COLORS[i % COLORS.length]} />)}
              </Pie>
              <Tooltip formatter={(v) => formatCompactNumber(v)} />
            </PieChart>
          </ResponsiveContainer>
          <div style={{ marginTop: '0.5rem', color: 'var(--text-muted)', fontSize: '0.85rem' }}>
            <div>Bad debts 3m: <strong style={{ color: 'var(--danger)' }}>{formatDT(payload.bad_debts?.after_3_months?.unpaid_total)}</strong></div>
            <div>Bad debts 6m: <strong style={{ color: 'var(--danger)' }}>{formatDT(payload.bad_debts?.after_6_months?.unpaid_total)}</strong></div>
          </div>
        </div>
      </div>
    </div>
  );
}

