/* eslint-disable react/prop-types */
import { useEffect, useState, useMemo, useRef } from 'react';
import api from '../api/axios';
import {
  AreaChart, Area, XAxis, YAxis, Tooltip,
  ResponsiveContainer, CartesianGrid,
  BarChart, Bar, Legend, Brush, ReferenceLine,
  ComposedChart, Line, Cell
} from 'recharts';
import { formatCompactNumber, formatDT } from '../lib/format';
import JobStatusBar from '../components/JobStatusBar';
import { usePeriode } from '../hooks/usePeriode';
import { format, parseISO, differenceInDays, subDays } from 'date-fns';
import { PieChart, Pie, Cell as PieCell } from 'recharts';
import useServiceMapping from '../hooks/useServiceMapping';

// --- Shared Components ---

const CardHeader = ({ title, subtitle, children }) => (
  <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: '1rem' }}>
    <div>
      <h3 className="text-heading" style={{ margin: 0, fontSize: '1.1rem', fontWeight: 700, color: 'var(--text-main)' }}>{title}</h3>
      {subtitle && <p style={{ margin: '0.25rem 0 0', fontSize: '0.85rem', color: 'var(--text-muted)' }}>{subtitle}</p>}
    </div>
    <div style={{ display: 'flex', gap: '8px' }}>
      {children}
    </div>
  </div>
);

const MiniStat = ({ label, value, color }) => (
  <div style={{ fontSize: '0.85rem', color: 'var(--text-muted)', borderRight: '1px solid var(--border)', paddingRight: '12px', marginRight: '12px' }}>
    <span style={{ fontWeight: 500 }}>{label}:</span>{' '}
    <span style={{ fontWeight: 700, color: color || 'var(--text-main)' }}>{value}</span>
  </div>
);

// --- Sub-Components ---

const GlobalPeriodControls = ({ periode, setPreset, setCustom }) => {
  const [isCustom, setIsCustom] = useState(periode.preset === 'custom');
  const [dates, setDates] = useState({ start: periode.startDate, end: periode.endDate });

  const presets = [
    { id: 'today', label: "Aujourd'hui" },
    { id: '7j', label: "7j" },
    { id: '14j', label: "14j" },
    { id: '30j', label: "30j" },
    { id: 'ce_mois', label: "Ce mois" },
    { id: 'mois_dernier', label: "Mois dernier" },
  ];

  const handleApply = () => {
    setCustom(dates.start, dates.end);
  };

  return (
    <div className="glass-card" style={{ padding: '16px 20px', marginBottom: '24px' }}>
      <div style={{ display: 'flex', alignItems: 'center', gap: '12px', flexWrap: 'wrap' }}>
        <span style={{ fontWeight: 600, fontSize: '14px', color: 'var(--text-main)', display: 'flex', alignItems: 'center', gap: '6px' }}>
          📅 Période d'analyse
        </span>
        
        <div style={{ display: 'flex', gap: '6px' }}>
          {presets.map(p => (
            <button
              key={p.id}
              className={`btn ${periode.preset === p.id ? 'btn-primary' : 'btn-soft'}`}
              style={{ 
                height: '32px', 
                fontSize: '13px', 
                padding: '0 12px',
                background: periode.preset === p.id ? 'var(--primary)' : 'var(--bg-surface)',
                color: periode.preset === p.id ? 'white' : 'var(--text-main)',
                border: '1px solid var(--border)'
              }}
              onClick={() => { setPreset(p.id); setIsCustom(false); }}
            >
              {p.label}
            </button>
          ))}
          <button
            className={`btn ${isCustom ? 'btn-primary' : 'btn-soft'}`}
            style={{ 
              height: '32px', 
              fontSize: '13px', 
              padding: '0 12px',
              background: isCustom ? 'var(--primary)' : 'var(--bg-surface)',
              color: isCustom ? 'white' : 'var(--text-main)',
              border: '1px solid var(--border)'
            }}
            onClick={() => setIsCustom(true)}
          >
            Custom
          </button>
        </div>

        {isCustom && (
          <div style={{ display: 'flex', alignItems: 'center', gap: '8px', marginLeft: '8px' }}>
            <span style={{ fontSize: '13px', color: 'var(--text-muted)' }}>Du</span>
            <input 
              type="date" 
              value={dates.start} 
              onChange={e => setDates(prev => ({ ...prev, start: e.target.value }))}
              style={{ height: '32px', border: '1px solid var(--border)', borderRadius: '6px', padding: '0 8px', fontSize: '13px', background: 'var(--bg-surface)', color: 'var(--text-main)' }}
            />
            <span style={{ fontSize: '13px', color: 'var(--text-muted)' }}>au</span>
            <input 
              type="date" 
              value={dates.end} 
              onChange={e => setDates(prev => ({ ...prev, end: e.target.value }))}
              style={{ height: '32px', border: '1px solid var(--border)', borderRadius: '6px', padding: '0 8px', fontSize: '13px', background: 'var(--bg-surface)', color: 'var(--text-main)' }}
            />
            <button 
              className="btn btn-primary" 
              style={{ height: '32px', fontSize: '13px', fontWeight: 600 }}
              onClick={handleApply}
            >
              Appliquer
            </button>
          </div>
        )}

        <div style={{ marginLeft: 'auto', fontSize: '13px', color: '#64748b', fontWeight: 500 }}>
           {periode.label} · {format(new Date(periode.startDate), 'dd/MM')} → {format(new Date(periode.endDate), 'dd/MM/yyyy')}
        </div>
      </div>
    </div>
  );
};

const TrafficChart = ({ startDate, endDate, label }) => {
  const [data, setData] = useState([]);
  const [loading, setLoading] = useState(true);
  const [granularity, setGranularity] = useState('week');
  const [options, setOptions] = useState({ mmg: true, occ: true, ecart: false, moyenne: true });

  const fetchData = async () => {
    setLoading(true);
    try {
      const res = await api.get(`/dashboard/trafic-mmg-occ?start_date=${startDate}&end_date=${endDate}&granularite=${granularity}`);
      setData(res.data);
    } catch (err) {
      console.error(err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    // Auto granularity only if date range changes significantly
    const diff = differenceInDays(new Date(endDate), new Date(startDate));
    if (diff <= 2) setGranularity('hour');
    else if (diff >= 7) setGranularity('week');
    else setGranularity('day');
  }, [startDate, endDate]);

  useEffect(() => {
    fetchData();
  }, [startDate, endDate, granularity]);

  // Normalise data: ensure valeur_capped always exists so Bar dataKey never points at undefined
  const normalizedData = useMemo(() =>
    data.map(d => ({
      ...d,
      valeur_capped: d.valeur_capped !== undefined ? d.valeur_capped : d.occ,
    })),
    [data]
  );

  const stats = useMemo(() => {
    if (data.length === 0) return null;
    const totalMmg = data.reduce((acc, d) => acc + d.mmg, 0);
    const totalOcc = data.reduce((acc, d) => acc + d.occ, 0);
    const avgEcart = data.reduce((acc, d) => acc + d.ecart_pct, 0) / data.length;
    const pic = [...data].sort((a, b) => b.occ - a.occ)[0];
    const meanOcc = totalOcc / data.length;
    return { totalMmg, totalOcc, avgEcart, pic, meanOcc };
  }, [data]);

  const CustomTooltip = ({ active, payload }) => {
    if (active && payload && payload.length) {
      const d = payload[0].payload;
      return (
        <div style={{
          background: '#0f172a',
          color: '#f1f5f9',
          borderRadius: '8px',
          padding: '10px 14px',
          fontSize: '12px',
          lineHeight: '1.8',
          boxShadow: '0 4px 15px rgba(0,0,0,0.4)',
          border: '1px solid #1e293b'
        }}>
          <div style={{ fontWeight: 700, marginBottom: '6px' }}>📅 {d.label}</div>
          <div style={{ display: 'flex', justifyContent: 'space-between', gap: '20px' }}>
            <span style={{ color: '#94a3b8' }}>MMG :</span>
            <span style={{ fontWeight: 700 }}>{formatCompactNumber(d.mmg)} CDR</span>
          </div>
          <div style={{ display: 'flex', justifyContent: 'space-between', gap: '20px' }}>
            <span style={{ color: '#94a3b8' }}>OCC :</span>
            <span style={{ fontWeight: 700, color: '#6366f1' }}>{formatCompactNumber(d.occ)} CDR</span>
          </div>
          <div style={{ display: 'flex', justifyContent: 'space-between', gap: '20px', borderTop: '1px solid #1e293b', paddingTop: '4px', marginTop: '4px' }}>
            <span style={{ color: '#94a3b8' }}>Écart :</span>
            <span style={{ fontWeight: 700, color: d.ecart_pct > 5 ? '#ef4444' : '#10b981' }}>
              {d.ecart_pct > 5 ? '⚠' : '✓'} {d.ecart_pct}%
            </span>
          </div>
        </div>
      );
    }
    return null;
  };


  return (
    <div className="glass-card surface-pad" style={{ marginBottom: '1.2rem', position: 'relative' }}>
      <CardHeader title="Trafic MMG vs OCC" subtitle={`Volume CDR · ${label}`}>
        <select 
          className="select-sm" 
          value={granularity} 
          onChange={e => setGranularity(e.target.value)}
          style={{ height: '32px', borderRadius: '6px', border: '1px solid var(--border)', padding: '0 8px', fontSize: '13px', background: 'var(--bg-surface)', color: 'var(--text-main)' }}
        >
          <option value="hour">Par heure</option>
          <option value="day">Par jour</option>
          <option value="week">Par semaine</option>
        </select>
        <div className="dropdown">
           <button className="btn btn-soft" style={{ height: '32px', padding: '0 8px' }}>⚙</button>
           <div className="dropdown-content" style={{ padding: '8px', minWidth: '180px' }}>
              <label style={{ display: 'flex', alignItems: 'center', gap: '8px', padding: '4px', cursor: 'pointer' }}>
                <input type="checkbox" checked={options.mmg} onChange={e => setOptions(prev => ({...prev, mmg: e.target.checked}))} /> MMG
              </label>
              <label style={{ display: 'flex', alignItems: 'center', gap: '8px', padding: '4px', cursor: 'pointer' }}>
                <input type="checkbox" checked={options.occ} onChange={e => setOptions(prev => ({...prev, occ: e.target.checked}))} /> OCC
              </label>
              <label style={{ display: 'flex', alignItems: 'center', gap: '8px', padding: '4px', cursor: 'pointer' }}>
                <input type="checkbox" checked={options.ecart} onChange={e => setOptions(prev => ({...prev, ecart: e.target.checked}))} /> Afficher écart
              </label>
              <label style={{ display: 'flex', alignItems: 'center', gap: '8px', padding: '4px', cursor: 'pointer' }}>
                <input type="checkbox" checked={options.moyenne} onChange={e => setOptions(prev => ({...prev, moyenne: e.target.checked}))} /> Moyenne
              </label>
           </div>
        </div>
      </CardHeader>

      <div style={{ display: 'flex', marginBottom: '16px', overflowX: 'auto', paddingBottom: '4px' }}>
         <MiniStat label="Total MMG" value={formatCompactNumber(stats?.totalMmg || 0)} />
         <MiniStat label="Total OCC" value={formatCompactNumber(stats?.totalOcc || 0)} />
         <MiniStat label="Écart moy" value={`${Number(stats?.avgEcart || 0).toFixed(1)}%`} color={stats?.avgEcart > 5 ? '#ef4444' : '#10b981'} />
         <MiniStat label="Pic" value={`${stats?.pic?.label || ''} (${formatCompactNumber(stats?.pic?.occ || 0)})`} />
      </div>

      <div style={{ width: '100%', height: 350 }}>
          <ResponsiveContainer width="100%" height={280} minWidth={0} debounce={50}>
          <ComposedChart key={granularity} data={normalizedData}>
            <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="var(--border)" />
            <XAxis dataKey="label" tick={{ fontSize: 11 }} />
            <YAxis tick={{ fontSize: 11 }} tickFormatter={formatCompactNumber} />
            <Tooltip content={<CustomTooltip />} />
            <Legend verticalAlign="top" align="right" height={36} />
            
            {options.mmg && <Bar dataKey="mmg" name="MMG" fill="#1e2f74" radius={[4, 4, 0, 0]} maxBarSize={30} />}
            {options.occ && (
              <Bar dataKey="occ" name="OCC" fill="#6366f1" radius={[4, 4, 0, 0]} maxBarSize={30} />
            )}

            
            {options.ecart && <Line type="monotone" dataKey="ecart_pct" name="Écart %" stroke="#f59e0b" strokeWidth={2} dot={{ r: 4 }} />}
            {options.moyenne && stats && (
              <ReferenceLine 
                y={stats.meanOcc} 
                stroke="#94a3b8" 
                strokeDasharray="5 5" 
                label={{ position: 'right', value: `Moy: ${formatCompactNumber(stats.meanOcc)}`, fill: '#64748b', fontSize: 11 }} 
              />
            )}
            
            <Brush dataKey="label" height={30} stroke="var(--border)" fill="var(--bg-surface)" />
          </ComposedChart>
        </ResponsiveContainer>
      </div>

    </div>
  );
};

const RevenusChart = ({ startDate, endDate }) => {
  const [data, setData] = useState([]);
  const [loading, setLoading] = useState(true);
  const [service, setService] = useState('all');
  const [granularite, setGranularity] = useState('week');
  
  const { services: mappedServices } = useServiceMapping();

  const fetchData = async () => {
    setLoading(true);
    try {
      const url = `/dashboard/revenus-par-service?start_date=${startDate}&end_date=${endDate}&granularite=${granularite}${service !== 'all' ? `&keyword=${service}` : ''}`;
      const res = await api.get(url);
      setData(res.data);
    } catch (err) {
      console.error(err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    const diff = differenceInDays(new Date(endDate), new Date(startDate));
    if (diff <= 2) setGranularity('hour');
    else if (diff >= 7) setGranularity('week');
    else setGranularity('day');
  }, [startDate, endDate]);

  useEffect(() => {
    fetchData();
  }, [startDate, endDate, service, granularite]);

  const stats = useMemo(() => {
    if (data.length === 0) return null;
    const totals = data.map(d => d.total || d.revenus || 0);
    const total = totals.reduce((a, b) => a + b, 0);
    const avg = total / data.length;
    const max = Math.max(...totals);
    const min = Math.min(...totals);
    return { total, avg, max, min };
  }, [data]);

  // Dynamic colors for services
  const colors = ['#6366f1', '#ec4899', '#f59e0b', '#10b981', '#ef4444', '#8b5cf6', '#06b6d4'];

  return (
    <div className="surface surface-pad" style={{ marginBottom: '1.2rem', background: 'var(--bg-elevated)', border: '1px solid var(--border)' }}>
      <CardHeader title="Revenus par service" subtitle={`Analyse des revenus (DT) · ${startDate} au ${endDate}`}>
        <select 
          className="select-sm" 
          value={service} 
          onChange={e => setService(e.target.value)}
          style={{ height: '32px', borderRadius: '6px', border: '1px solid var(--border)', padding: '0 8px', fontSize: '13px', background: 'var(--bg-surface)', color: 'var(--text-main)' }}
        >
          <option value="all">Tous les services</option>
          {mappedServices.map(s => <option key={s.keyword} value={s.keyword}>{s.nom_service}</option>)}
        </select>
        <select 
          className="select-sm" 
          value={granularite} 
          onChange={e => setGranularity(e.target.value)}
          style={{ height: '32px', borderRadius: '6px', border: '1px solid var(--border)', padding: '0 8px', fontSize: '13px', background: 'var(--bg-surface)', color: 'var(--text-main)' }}
        >
          <option value="hour">Par heure</option>
          <option value="day">Par jour</option>
          <option value="week">Par semaine</option>
        </select>
      </CardHeader>

      <div style={{ display: 'flex', marginBottom: '16px', overflowX: 'auto', paddingBottom: '4px' }}>
         <MiniStat label="Total période" value={formatDT(stats?.total || 0)} />
         <MiniStat label="Moyenne/jour" value={formatDT(stats?.avg || 0)} />
         <MiniStat label="Max pic" value={formatDT(stats?.max || 0)} />
         <MiniStat label="Min" value={formatDT(stats?.min || 0)} />
      </div>

      <div style={{ width: '100%', height: 350 }}>
          <ResponsiveContainer width="100%" height={280} minWidth={0} debounce={50}>
          <AreaChart key={`${granularite}-${service}`} data={data}>
            <defs>
              {colors.map((c, i) => (
                <linearGradient key={i} id={`color-${i}`} x1="0" y1="0" x2="0" y2="1">
                  <stop offset="5%" stopColor={c} stopOpacity={0.3}/>
                  <stop offset="95%" stopColor={c} stopOpacity={0}/>
                </linearGradient>
              ))}
            </defs>
            <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="var(--border)" />
            <XAxis dataKey="label" tick={{ fontSize: 11, fill: 'var(--text-muted)' }} />
            <YAxis tick={{ fontSize: 11, fill: 'var(--text-muted)' }} tickFormatter={formatDT} />
            <Tooltip 
               content={({ active, payload, label }) => {
                if (active && payload && payload.length) {
                  const d = payload[0].payload;
                  return (
                    <div style={{ background: '#0f172a', color: '#f1f5f9', borderRadius: '8px', padding: '10px 14px', fontSize: '12px', border: d.is_outlier ? '1px solid #f59e0b' : '1px solid #1e293b' }}>
                      <div style={{ fontWeight: 700, marginBottom: '4px' }}>📅 {label}</div>
                      {d.is_outlier && <div style={{ color: '#fbbf24', fontSize: '11px', marginBottom: '4px' }}>⚠ Valeur exceptionnelle (z-score = {d.z_score})</div>}
                      {payload.map((p, i) => (
                        <div key={i} style={{ color: p.color, display: 'flex', justifyContent: 'space-between', gap: '15px' }}>
                          <span>{p.name}:</span>
                          <span style={{ fontWeight: 700 }}>{formatDT(p.value)}</span>
                        </div>
                      ))}
                      {d.is_outlier && <div style={{ color: '#94a3b8', fontSize: '11px', marginTop: '4px', borderTop: '1px solid #1e293b', paddingTop: '4px' }}>Plafonné à : {formatDT(d.valeur_capped)}</div>}
                    </div>
                  );
                }
                return null;
              }}
            />
            <Legend />
            
            {service === 'all' ? (
              Object.keys(data[0] || {}).filter(k => !['date', 'label', 'total', 'is_outlier', 'z_score', 'valeur_capped'].includes(k)).map((k, i) => (
                <Area 
                  key={k} 
                  type="monotone" 
                  dataKey={k === 'total' ? 'valeur_capped' : k} 
                  name={k === 'total' ? 'Revenus total' : k}
                  stackId="1" 
                  stroke={colors[i % colors.length]} 
                  fill={`url(#color-${i % colors.length})`} 
                />
              ))
            ) : (
              <Area 
                type="monotone" 
                dataKey="valeur_capped" 
                name="Revenus"
                stroke="#6366f1" 
                fill="url(#color-0)" 
                strokeWidth={3}
                dot={(props) => {
                  const { cx, cy, payload } = props;
                  if (payload.is_outlier) {
                    return <circle cx={cx} cy={cy} r={6} fill="#f59e0b" stroke="#fff" strokeWidth={2} />;
                  }
                  return <circle cx={cx} cy={cy} r={4} fill="#6366f1" />;
                }}
                activeDot={{ r: 6 }}
              />
            )}
            <Brush dataKey="label" height={30} stroke="var(--border)" fill="var(--bg-surface)" />
          </AreaChart>

        </ResponsiveContainer>
      </div>
    </div>
  );
};

const TopServices = ({ startDate, endDate }) => {
  const [data, setData] = useState([]);
  const [orderBy, setOrderBy] = useState('revenus');
  const [limit, setLimit] = useState(5);

  const fetchData = async () => {
    try {
      const res = await api.get(`/dashboard/top-services-enrichi?start_date=${startDate}&end_date=${endDate}&limit=${limit}&order_by=${orderBy}`);
      setData(res.data);
    } catch (err) {
      console.error(err);
    }
  };

  useEffect(() => {
    fetchData();
  }, [startDate, endDate, orderBy, limit]);

  return (
    <div className="surface surface-pad" style={{ background: 'var(--bg-elevated)', border: '1px solid var(--border)' }}>
      <CardHeader title="Meilleurs services">
        <select className="select-sm" value={limit} onChange={e => setLimit(e.target.value)} style={{ background: 'var(--bg-surface)', color: 'var(--text-main)', border: '1px solid var(--border)', borderRadius: '6px', padding: '0 4px' }}>
          <option value={3}>Top 3</option>
          <option value={5}>Top 5</option>
          <option value={10}>Top 10</option>
        </select>
        <select className="select-sm" value={orderBy} onChange={e => setOrderBy(e.target.value)} style={{ background: 'var(--bg-surface)', color: 'var(--text-main)', border: '1px solid var(--border)', borderRadius: '6px', padding: '0 4px' }}>
          <option value="revenus">Par revenus DT</option>
          <option value="nb_cdr">Par nb CDR</option>
          <option value="nb_abonnes">Par abonnés</option>
        </select>
      </CardHeader>

      <div style={{ display: 'flex', flexDirection: 'column', gap: '12px', marginTop: '12px' }}>
        {data.map((s, i) => {
          const max = data[0]?.[orderBy] || 1;
          const pct = ((s[orderBy] / max) * 100).toFixed(0);
          const medals = ['🥇', '🥈', '🥉'];
          
          return (
            <div key={s.keyword} className="surface" style={{ padding: '16px', borderRadius: '10px', border: '1px solid var(--border)', background: 'var(--bg-surface)' }}>
              <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '8px' }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                  <span style={{ fontSize: '1.2rem' }}>{i < 3 ? medals[i] : `#${s.rank}`}</span>
                  <div>
                    <div style={{ fontWeight: 700, fontSize: '14px', color: 'var(--text-main)' }}>{s.nom} <span style={{ fontWeight: 400, color: 'var(--text-muted)' }}>({s.keyword})</span></div>
                    <div style={{ fontSize: '12px', color: 'var(--text-muted)' }}>
                      {formatDT(s.revenus)} · {formatCompactNumber(s.nb_cdr)} CDR · {formatCompactNumber(s.nb_abonnes)} Abonnés
                    </div>
                  </div>
                </div>
                <div style={{ textAlign: 'right' }}>
                   <div style={{ fontSize: '14px', fontWeight: 700, color: s.is_up ? '#10b981' : '#ef4444' }}>
                      {s.is_up ? '↑' : '↓'} {Math.abs(s.variation)}%
                   </div>
                   <div style={{ fontSize: '10px', color: 'var(--text-muted)', textTransform: 'uppercase' }}>vs période préc.</div>
                </div>
              </div>
              
              <div style={{ display: 'flex', alignItems: 'center', gap: '10px' }}>
                <div style={{ flex: 1, height: '8px', background: 'var(--border)', borderRadius: '4px', overflow: 'hidden' }}>
                  <div style={{ width: `${pct}%`, height: '100%', background: i === 0 ? 'var(--primary)' : 'var(--text-muted)', transition: 'width 0.5s cubic-bezier(0.4, 0, 0.2, 1)' }} />
                </div>
                <span style={{ fontSize: '12px', fontWeight: 600, color: 'var(--text-muted)', minWidth: '35px' }}>{pct}%</span>
              </div>
            </div>
          );
        })}
      </div>
    </div>
  );
};

const MmgSuccessCard = ({ startDate, endDate }) => {
  const [data, setData] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    setLoading(true);
    api.get(`/dashboard/mmg-success-rate?start_date=${startDate}&end_date=${endDate}`)
      .then(res => setData(res.data))
      .catch(console.error)
      .finally(() => setLoading(false));
  }, [startDate, endDate]);

  const stats = useMemo(() => {
    const success = data.find(d => d.event_status === 'Success')?.nb || 0;
    const total = data.reduce((acc, d) => acc + d.nb, 0);
    const rate = total > 0 ? (success / total * 100).toFixed(1) : 100;
    return { success, total, rate };
  }, [data]);

  return (
    <div className="glass-card surface-pad">
      <h3 style={{ margin: 0, fontSize: '0.9rem', color: 'var(--text-muted)', textTransform: 'uppercase', letterSpacing: '0.05em' }}>Taux de succès MMG</h3>
      <div style={{ display: 'flex', alignItems: 'baseline', gap: '10px', margin: '15px 0' }}>
        <span style={{ fontSize: '2.5rem', fontWeight: 800, color: 'var(--text-main)' }}>{stats.rate}%</span>
        <span style={{ color: 'var(--success)', fontWeight: 600 }}>{stats.success} OK</span>
      </div>
      <div style={{ height: '6px', background: 'var(--border)', borderRadius: '3px', overflow: 'hidden' }}>
        <div style={{ width: `${stats.rate}%`, height: '100%', background: 'var(--success)' }} />
      </div>
      <p style={{ margin: '10px 0 0', fontSize: '0.8rem', color: 'var(--text-muted)' }}>
        Sur un total de {formatCompactNumber(stats.total)} CDR MMG traités.
      </p>
    </div>
  );
};

const SubscriberDistribution = ({ startDate, endDate }) => {
  const [data, setData] = useState([]);
  const COLORS = ['#6366f1', '#ec4899', '#f59e0b', '#10b981'];

  useEffect(() => {
    api.get(`/dashboard/repartition-abonnes?start_date=${startDate}&end_date=${endDate}`)
      .then(res => setData(res.data))
      .catch(console.error);
  }, [startDate, endDate]);

  return (
    <div className="glass-card surface-pad">
      <CardHeader title="Répartition par offre" subtitle="PREPAID vs HYBRID" />
      <div style={{ height: '220px', minHeight: '220px', display: 'flex', alignItems: 'center' }}>
          <ResponsiveContainer width="100%" height={280} minWidth={0} debounce={50}>
          <PieChart>
            <Pie
              data={data}
              innerRadius={60}
              outerRadius={80}
              paddingAngle={5}
              dataKey="nb_abonnes"
              nameKey="subscriber_type"
            >
              {data.map((entry, index) => (
                <PieCell key={`cell-${index}`} fill={COLORS[index % COLORS.length]} />
              ))}
            </Pie>
            <Tooltip 
              contentStyle={{ background: 'var(--bg-elevated)', border: '1px solid var(--border)', borderRadius: '8px', color: 'var(--text-main)' }} 
              itemStyle={{ fontSize: '13px' }}
            />
            <Legend verticalAlign="bottom" align="center" />
          </PieChart>
        </ResponsiveContainer>
      </div>
      <div style={{ marginTop: '10px' }}>
         {data.map((d, i) => (
           <div key={d.subscriber_type} style={{ display: 'flex', justifyContent: 'space-between', fontSize: '0.85rem', marginBottom: '4px' }}>
              <span style={{ color: 'var(--text-muted)' }}>{d.subscriber_type}</span>
              <span style={{ fontWeight: 600 }}>{formatDT(d.revenus)}</span>
           </div>
         ))}
      </div>
    </div>
  );
};

const EtlHealthSection = () => {
  const [health, setHealth] = useState(null);

  useEffect(() => {
    api.get('/dashboard/etl-health').then(res => setHealth(res.data));
  }, []);

  if (!health) return null;

  return (
    <div className="surface surface-pad" style={{ background: 'var(--bg-elevated)', border: '1px solid var(--border)' }}>
      <CardHeader title="Santé de l'ETL" subtitle="Performance des flux aujourd'hui" />
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: '15px', marginTop: '10px' }}>
        <div style={{ textAlign: 'center' }}>
           <div style={{ fontSize: '1.2rem', fontWeight: 800, color: (health.taux_succes_today > 95) ? 'var(--success)' : 'var(--warning)' }}>{health.taux_succes_today}%</div>
           <div style={{ fontSize: '0.7rem', color: 'var(--text-muted)', textTransform: 'uppercase' }}>Succès</div>
        </div>
        <div style={{ textAlign: 'center' }}>
           <div style={{ fontSize: '1.2rem', fontWeight: 800, color: (health.jobs_erreur_today > 0) ? 'var(--danger)' : 'var(--success)' }}>{health.jobs_erreur_today}</div>
           <div style={{ fontSize: '0.7rem', color: 'var(--text-muted)', textTransform: 'uppercase' }}>Erreurs</div>
        </div>
        <div style={{ textAlign: 'center' }}>
           <div style={{ fontSize: '1.2rem', fontWeight: 800, color: 'var(--text-main)' }}>{health.total_today}</div>
           <div style={{ fontSize: '0.7rem', color: 'var(--text-muted)', textTransform: 'uppercase' }}>Jobs total</div>
        </div>
      </div>
      <div style={{ marginTop: '15px', fontSize: '0.75rem', color: 'var(--text-muted)', borderTop: '1px solid var(--border)', paddingTop: '8px' }}>
         Dernier import réussi : <span style={{ fontWeight: 600 }}>{health.dernier_import ? format(parseISO(health.dernier_import), 'HH:mm') : 'N/A'}</span>
      </div>
    </div>
  );
};

const HourlyActivityChart = ({ startDate, endDate }) => {
  const [data, setData] = useState([]);

  useEffect(() => {
    api.get(`/dashboard/revenus?date=${endDate}&granularity=hour`)
      .then(res => {
        // Aggregate by hour
        const hourly = Array.from({ length: 24 }, (_, i) => ({ hour: i, nb: 0 }));
        res.data.forEach(d => {
          if (d.hour !== undefined) {
             const h = parseInt(d.hour);
             hourly[h].nb += d.nb_cdr || 0;
          }
        });
        setData(hourly);
      });
  }, [startDate, endDate]);

  return (
    <div className="surface surface-pad" style={{ background: 'var(--bg-elevated)', border: '1px solid var(--border)' }}>
      <CardHeader title="Activité par heure" subtitle="Volume CDR moyen par heure de la journée" />
      <div style={{ height: '200px', minHeight: '200px' }}>
          <ResponsiveContainer width="100%" height={280} minWidth={0} debounce={50}>
          <BarChart data={data}>
            <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="var(--border)" />
            <XAxis dataKey="hour" tick={{ fontSize: 10, fill: 'var(--text-muted)' }} />
            <YAxis hide />
            <Tooltip 
              labelFormatter={(h) => `${h}h - ${h+1}h`} 
              contentStyle={{ background: 'var(--bg-elevated)', border: '1px solid var(--border)', borderRadius: '8px', color: 'var(--text-main)' }} 
              itemStyle={{ fontSize: '13px' }}
            />
            <Bar dataKey="nb" fill="#6366f1" radius={[2, 2, 0, 0]} />
          </BarChart>
        </ResponsiveContainer>
      </div>
    </div>
  );
};

const BillingIntegrity = ({ startDate, endDate }) => {
  const [data, setData] = useState([]);

  useEffect(() => {
    api.get(`/dashboard/billing-integrity?start_date=${startDate}&end_date=${endDate}`)
      .then(res => setData(res.data))
      .catch(console.error);
  }, [startDate, endDate]);

  return (
    <div className="surface surface-pad" style={{ background: 'var(--bg-elevated)', border: '1px solid var(--border)' }}>
      <CardHeader title="Intégrité Tarifaire" subtitle="Écart entre revenu réel et théorique (Prix Catalogue)" />
      <div className="table-wrap" style={{ marginTop: '10px' }}>
         <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: '0.85rem' }}>
            <thead>
               <tr style={{ borderBottom: '1px solid var(--border)', color: 'var(--text-muted)', textAlign: 'left' }}>
                  <th style={{ padding: '8px 4px' }}>Service</th>
                  <th style={{ padding: '8px 4px', textAlign: 'right' }}>Réel</th>
                  <th style={{ padding: '8px 4px', textAlign: 'right' }}>Écart</th>
                  <th style={{ padding: '8px 4px', textAlign: 'right' }}>%</th>
               </tr>
            </thead>
            <tbody>
               {data.map((d, i) => (
                 <tr key={d.keyword} style={{ borderBottom: i < data.length - 1 ? '1px solid var(--border)' : 'none' }}>
                    <td style={{ padding: '8px 4px' }}>
                       <div style={{ fontWeight: 600 }}>{d.nom_service}</div>
                       <div style={{ fontSize: '10px', color: 'var(--text-muted)' }}>{d.nb_cdr} CDR · {d.prix_theorique} DT</div>
                    </td>
                    <td style={{ padding: '8px 4px', textAlign: 'right', fontWeight: 600 }}>{formatDT(Number(d.total_reel))}</td>
                    <td style={{ padding: '8px 4px', textAlign: 'right', color: Number(d.ecart_total) < 0 ? '#ef4444' : '#10b981' }}>
                       {Number(d.ecart_total) > 0 ? '+' : ''}{formatDT(Number(d.ecart_total))}
                    </td>
                     <td style={{ padding: '8px 4px', textAlign: 'right' }}>
                       <span style={{ 
                         background: Math.abs(Number(d.ecart_pct)) > 5 ? (Number(d.ecart_pct) < 0 ? 'rgba(220, 38, 38, 0.15)' : 'rgba(22, 163, 74, 0.15)') : 'transparent',
                         color: Math.abs(Number(d.ecart_pct)) > 5 ? (Number(d.ecart_pct) < 0 ? 'var(--danger)' : 'var(--success)') : 'var(--text-muted)',
                         padding: '2px 4px',
                         borderRadius: '4px',
                         fontWeight: 600
                       }}>
                          {Number(d.ecart_pct).toFixed(1)}%
                       </span>
                    </td>
                 </tr>
               ))}
               {data.length === 0 && (
                 <tr>
                    <td colSpan="4" style={{ textAlign: 'center', padding: '20px', color: '#94a3b8' }}>Aucune anomalie détectée</td>
                 </tr>
               )}
            </tbody>
         </table>
      </div>
    </div>
  );
};

const RoamingDistribution = ({ startDate, endDate }) => {
  const [data, setData] = useState([]);
  const COLORS = ['#10b981', '#6366f1'];

  useEffect(() => {
    api.get(`/dashboard/repartition-roaming?start_date=${startDate}&end_date=${endDate}`)
      .then(res => setData(res.data))
      .catch(console.error);
  }, [startDate, endDate]);

  return (
    <div className="surface surface-pad" style={{ background: 'var(--bg-elevated)', border: '1px solid var(--border)' }}>
      <CardHeader title="Local vs Roaming" subtitle="Volume CDR par type de connexion" />
      <div style={{ height: '220px', minHeight: '220px' }}>
          <ResponsiveContainer width="100%" height={280} minWidth={0} debounce={50}>
          <PieChart>
            <Pie
              data={data}
              innerRadius={0}
              outerRadius={80}
              dataKey="nb"
              nameKey="type"
            >
              {data.map((entry, index) => (
                <PieCell key={`cell-${index}`} fill={COLORS[index % COLORS.length]} />
              ))}
            </Pie>
            <Tooltip 
              contentStyle={{ background: 'var(--bg-elevated)', border: '1px solid var(--border)', borderRadius: '8px', color: 'var(--text-main)' }} 
              itemStyle={{ fontSize: '13px' }}
            />
            <Legend />
          </PieChart>
        </ResponsiveContainer>
      </div>
    </div>
  );
};

// --- Main Page ---

const ComparisonChart = ({ startDate, endDate }) => {
  const [data, setData] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const fetchData = async () => {
      setLoading(true);
      try {
        // Calculer la période précédente
        const start = new Date(startDate);
        const end = new Date(endDate);
        const diff = differenceInDays(end, start) + 1;
        
        const prevEnd = format(subDays(start, 1), 'yyyy-MM-dd');
        const prevStart = format(subDays(start, diff), 'yyyy-MM-dd');

        const [resCurr, resPrev] = await Promise.all([
          api.get(`/dashboard/revenus?date=${endDate}&days=${diff}`),
          api.get(`/dashboard/revenus?date=${prevEnd}&days=${diff}`)
        ]);

        // Aligner les données par "index" de jour (J-0, J-1...)
        const combined = [];
        const currData = resCurr.data.reverse();
        const prevData = resPrev.data.reverse();
        
        const maxLen = Math.max(currData.length, prevData.length);
        for(let i=0; i < maxLen; i++) {
          combined.push({
            index: i,
            label: currData[i]?.start_date ? format(parseISO(currData[i].start_date), 'dd/MM') : `J-${maxLen-i}`,
            actuelle: currData[i]?.total || 0,
            precedente: prevData[i]?.total || 0
          });
        }
        setData(combined);
      } catch (err) {
        console.error(err);
      } finally {
        setLoading(false);
      }
    };
    fetchData();
  }, [startDate, endDate]);

  return (
    <div className="surface surface-pad" style={{ marginBottom: '24px', border: '1px solid var(--primary)', background: 'var(--bg-surface)' }}>
       <CardHeader title="⇄ Comparaison de tendances" subtitle="Superposition de la période actuelle (Bleu) vs précédente (Gris)" />
       <div style={{ height: '300px', width: '100%' }}>
            <ResponsiveContainer width="100%" height={300} minWidth={0} debounce={50}>
            <AreaChart data={data}>
              <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="var(--border)" />
              <XAxis dataKey="label" tick={{ fontSize: 11, fill: 'var(--text-muted)' }} />
              <YAxis tick={{ fontSize: 11, fill: 'var(--text-muted)' }} tickFormatter={formatDT} />
              <Tooltip 
                contentStyle={{ background: 'var(--bg-elevated)', border: '1px solid var(--border)', borderRadius: '8px', color: 'var(--text-main)' }} 
              />
              <Legend />
              <Area type="monotone" dataKey="actuelle" stroke="var(--primary)" fill="var(--primary)" fillOpacity={0.1} strokeWidth={3} />
              <Area type="monotone" dataKey="precedente" stroke="#94a3b8" fill="#94a3b8" fillOpacity={0.05} strokeWidth={2} strokeDasharray="5 5" />
            </AreaChart>
          </ResponsiveContainer>
       </div>
    </div>
  );
};

export default function Dashboard({ user }) {
  const { periode, setPreset, setCustom, setPeriode } = usePeriode();
  const [compare, setCompare] = useState(false);



  useEffect(() => {
    const checkRange = async () => {
      try {
        const res = await api.get('/dashboard/range');
        const maxDate = res.data.max_date;
        if (maxDate && (new Date(periode.startDate) > new Date(maxDate))) {
           const start = format(subDays(parseISO(maxDate), 7), 'yyyy-MM-dd');
           setPeriode({
             preset: 'custom',
             startDate: start,
             endDate: maxDate,
             label: `Dernières données disponibles (${format(parseISO(maxDate), 'dd/MM/yyyy')})`
           });
        }
      } catch (err) { console.error(err); }
    };
    checkRange();
  }, [periode]);


  return (
    <div className="page" style={{ padding: '1rem', backgroundColor: 'var(--bg-page)', minHeight: '100vh' }}>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '24px' }}>
        <div>
          <h1 style={{ margin: 0, fontSize: '1.25rem', fontWeight: 800, color: 'var(--text-main)' }}>Tableau de bord SMS+ VAS</h1>
          <p style={{ margin: '4px 0 0', color: '#64748b' }}>Analyse complète de l'assurance revenus et détection de fraude</p>
        </div>
        <button 
          className={`btn ${compare ? 'btn-primary' : 'btn-soft'}`} 
          onClick={() => setCompare(!compare)}
          style={{ height: '40px', fontWeight: 600 }}
        >
          {compare ? '✓ Comparaison Active' : '⇄ Comparer deux périodes'}
        </button>
      </div>

      <GlobalPeriodControls 
        periode={periode} 
        setPreset={setPreset} 
        setCustom={setCustom} 
      />


      {compare && <ComparisonChart startDate={periode.startDate} endDate={periode.endDate} />}

      <div className="dashboard-grid" style={{ display: 'grid', gridTemplateColumns: '1fr', gap: '1rem' }}>
        
        <TrafficChart 
          startDate={periode.startDate} 
          endDate={periode.endDate} 
          label={periode.label} 
        />

        <div style={{ display: 'grid', gridTemplateColumns: '2fr 1fr', gap: '1rem' }}>
          <RevenusChart 
            startDate={periode.startDate} 
            endDate={periode.endDate} 
          />
          <TopServices 
            startDate={periode.startDate} 
            endDate={periode.endDate} 
          />
        </div>

        <div style={{ display: 'grid', gridTemplateColumns: '2fr 1fr', gap: '1rem', marginBottom: '1rem' }}>
           <BillingIntegrity startDate={periode.startDate} endDate={periode.endDate} />
           <EtlHealthSection />
        </div>

        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr 1fr', gap: '1rem' }}>
           <MmgSuccessCard startDate={periode.startDate} endDate={periode.endDate} />
           <SubscriberDistribution startDate={periode.startDate} endDate={periode.endDate} />
           <RoamingDistribution startDate={periode.startDate} endDate={periode.endDate} />
           <HourlyActivityChart startDate={periode.startDate} endDate={periode.endDate} />
        </div>

      </div>

      <div style={{ marginTop: '32px' }}>
        <JobStatusBar
          jobTypes={['import_occ_csv', 'import_mmg_csv', 'etl_cdr_from_tmp', 'etl_agg_from_raw', 'dashboard_stats_load', 'dashboard_revenus_chart', 'notifications_load', 'notifications_polling']}
          title="Traitements système aujourd'hui"
          compact={false}
          refreshInterval={15000}
        />
      </div>

      <style>{`
        .dashboard-grid { margin-top: 24px; }
        .select-sm { 
          background: #f8fafc; 
          border: 1px solid #e2e8f0; 
          border-radius: 6px; 
          padding: 0 8px; 
          height: 32px; 
          font-size: 13px; 
          outline: none; 
          cursor: pointer;
        }
        .select-sm:hover { border-color: #cbd5e1; }
        .select-sm:focus { border-color: #6366f1; }
        
        .dropdown { position: relative; display: inline-block; }
        .dropdown-content {
          display: none;
          position: absolute;
          right: 0;
          background-color: #ffffff;
          min-width: 160px;
          box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.1);
          z-index: 100;
          border-radius: 8px;
          border: 1px solid #e2e8f0;
          margin-top: 4px;
        }
        .dropdown:hover .dropdown-content { display: block; }
        
        .btn-soft { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }
        .btn-soft:hover { background: #e2e8f0; }
        
        .btn-primary { background: #1e293b; color: white; border: none; }
        .btn-primary:hover { background: #0f172a; }
      `}</style>
    </div>
  );
}