 
import { useMemo, useState } from 'react';
import { LineChart, Line, XAxis, YAxis, Tooltip, ResponsiveContainer, Area, AreaChart } from 'recharts';
import { formatDT } from '../lib/format';

const HEAT = [
  { min: 0, max: 0, bg: 'rgba(255, 255, 255, 0.05)', border: 'var(--border)' },
  { min: 1, max: 2, bg: '#c7d2fe', border: '#a5b4fc' },
  { min: 3, max: 5, bg: '#818cf8', border: '#6366f1' },
  { min: 6, max: 10, bg: '#4f46e5', border: '#4338ca' },
  { min: 11, max: Infinity, bg: '#312e81', border: '#1e1b4b' },
];

function heatColor(c) {
  for (const h of HEAT) if (c >= h.min && c <= h.max) return h;
  return HEAT[0];
}

function fmtDateFr(s) {
  return new Date(s + 'T00:00:00').toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
}

function fmtShort(s) {
  return new Date(s + 'T00:00:00').toLocaleDateString('fr-FR', { day: '2-digit', month: 'short' });
}

function fmtHour(h) {
  return String(h).padStart(2, '0') + 'h00';
}

function groupByDate(items) {
  const g = {};
  for (const it of items) { (g[it.date] ||= []).push(it); }
  return Object.entries(g).sort((a, b) => b[0].localeCompare(a[0]));
}

export default function MsisdnTimeline({ data }) {
  const [source, setSource] = useState('all');
  const [svcFilter, setSvcFilter] = useState(null);

  const safeData = data || {};
  const tl = safeData.timeline || [];
  const pj = safeData.par_jour || [];

  const filtered = useMemo(() => {
    let a = [...tl];
    if (source !== 'all') a = a.filter((it) => it.source.toLowerCase() === source);
    if (svcFilter) a = a.filter((it) => it.service === svcFilter);
    return a;
  }, [tl, source, svcFilter]);

  const grouped = useMemo(() => groupByDate(filtered), [filtered]);

  const heatDays = useMemo(() => {
    if (!safeData.periode) return [];
    const days = [];
    const map = Object.fromEntries(pj.map((d) => [d.date, d]));
    
    let current = new Date(safeData.periode.debut + 'T00:00:00Z');
    const end = new Date(safeData.periode.fin + 'T00:00:00Z');
    
    while (current <= end) {
      const iso = current.toISOString().slice(0, 10);
      days.push({ date: iso, ...(map[iso] || { nb_transactions: 0, montant_total: 0 }) });
      current.setUTCDate(current.getUTCDate() + 1);
    }
    
    return days;
  }, [pj, safeData]);

  const chartData = useMemo(() => [...pj].sort((a, b) => a.date.localeCompare(b.date)).map((d) => ({ date: fmtShort(d.date), montant: d.montant_total })), [pj]);

  const svcStats = useMemo(() => {
    const st = {};
    for (const it of tl) {
      if (!it.service) continue;
      if (!st[it.service]) st[it.service] = { service: it.service, count: 0, montant: 0 };
      st[it.service].count++; st[it.service].montant += it.montant;
    }
    return Object.values(st).sort((a, b) => b.count - a.count);
  }, [tl]);

  const totM = filtered.reduce((s, it) => s + it.montant, 0);

  if (!data) return null;

  // --- STYLES MODERNES (Premium & Glassmorphism) ---
  const containerStyle = {
    background: 'var(--bg-elevated)',
    borderRadius: '20px',
    border: '1px solid var(--border)',
    boxShadow: '0 10px 30px -10px rgba(0,0,0,0.05)',
    padding: '2rem',
    marginTop: '2rem',
    fontFamily: "'Inter', sans-serif"
  };

  const headerStyle = {
    display: 'flex', justifyContent: 'space-between', alignItems: 'center',
    marginBottom: '2rem', flexWrap: 'wrap', gap: '1rem'
  };

  const statCardStyle = {
    background: 'linear-gradient(135deg, rgba(99,102,241,0.05) 0%, rgba(99,102,241,0.01) 100%)',
    border: '1px solid rgba(99,102,241,0.15)',
    borderRadius: '16px', padding: '1rem 1.5rem',
    display: 'flex', flexDirection: 'column', gap: '4px',
    minWidth: '140px', flex: '1 1 0'
  };

  const btnGroupStyle = {
    display: 'flex', background: 'var(--bg-surface)', padding: '4px',
    borderRadius: '12px', border: '1px solid var(--border)'
  };

  const btnStyle = (isActive) => ({
    padding: '0.5rem 1.25rem', border: 'none', borderRadius: '8px',
    fontSize: '0.85rem', fontWeight: 600, cursor: 'pointer', transition: 'all 0.2s',
    background: isActive ? '#6366f1' : 'transparent',
    color: isActive ? '#fff' : 'var(--text-muted)',
    boxShadow: isActive ? '0 4px 12px rgba(99,102,241,0.3)' : 'none'
  });

  const timelineContainerStyle = {
    position: 'relative', marginTop: '2rem', paddingLeft: '24px',
    borderLeft: '2px solid var(--border)'
  };

  const dayCardStyle = {
    background: 'var(--bg-surface)', borderRadius: '16px',
    padding: '1.25rem', marginBottom: '1.5rem', position: 'relative',
    border: '1px solid var(--border)', transition: 'transform 0.2s, box-shadow 0.2s',
  };

  const dotIndicatorStyle = {
    position: 'absolute', left: '-31.5px', top: '24px',
    width: '14px', height: '14px', borderRadius: '50%',
    background: 'var(--bg-elevated)', border: '3px solid #6366f1',
    zIndex: 2
  };

  return (
    <div style={containerStyle}>
      {/* HEADER & FILTRES */}
      <div style={headerStyle}>
        <div>
          <h3 style={{ margin: '0 0 0.4rem', fontSize: '1.4rem', fontWeight: 800, color: 'var(--text-main)', display: 'flex', alignItems: 'center', gap: '8px' }}>
            Historique d'Activité
          </h3>
          <p style={{ margin: 0, fontSize: '0.85rem', color: 'var(--text-muted)', fontWeight: 500 }}>
            {data.periode ? `Du ${fmtDateFr(data.periode.debut)} au ${fmtDateFr(data.periode.fin)}` : 'Période indéterminée'}
          </p>
        </div>

        <div style={btnGroupStyle}>
          {['all', 'occ', 'mmg'].map((k) => (
            <button key={k} style={btnStyle(source === k)} onClick={() => setSource(k)}>
              {k === 'all' ? 'Tous les flux' : k.toUpperCase()}
            </button>
          ))}
        </div>
      </div>

      {/* QUICK STATS */}
      <div style={{ display: 'flex', gap: '1rem', flexWrap: 'wrap', marginBottom: '2rem' }}>
        <div style={statCardStyle}>
          <span style={{ fontSize: '0.75rem', fontWeight: 700, color: '#6366f1', textTransform: 'uppercase', letterSpacing: '0.05em' }}>Transactions</span>
          <div style={{ fontSize: '1.5rem', fontWeight: 800, color: 'var(--text-main)' }}>{data.total_transactions}</div>
        </div>
        <div style={statCardStyle}>
          <span style={{ fontSize: '0.75rem', fontWeight: 700, color: '#10b981', textTransform: 'uppercase', letterSpacing: '0.05em' }}>Revenus Totaux</span>
          <div style={{ fontSize: '1.5rem', fontWeight: 800, color: 'var(--text-main)' }}>{formatDT(totM)} <span style={{fontSize:'0.9rem'}}>DT</span></div>
        </div>
        <div style={statCardStyle}>
          <span style={{ fontSize: '0.75rem', fontWeight: 700, color: '#f59e0b', textTransform: 'uppercase', letterSpacing: '0.05em' }}>Services Utilisés</span>
          <div style={{ fontSize: '1.5rem', fontWeight: 800, color: 'var(--text-main)' }}>{svcStats.length}</div>
        </div>
        
        {data.timeline_shown < data.total_transactions && (
          <div style={{ ...statCardStyle, background: 'rgba(245,158,11,0.05)', border: '1px dashed #f59e0b', justifyContent: 'center' }}>
            <span style={{ fontSize: '0.8rem', color: '#f59e0b', fontWeight: 600, display: 'flex', alignItems: 'center', gap: '6px' }}>
              ⚠ Détail limité aux {data.timeline_shown} plus récents
            </span>
          </div>
        )}
      </div>

      {/* HEATMAP */}
      <div style={{ marginBottom: '2.5rem' }}>
        <h4 style={{ fontSize: '0.9rem', fontWeight: 700, margin: '0 0 1rem', color: 'var(--text-main)' }}>Densité d'activité</h4>
        <div style={{ overflowX: 'auto', paddingBottom: '8px' }}>
          <div style={{ display: 'flex', gap: '4px', minWidth: 'max-content' }}>
            {heatDays.map((d, index) => {
              const isFirst = index === 0;
              const isLast = index === heatDays.length - 1;
              const isMonday = new Date(d.date + 'T00:00:00').getDay() === 1;
              const showLabel = isFirst || isLast || isMonday;
              const hColor = heatColor(d.nb_transactions);
              
              return (
                <div key={d.date} style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', gap: '6px' }}>
                  <div
                    style={{
                      width: '20px', height: '20px', borderRadius: '4px',
                      background: hColor.bg,
                      border: `1px solid ${hColor.border}`,
                      cursor: 'pointer', flexShrink: 0,
                      transition: 'transform 0.1s',
                    }}
                    onMouseEnter={e => e.currentTarget.style.transform = 'scale(1.2)'}
                    onMouseLeave={e => e.currentTarget.style.transform = 'scale(1)'}
                    title={`${fmtDateFr(d.date)} — ${d.nb_transactions} tx${d.montant_total > 0 ? ' · ' + formatDT(d.montant_total) + ' DT' : ''}`}
                  />
                  {showLabel && (
                    <span style={{ 
                      fontSize: '10px', 
                      color: (isFirst || isLast) ? 'var(--text-main)' : 'var(--text-muted)', 
                      fontWeight: (isFirst || isLast) ? 700 : 500,
                      whiteSpace: 'nowrap' 
                    }}>
                      {fmtShort(d.date)}
                    </span>
                  )}
                </div>
              );
            })}
          </div>
        </div>
      </div>

      {/* CHART EVOLUTION */}
      {chartData.length > 1 && (
        <div style={{ marginBottom: '2.5rem', background: 'var(--bg-surface)', padding: '1.5rem', borderRadius: '16px', border: '1px solid var(--border)' }}>
          <h4 style={{ fontSize: '0.9rem', fontWeight: 700, margin: '0 0 1rem', color: 'var(--text-main)' }}>Évolution des dépenses</h4>
          <ResponsiveContainer width="100%" height={160}>
            <AreaChart data={chartData}>
              <defs>
                <linearGradient id="colorMontant" x1="0" y1="0" x2="0" y2="1">
                  <stop offset="5%" stopColor="#6366f1" stopOpacity={0.3}/>
                  <stop offset="95%" stopColor="#6366f1" stopOpacity={0}/>
                </linearGradient>
              </defs>
              <XAxis dataKey="date" tick={{ fontSize: 11, fill: "var(--text-muted)", fontWeight: 500 }} axisLine={false} tickLine={false} dy={10} />
              <YAxis tick={{ fontSize: 11, fill: "var(--text-muted)" }} axisLine={false} tickLine={false} dx={-10} />
              <Tooltip 
                contentStyle={{ background: 'var(--bg-elevated)', border: 'none', borderRadius: '12px', boxShadow: '0 10px 25px -5px rgba(0,0,0,0.1)', fontSize: '0.85rem', fontWeight: 600 }}
                formatter={(v) => [`${formatDT(v)} DT`, "Dépense"]} 
              />
              <Area type="monotone" dataKey="montant" stroke="#6366f1" strokeWidth={3} fillOpacity={1} fill="url(#colorMontant)" activeDot={{ r: 6, fill: '#6366f1', stroke: '#fff', strokeWidth: 2 }} />
            </AreaChart>
          </ResponsiveContainer>
        </div>
      )}

      {/* SERVICES FILTERS */}
      {svcStats.length > 0 && (
        <div style={{ display: 'flex', flexWrap: 'wrap', gap: '0.5rem', marginBottom: '1.5rem' }}>
          <span style={{ fontSize: '0.8rem', fontWeight: 700, color: 'var(--text-muted)', display: 'flex', alignItems: 'center', marginRight: '0.5rem' }}>Filtrer :</span>
          {svcStats.map((s) => (
            <button key={s.service} 
              style={{
                padding: '0.4rem 0.8rem', borderRadius: '99px', border: '1px solid',
                fontSize: '0.75rem', fontWeight: 600, cursor: 'pointer', transition: 'all 0.2s', display: 'flex', alignItems: 'center', gap: '6px',
                background: svcFilter === s.service ? '#6366f1' : 'var(--bg-surface)',
                color: svcFilter === s.service ? '#fff' : 'var(--text-main)',
                borderColor: svcFilter === s.service ? '#6366f1' : 'var(--border)'
              }}
              onClick={() => setSvcFilter(svcFilter === s.service ? null : s.service)}>
              {s.service} 
              <span style={{ opacity: 0.7, fontSize: '0.7rem' }}>({s.count})</span>
            </button>
          ))}
          {svcFilter && (
            <button onClick={() => setSvcFilter(null)} style={{ padding: '0.4rem 0.8rem', borderRadius: '99px', border: 'none', background: 'rgba(239,68,68,0.1)', color: '#ef4444', fontSize: '0.75rem', fontWeight: 700, cursor: 'pointer' }}>
              ✕ Effacer
            </button>
          )}
        </div>
      )}

      {/* TIMELINE STREAM */}
      <h4 style={{ fontSize: '1.1rem', fontWeight: 800, color: 'var(--text-main)', marginTop: '2rem', borderBottom: '1px solid var(--border)', paddingBottom: '0.5rem' }}>
        Journal Détaillé
      </h4>
      <div style={timelineContainerStyle}>
        {grouped.length === 0 ? (
          <div style={{ padding: '3rem', textAlign: 'center', background: 'var(--bg-surface)', borderRadius: '16px', border: '1px dashed var(--border)' }}>
            <div style={{ fontSize: '2rem', marginBottom: '1rem' }}>📭</div>
            <p style={{ color: 'var(--text-muted)', margin: 0, fontWeight: 600 }}>Aucune activité correspondant aux filtres.</p>
          </div>
        ) : (
          grouped.map(([date, evs]) => {
            const dayTot = evs.reduce((s, it) => s + it.montant, 0);
            const gap = data.gaps?.find((g) => g.apres === date);
            return (
              <div key={date}>
                {gap && (
                  <div style={{ margin: '1rem 0 2rem -12px', display: 'flex', alignItems: 'center', gap: '12px' }}>
                    <div style={{ width: '8px', height: '8px', borderRadius: '50%', background: 'var(--border)' }} />
                    <span style={{ fontSize: '0.75rem', fontWeight: 600, color: 'var(--text-muted)', background: 'var(--bg-surface)', padding: '4px 10px', borderRadius: '99px', border: '1px dashed var(--border)' }}>
                      Inactivité de {gap.jours} jour{gap.jours > 1 ? 's' : ''}
                    </span>
                  </div>
                )}
                <div style={dayCardStyle}>
                  <div style={dotIndicatorStyle} />
                  
                  <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '1.5rem', borderBottom: '1px solid var(--border)', paddingBottom: '0.75rem' }}>
                    <span style={{ fontSize: '1.05rem', fontWeight: 800, color: 'var(--text-main)' }}>{fmtDateFr(date)}</span>
                    <span style={{ fontSize: '0.85rem', fontWeight: 700, color: 'var(--text-muted)', background: 'var(--bg-elevated)', padding: '4px 12px', borderRadius: '8px' }}>
                      {evs.length} tx {dayTot > 0 ? `• ${formatDT(dayTot)} DT` : ''}
                    </span>
                  </div>

                  <div style={{ display: 'flex', flexDirection: 'column', gap: '0.75rem' }}>
                    {evs.map((ev) => {
                      const isOcc = ev.source === 'OCC';
                      const sourceColor = isOcc ? '#3b82f6' : '#10b981';
                      const sourceBg = isOcc ? 'rgba(59,130,246,0.1)' : 'rgba(16,185,129,0.1)';
                      
                      return (
                        <div key={ev.id + "-" + ev.source} style={{
                          display: 'flex', alignItems: 'center', gap: '1rem', padding: '0.75rem 1rem',
                          background: ev.doublon ? 'rgba(239,68,68,0.02)' : 'var(--bg-elevated)',
                          border: ev.doublon ? '1px dashed #fca5a5' : '1px solid var(--border)',
                          borderRadius: '12px', transition: 'background 0.2s',
                        }}
                        onMouseEnter={e => e.currentTarget.style.background = 'var(--bg-surface)'}
                        onMouseLeave={e => e.currentTarget.style.background = ev.doublon ? 'rgba(239,68,68,0.02)' : 'var(--bg-elevated)'}
                        >
                          {/* Temps */}
                          <div style={{ fontSize: '0.85rem', fontWeight: 700, color: 'var(--text-main)', width: '45px', flexShrink: 0 }}>
                            {fmtHour(ev.heure)}
                          </div>
                          
                          {/* Badges Info */}
                          <div style={{ display: 'flex', gap: '6px', flexShrink: 0 }}>
                            <span style={{ fontSize: '0.65rem', fontWeight: 800, padding: '2px 6px', borderRadius: '4px', background: sourceBg, color: sourceColor }}>
                              {ev.source}
                            </span>
                            {ev.role && (
                              <span style={{
                                fontSize: '0.65rem', fontWeight: 700, padding: '2px 6px', borderRadius: '4px',
                                background: ev.role === 'appelant' ? 'rgba(99,102,241,0.1)' : 'rgba(245,158,11,0.1)',
                                color: ev.role === 'appelant' ? '#6366f1' : '#f59e0b',
                              }}>{ev.role === 'appelant' ? '↑ OUT' : '↓ IN'}</span>
                            )}
                          </div>

                          {/* Detail */}
                          <div style={{ display: 'flex', flexDirection: 'column', flexGrow: 1 }}>
                            <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                              <strong style={{ fontSize: '0.9rem', color: 'var(--text-main)' }}>{ev.nom_service || ev.service}</strong>
                              {ev.montant > 0 && <span style={{ fontSize: '0.8rem', fontWeight: 700, color: '#10b981' }}>+{formatDT(ev.montant)} DT</span>}
                            </div>
                            {ev.destinataire && (
                              <div style={{ fontSize: '0.75rem', color: 'var(--text-muted)', display: 'flex', alignItems: 'center', gap: '4px', marginTop: '2px' }}>
                                <span>Vers:</span> <span style={{ fontFamily: 'monospace', background: 'rgba(0,0,0,0.05)', padding: '0 4px', borderRadius: '3px' }}>{ev.destinataire}</span>
                              </div>
                            )}
                          </div>

                          {/* Doublon Warning */}
                          {ev.doublon && (
                            <span style={{ fontSize: '0.7rem', fontWeight: 700, color: '#ef4444', background: 'rgba(239,68,68,0.1)', padding: '2px 8px', borderRadius: '99px' }}>
                              DOUBLON
                            </span>
                          )}
                        </div>
                      );
                    })}
                  </div>
                </div>
              </div>
            );
          })
        )}
      </div>
    </div>
  );
}
