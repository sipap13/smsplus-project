 
import { useEffect, useState, useMemo, useRef } from 'react';
import api from '../api/axios';
import {
  AreaChart, Area, XAxis, YAxis, Tooltip,
  ResponsiveContainer, CartesianGrid,
  BarChart, Bar, Legend, Brush, ReferenceLine,
  ComposedChart, Line, Cell
} from 'recharts';
import { formatCompactNumber, formatDT } from '../lib/format';
import { usePeriode } from '../hooks/usePeriode';
import useLocalState from '../hooks/useLocalState';
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
  const [granularity, setGranularity] = useLocalState('trafic_granularity', 'week');
  const [options, setOptions] = useLocalState('trafic_options', { mmg: true, occ: true, ecart: false, moyenne: true });

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

  const comparisonFactor = useMemo(() => {
    if (!normalizedData.length) return 1;
    const totalMmg = normalizedData.reduce((acc, d) => acc + (Number(d.mmg) || 0), 0);
    const totalOcc = normalizedData.reduce((acc, d) => acc + (Number(d.occ) || 0), 0);
    return totalMmg > 0 ? totalOcc / totalMmg : 1;
  }, [normalizedData]);

  const comparisonData = useMemo(() => {
    return normalizedData.map(d => {
      const mmgAdjusted = (Number(d.mmg) || 0) * comparisonFactor;
      const ecartAdjustedPct = d.occ > 0 ? Math.abs(mmgAdjusted - d.occ) / d.occ * 100 : 0;
      return {
        ...d,
        mmg_adjusted: mmgAdjusted,
        ecart_adjusted_pct: ecartAdjustedPct,
      };
    });
  }, [normalizedData, comparisonFactor]);

  const stats = useMemo(() => {
    if (!comparisonData.length) return null;
    const totalMmg = comparisonData.reduce((acc, d) => acc + d.mmg_adjusted, 0);
    const totalOcc = comparisonData.reduce((acc, d) => acc + d.occ, 0);
    const avgEcart = comparisonData.reduce((acc, d) => acc + d.ecart_adjusted_pct, 0) / comparisonData.length;
    const pic = [...comparisonData].sort((a, b) => b.occ - a.occ)[0];
    const meanOcc = totalOcc / comparisonData.length;
    return { totalMmg, totalOcc, avgEcart, pic, meanOcc };
  }, [comparisonData]);

  const CustomTooltip = ({ active, payload, label }) => {
    if (!active || !payload || !payload.length) return null;

    const payloadByName = payload.reduce((acc, item) => {
      acc[item.name] = item;
      return acc;
    }, {});

    const mmgItem = payloadByName['MMG'];
    const occItem = payloadByName['OCC'];
    const mmgValue = mmgItem?.value ?? null;
    const occValue = occItem?.value ?? null;
    const ecartPct = mmgValue !== null && occValue !== null && occValue > 0
      ? Math.abs(mmgValue - occValue) / occValue * 100
      : null;

    const sourceRow = payload[0]?.payload || {};

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
        <div style={{ fontWeight: 700, marginBottom: '6px' }}>📅 {sourceRow.full_label || label || sourceRow.label}</div>
        {payload
          .filter(item => item?.name)
          .map(item => {
            // override the dark bar color so text is visible in the dark tooltip
            const textColor = item.name === 'MMG' ? '#60a5fa' : item.color;
            return (
              <div key={item.name} style={{ display: 'flex', justifyContent: 'space-between', gap: '20px' }}>
                <span style={{ color: '#94a3b8' }}>{item.name} :</span>
                <span style={{ fontWeight: 700, color: textColor }}>{formatCompactNumber(item.value)} CDR</span>
              </div>
            );
          })}
        {ecartPct !== null && (
          <div style={{ display: 'flex', justifyContent: 'space-between', gap: '20px', borderTop: '1px solid #1e293b', paddingTop: '4px', marginTop: '4px' }}>
            <span style={{ color: '#94a3b8' }}>Écart :</span>
            <span style={{ fontWeight: 700, color: ecartPct > 5 ? '#ef4444' : '#10b981' }}>
              {ecartPct > 5 ? '⚠' : '✓'} {ecartPct.toFixed(1)}%
            </span>
          </div>
        )}
      </div>
    );
  };


  return (
    <div className="glass-card surface-pad" style={{ marginBottom: '1.2rem', position: 'relative' }}>
      <CardHeader title="Trafic MMG vs OCC" subtitle={`Volume CDR · ${label} · MMG ajusté x${comparisonFactor.toFixed(2)}`}>
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
          <ComposedChart key={granularity} data={comparisonData}>
            <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="var(--border)" />
            <XAxis dataKey="label" tick={{ fontSize: 10 }} minTickGap={30} interval="preserveStartEnd" />
            <YAxis tick={{ fontSize: 11 }} tickFormatter={formatCompactNumber} />
            <Tooltip content={<CustomTooltip />} />
            <Legend verticalAlign="top" align="right" height={36} />
            
            {options.mmg && <Bar dataKey="mmg_adjusted" name="MMG" fill="#0f2744" radius={[4, 4, 0, 0]} maxBarSize={30} />}
            {options.occ && (
              <Bar dataKey="occ" name="OCC" fill="#3b6fa0" radius={[4, 4, 0, 0]} maxBarSize={30} />
            )}

            
            {options.ecart && <Line type="monotone" dataKey="ecart_pct" name="Écart %" stroke="#5ba3d9" strokeWidth={2} dot={{ r: 4 }} />}
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
  const [service, setService] = useLocalState('revenus_service_v2', 'all');
  const [fournisseur, setFournisseur] = useLocalState('revenus_fournisseur_v2', '');
  const [granularite, setGranularity] = useLocalState('revenus_granularite_v2', 'week');
  const [showAllServices, setShowAllServices] = useLocalState('revenus_showAllServices', false);
  
  const { services: mappedServices } = useServiceMapping();

  const fournisseurs = useMemo(() => {
    return [...new Set(mappedServices.map(s => s.nom_fournisseur).filter(Boolean))].sort();
  }, [mappedServices]);

  useEffect(() => {
    if (fournisseurs && fournisseurs.length) {
      // If saved fournisseur is not empty but not present in the current list, default to empty
      if (fournisseur && !fournisseurs.includes(fournisseur)) {
        setFournisseur('');
      }
    }
  }, [fournisseurs, fournisseur]);

  const filteredServices = useMemo(() => {
    return fournisseur ? mappedServices.filter(s => s.nom_fournisseur === fournisseur) : mappedServices;
  }, [mappedServices, fournisseur]);

  const fetchData = async () => {
    setLoading(true);
    try {
      let url = `/dashboard/revenus-par-service?start_date=${startDate}&end_date=${endDate}&granularite=${granularite}`;
      if (service !== 'all') url += `&nom_service=${encodeURIComponent(service)}`;
      if (fournisseur) url += `&fournisseur=${encodeURIComponent(fournisseur)}`;
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
  }, [startDate, endDate, service, fournisseur, granularite]);

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
  const colors = ['#0f2744', '#1e3a5f', '#2a5082', '#3b6fa0', '#4a8ec2', '#5ba3d9', '#7ab8e0'];

  // compute visible service keys (top-N) when showing aggregated multiple services
  const { availableServiceKeys, visibleServiceKeys } = useMemo(() => {
    if (!data || !data.length) return { availableServiceKeys: [], visibleServiceKeys: [] };
    const excluded = ['date', 'label', 'full_label', 'total', 'is_outlier', 'z_score', 'valeur_capped'];
    const available = [...new Set(data.flatMap(d => Object.keys(d)))].filter(k => !excluded.includes(k));
    const totals = available.map(k => ({ k, sum: data.reduce((s, r) => s + Number(r[k] || 0), 0) }));
    totals.sort((a, b) => b.sum - a.sum);
    const topN = 10;
    const visible = showAllServices ? available : totals.slice(0, topN).map(t => t.k);
    return { availableServiceKeys: available, visibleServiceKeys: visible };
  }, [data, showAllServices]);

  return (
    <div className="surface surface-pad" style={{ marginBottom: '1.2rem', background: 'var(--bg-elevated)', border: '1px solid var(--border)' }}>
      <CardHeader title="Revenus par service" subtitle={`Analyse des revenus (DT) · ${startDate} au ${endDate}`}>
        <select 
          className="select-sm" 
          value={fournisseur} 
          onChange={e => { setFournisseur(e.target.value); setService('all'); }}
          style={{ height: '32px', borderRadius: '6px', border: '1px solid var(--border)', padding: '0 8px', fontSize: '13px', background: 'var(--bg-surface)', color: 'var(--text-main)', marginRight: '8px' }}
        >
          <option value="">Tous les fournisseurs</option>
          {fournisseurs.map(f => <option key={f} value={f}>{f}</option>)}
        </select>
        <button
          type="button"
          className="btn btn-soft"
          onClick={() => setShowAllServices(s => !s)}
          style={{ height: '32px', padding: '0 8px', marginRight: '8px' }}
        >
          {showAllServices ? 'Top 10' : 'Afficher tous'}
        </button>
        <select 
          className="select-sm" 
          value={service} 
          onChange={e => setService(e.target.value)}
          style={{ height: '32px', borderRadius: '6px', border: '1px solid var(--border)', padding: '0 8px', fontSize: '13px', background: 'var(--bg-surface)', color: 'var(--text-main)', marginRight: '8px' }}
        >
          <option value="all">Tous les services</option>
          {(() => {
            const uniqueServices = [...new Map(filteredServices.map(s => [s.nom_service, s])).values()].map(s => ({
              ...s,
              has_traffic_global: filteredServices.some(fs => fs.nom_service === s.nom_service && fs.has_traffic)
            }));
            
            uniqueServices.sort((a, b) => {
              if (a.has_traffic_global === b.has_traffic_global) {
                return a.nom_service.localeCompare(b.nom_service);
              }
              return a.has_traffic_global ? -1 : 1;
            });

            return uniqueServices.map(s => (
              <option 
                key={s.nom_service} 
                value={s.nom_service}
                style={{ color: !s.has_traffic_global ? 'var(--danger)' : 'inherit' }}
              >
                {s.has_traffic_global ? s.nom_service : `🔴 ${s.nom_service} (vide)`}
              </option>
            ));
          })()}
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
          {data && data.length > 0 ? (
            <ResponsiveContainer width="100%" height={280} minWidth={0} debounce={50}>
              <BarChart key={`${granularite}-${service}`} data={data}>
                <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="var(--border)" />
                <XAxis dataKey="label" tick={{ fontSize: 10, fill: 'var(--text-muted)' }} minTickGap={30} interval="preserveStartEnd" />
                <YAxis tick={{ fontSize: 11, fill: 'var(--text-muted)' }} tickFormatter={formatDT} />
                <Tooltip 
                  content={({ active, payload, label }) => {
                    if (active && payload && payload.length) {
                      const d = payload[0].payload;
                      return (
                        <div style={{ background: '#0f172a', color: '#f1f5f9', borderRadius: '8px', padding: '10px 14px', fontSize: '12px', border: d.is_outlier ? '1px solid #f59e0b' : '1px solid #1e293b' }}>
                          <div style={{ fontWeight: 700, marginBottom: '4px' }}>📅 {d.full_label || label}</div>
                          {d.is_outlier && <div style={{ color: '#fbbf24', fontSize: '11px', marginBottom: '4px' }}>⚠ Valeur exceptionnelle (z-score = {d.z_score})</div>}
                          {payload.map((p, i) => (
                            <div key={i} style={{ display: 'flex', justifyContent: 'space-between', gap: '15px', alignItems: 'center', marginBottom: '2px' }}>
                              <div style={{ display: 'flex', alignItems: 'center', gap: '6px' }}>
                                <div style={{ width: 8, height: 8, borderRadius: 2, background: p.color }} />
                                <span style={{ color: '#94a3b8' }}>{p.name}:</span>
                              </div>
                              <span style={{ fontWeight: 700, color: '#f1f5f9' }}>{formatDT(p.value)}</span>
                            </div>
                          ))}
                          {d.is_outlier && <div style={{ color: '#94a3b8', fontSize: '11px', marginTop: '4px', borderTop: '1px solid #1e293b', paddingTop: '4px' }}>Plafonné à : {formatDT(d.valeur_capped)}</div>}
                        </div>
                      );
                    }
                    return null;
                  }}
                />
                <Legend wrapperStyle={{ fontSize: '12px' }} />
                
                {service === 'all' ? (
                  (visibleServiceKeys || []).map((k, i) => (
                    <Bar 
                      key={k} 
                      dataKey={k} 
                      name={k}
                      stackId="a" 
                      fill={colors[i % colors.length]} 
                      radius={i === (visibleServiceKeys || []).length - 1 ? [4, 4, 0, 0] : [0, 0, 0, 0]}
                    />
                  ))
                ) : (
                  <Bar 
                    dataKey="revenus" 
                    name="Revenus"
                    fill="#3b6fa0" 
                    radius={[4, 4, 0, 0]}
                    maxBarSize={50}
                  >
                    {data.map((entry, index) => (
                      <Cell key={`cell-${index}`} fill={entry.is_outlier ? '#f59e0b' : '#3b6fa0'} />
                    ))}
                  </Bar>
                )}
                <Brush dataKey="label" height={30} stroke="var(--border)" fill="var(--bg-surface)" />
              </BarChart>
            </ResponsiveContainer>
          ) : (
            <div style={{ height: '280px', display: 'flex', alignItems: 'center', justifyContent: 'center', color: 'var(--text-muted)' }}>
              {!loading && "Aucune donnée disponible pour les filtres sélectionnés."}
            </div>
          )}
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
            <div key={`${s.keyword}-${i}`} className="surface" style={{ padding: '16px', borderRadius: '10px', border: '1px solid var(--border)', background: 'var(--bg-surface)' }}>
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
                {/* Suppression de la comparaison vs période précédente */}
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
    const total = data.reduce((acc, d) => acc + (d.nb || 0), 0);
    const breakdown = data.reduce((acc, d) => {
      const key = (d.event_status || 'unknown').toString().trim().toLowerCase();
      const norm = (k => {
        if (k === 'success' || k === 'ok' || k === 'completed') return 'success';
        if (k === 'failed' || k === 'failure' || k === 'error' || k === 'failed_attempt') return 'failed';
        if (k === 'pending' || k === 'queued' || k === 'in_progress') return 'pending';
        return k;
      })(key);
      acc[norm] = (acc[norm] || 0) + (d.nb || 0);
      return acc;
    }, {});

    const success = breakdown.success || 0;
    const failed = breakdown.failed || 0;
    const pending = breakdown.pending || 0;
    const others = Object.keys(breakdown).filter(k => !['success','failed','pending'].includes(k)).reduce((s,k) => s + breakdown[k], 0);
    const rate = total > 0 ? Number(((success / total) * 100).toFixed(1)) : 0;
    return { total, success, failed, pending, others, breakdown, rate };
  }, [data]);

  // Colors for breakdown
  const COLORS = { success: '#10b981', failed: '#ef4444', pending: '#f59e0b', others: '#64748b' };

  return (
    <div className="glass-card surface-pad">
      <h3 style={{ margin: 0, fontSize: '0.9rem', color: 'var(--text-muted)', textTransform: 'uppercase', letterSpacing: '0.05em' }}>Taux de succès MMG</h3>

      <div style={{ display: 'flex', alignItems: 'baseline', gap: '10px', margin: '12px 0' }}>
        <span style={{ fontSize: '2.25rem', fontWeight: 800, color: 'var(--text-main)' }}>{stats.rate}%</span>
        <div style={{ display: 'flex', flexDirection: 'column' }}>
          <div style={{ fontSize: '0.9rem', fontWeight: 700 }}>{formatCompactNumber(stats.success)} OK</div>
          <div style={{ fontSize: '0.8rem', color: 'var(--text-muted)' }}>{formatCompactNumber(stats.total)} total</div>
        </div>
      </div>

      {/* Segmented bar */}
      <div style={{ height: '14px', background: 'var(--border)', borderRadius: '8px', overflow: 'hidden', display: 'flex', marginBottom: '10px' }}>
        {stats.total > 0 ? (
          <>
            <div style={{ width: `${(stats.success / stats.total) * 100}%`, background: COLORS.success }} />
            <div style={{ width: `${(stats.failed / stats.total) * 100}%`, background: COLORS.failed }} />
            <div style={{ width: `${(stats.pending / stats.total) * 100}%`, background: COLORS.pending }} />
            <div style={{ width: `${(stats.others / stats.total) * 100}%`, background: COLORS.others }} />
          </>
        ) : (
          <div style={{ width: '100%', background: 'var(--border)' }} />
        )}
      </div>

      {/* Breakdown list */}
      <div style={{ display: 'flex', gap: '12px', marginBottom: '8px' }}>
        {[['success','Succès', COLORS.success], ['failed','Échecs', COLORS.failed], ['pending','En attente', COLORS.pending], ['others','Autres', COLORS.others]].map(([k,label,color]) => {
          const value = stats[k] || 0;
          const pct = stats.total > 0 ? ((value / stats.total) * 100).toFixed(1) : '0.0';
          return (
            <div key={k} style={{ display: 'flex', flexDirection: 'column', alignItems: 'flex-start' }}>
              <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                <div style={{ width: 10, height: 10, background: color, borderRadius: 3 }} />
                <div style={{ fontSize: '0.85rem', color: 'var(--text-main)', fontWeight: 600 }}>{label}</div>
              </div>
              <div style={{ fontSize: '0.85rem', color: 'var(--text-muted)', marginTop: '4px' }}>{formatCompactNumber(value)} · {pct}%</div>
            </div>
          );
        })}
      </div>

      <p style={{ margin: '6px 0 0', fontSize: '0.8rem', color: 'var(--text-muted)' }}>
        Période: {startDate} → {endDate} · Données agrégées par statut MMG.
      </p>

    </div>
  );
};

const SubscriberDistribution = ({ startDate, endDate }) => {
  const [data, setData] = useState([]);
  const COLORS = ['#1e3a5f', '#3b6fa0', '#5ba3d9', '#7ab8e0'];

  useEffect(() => {
    api.get(`/dashboard/repartition-abonnes?start_date=${startDate}&end_date=${endDate}`)
      .then(res => setData(res.data))
      .catch(console.error);
  }, [startDate, endDate]);

  // Normalize and group subscriber_type, and remove zero-values to avoid rendering invalid pie sectors
  const displayData = useMemo(() => {
    if (!Array.isArray(data) || data.length === 0) return [];
    const mapKey = (k) => {
      if (!k) return 'UNKNOWN';
      const s = k.toString().trim().toLowerCase();
      if (s.includes('pre')) return 'PREPAID';
      if (s.includes('hyb') || s.includes('hybrid')) return 'HYB';
      if (s.includes('post')) return 'POSTPAID';
      return k.toString().toUpperCase();
    };
    const grouped = {};
    data.forEach(d => {
      const key = mapKey(d.subscriber_type);
      if (!grouped[key]) grouped[key] = { subscriber_type: key, nb_abonnes: 0, revenus: 0, nb_cdr: 0 };
      grouped[key].nb_abonnes += Number(d.nb_abonnes || d.nb || 0);
      grouped[key].revenus += Number(d.revenus || d.revenue || 0);
      grouped[key].nb_cdr += Number(d.nb_cdr || 0);
    });
    // filter out zero-values to avoid tiny/invalid sectors
    return Object.values(grouped).filter(x => (x.nb_abonnes || 0) > 0);
  }, [data]);

  const COLOR_MAP = { PREPAID: '#1e3a5f', HYB: '#f59e0b', POSTPAID: '#3b6fa0', UNKNOWN: '#94a3b8' };

  return (
    <div className="glass-card surface-pad">
      <CardHeader title="Répartition par offre" subtitle="PREPAID vs HYBRID" />
      <div style={{ height: '220px', minHeight: '220px', display: 'flex', alignItems: 'center' }}>
          <ResponsiveContainer width="100%" height={280} minWidth={0} debounce={50}>
          <PieChart>
            <Pie
              data={displayData}
              innerRadius={60}
              outerRadius={80}
              paddingAngle={5}
              dataKey="nb_abonnes"
              nameKey="subscriber_type"
              isAnimationActive={false}
            >
              {displayData.map((entry, index) => (
                <PieCell key={`cell-${entry.subscriber_type}-${index}`} fill={COLOR_MAP[entry.subscriber_type] || COLORS[index % COLORS.length]} />
              ))}
            </Pie>
            <Tooltip 
              formatter={(value, name) => {
                if (name === 'nb_abonnes') return [formatCompactNumber(value) + ' abonnés', 'Abonnés'];
                return [value, name];
              }}
              contentStyle={{ background: 'var(--bg-elevated)', border: '1px solid var(--border)', borderRadius: '8px', color: 'var(--text-main)' }} 
              itemStyle={{ fontSize: '13px' }}
            />
            <Legend verticalAlign="bottom" align="center" />
          </PieChart>
        </ResponsiveContainer>
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
            <Bar dataKey="nb" fill="#2a5082" radius={[2, 2, 0, 0]} />
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
                 <tr key={`${d.keyword}-${i}`} style={{ borderBottom: i < data.length - 1 ? '1px solid var(--border)' : 'none' }}>
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

// --- Main Page ---

export default function Dashboard({ user }) {
  const { periode, setPreset, setCustom, setPeriode } = usePeriode();
  


  // Check server max available date only once at mount to avoid
  // overriding user changes to the period after they interact.
  useEffect(() => {
    const checkRange = async () => {
      try {
        const res = await api.get('/dashboard/range');
        const maxDate = res.data.max_date;
        if (maxDate) {
          const maxDateObj = parseISO(maxDate);
          const desiredEnd = new Date(periode.endDate);
          const desiredStart = new Date(periode.startDate);

          if (desiredStart > maxDateObj || desiredEnd > maxDateObj) {
             const end = maxDate;
             const start = format(subDays(maxDateObj, 7), 'yyyy-MM-dd');
             setPeriode({
               preset: 'custom',
               startDate: start,
               endDate: end,
               label: `Dernières données disponibles (${format(maxDateObj, 'dd/MM/yyyy')})`
             });
          }
        }
      } catch (err) { console.error(err); }
    };
    checkRange();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);


  return (
    <div className="page" style={{ padding: '1rem', backgroundColor: 'var(--bg-page)', minHeight: '100vh' }}>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '24px' }}>
        <div>
          <h1 style={{ margin: 0, fontSize: '1.25rem', fontWeight: 800, color: 'var(--text-main)' }}>Tableau de bord SMS+ VAS</h1>
          <p style={{ margin: '4px 0 0', color: '#64748b' }}>Analyse complète de l'assurance revenus et détection de fraude</p>
        </div>
      </div>

      <GlobalPeriodControls 
        periode={periode} 
        setPreset={setPreset} 
        setCustom={setCustom} 
      />


      
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

        <div style={{ marginBottom: '1rem' }}>
           <BillingIntegrity startDate={periode.startDate} endDate={periode.endDate} />
        </div>

        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: '1rem' }}>
           <MmgSuccessCard startDate={periode.startDate} endDate={periode.endDate} />
           <SubscriberDistribution startDate={periode.startDate} endDate={periode.endDate} />
           <HourlyActivityChart startDate={periode.startDate} endDate={periode.endDate} />
        </div>

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
        .select-sm:focus { border-color: #2a5082; }
        
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