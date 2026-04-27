/* eslint-disable react/prop-types */
import { useMemo, useState } from 'react';
import { LineChart, Line, XAxis, YAxis, Tooltip, ResponsiveContainer } from 'recharts';
import { formatDT } from '../lib/format';

const HEAT = [
  { min: 0, max: 0, bg: '#f1f5f9' },
  { min: 1, max: 2, bg: '#bfdbfe' },
  { min: 3, max: 5, bg: '#60a5fa' },
  { min: 6, max: 10, bg: '#2563eb' },
  { min: 11, max: Infinity, bg: '#1e3a8a' },
];

function heatColor(c) {
  for (const h of HEAT) if (c >= h.min && c <= h.max) return h.bg;
  return HEAT[0].bg;
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

  if (!data) return null;
  const tl = data.timeline || [];
  const pj = data.par_jour || [];

  const filtered = useMemo(() => {
    let a = [...tl];
    if (source !== 'all') a = a.filter((it) => it.source.toLowerCase() === source);
    if (svcFilter) a = a.filter((it) => it.service === svcFilter);
    return a;
  }, [tl, source, svcFilter]);

  const grouped = useMemo(() => groupByDate(filtered), [filtered]);

  const heatDays = useMemo(() => {
    if (!data.periode) return [];
    const days = [];
    const map = Object.fromEntries(pj.map((d) => [d.date, d]));
    for (let d = new Date(data.periode.debut + 'T00:00:00'), e = new Date(data.periode.fin + 'T00:00:00'); d <= e; d.setDate(d.getDate() + 1)) {
      const iso = d.toISOString().slice(0, 10);
      days.push({ date: iso, ...(map[iso] || { nb_transactions: 0, montant_total: 0 }) });
    }
    return days;
  }, [pj, data]);

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

  return (
    <div className="msisdn-timeline">
      <div className="timeline-header">
        <div>
          <h3 className="text-heading" style={{ margin: 0, fontSize: "1.1rem" }}>Historique activite</h3>
          <p style={{ margin: "0.25rem 0 0", fontSize: "0.82rem", color: "var(--text-muted)" }}>
            {data.periode ? fmtShort(data.periode.debut) + ' -> ' + fmtShort(data.periode.fin) : '-'}
          </p>
        </div>
        <div className="segmented">
          {['all', 'occ', 'mmg'].map((k) => (
            <button key={k} className={"segmented-btn " + (source === k ? "active" : "")} onClick={() => setSource(k)}>
              {k === "all" ? "Tout" : k.toUpperCase()}
            </button>
          ))}
        </div>
      </div>

      <div className="timeline-quick-stats">
        <span className="tl-stat">{filtered.length} <small>transactions</small></span>
        <span className="tl-stat">{formatDT(totM)} <small>DT</small></span>
        <span className="tl-stat">{svcStats.length} <small>services</small></span>
      </div>

      <div className="timeline-heat-section">
        <div className="heat-grid">
          {heatDays.map((d) => (
            <div key={d.date} className="heat-cell" style={{ backgroundColor: heatColor(d.nb_transactions) }}
              title={d.nb_transactions + " tx - " + formatDT(d.montant_total) + " - " + fmtShort(d.date)} />
          ))}
        </div>
        <div className="heat-legend">
          <span>Moins</span>{HEAT.map((h) => <span key={h.bg} className="heat-legend-dot" style={{ backgroundColor: h.bg }} />)}
          <span>Plus</span>
        </div>
      </div>

      {chartData.length > 1 && (
        <div className="timeline-chart-wrap">
          <h4 className="text-heading" style={{ fontSize: "0.85rem", margin: "0 0 0.5rem" }}>Evolution</h4>
          <ResponsiveContainer width="100%" height={120} minWidth={0} minHeight={0}>
            <LineChart data={chartData}>
              <XAxis dataKey="date" tick={{ fontSize: 10, fill: "var(--text-muted)" }} axisLine={{ stroke: "var(--border)" }} />
              <YAxis tick={{ fontSize: 10, fill: "var(--text-muted)" }} axisLine={{ stroke: "var(--border)" }} />
              <Tooltip contentStyle={{ background: "var(--bg-page)", border: "1px solid var(--border)", borderRadius: "8px", fontSize: "0.82rem" }} formatter={(v) => [formatDT(v), "Montant"]} />
              <Line type="monotone" dataKey="montant" stroke="#3b82f6" strokeWidth={2} dot={{ r: 3, fill: "#3b82f6" }} />
            </LineChart>
          </ResponsiveContainer>
        </div>
      )}

      {svcStats.length > 0 && (
        <div className="timeline-services">
          {svcStats.map((s) => (
            <button key={s.service} className={"service-chip " + (svcFilter === s.service ? "active" : "")}
              onClick={() => setSvcFilter(svcFilter === s.service ? null : s.service)}>
              <strong>{s.service}</strong><span>{s.count} - {formatDT(s.montant)}</span>
            </button>
          ))}
          {svcFilter && <button className="service-chip reset" onClick={() => setSvcFilter(null)}>X Reset</button>}
        </div>
      )}

      <div className="timeline-main">
        {grouped.length === 0 ? (
          <div className="empty-state" style={{ padding: "2rem", textAlign: "center" }}>
            <p style={{ color: "var(--text-muted)", margin: 0 }}>Aucune activite</p>
          </div>
        ) : (
          grouped.map(([date, evs]) => {
            const dayTot = evs.reduce((s, it) => s + it.montant, 0);
            const gap = data.gaps?.find((g) => g.apres === date);
            return (
              <div key={date} className="timeline-day">
                {gap && <div className="timeline-gap">{"-- Inactif " + gap.jours + " j --"}</div>}
                <div className="timeline-day-header">
                  <span>{fmtDateFr(date)}</span>
                  <span>{evs.length} tx{dayTot > 0 ? " - " + formatDT(dayTot) : ""}</span>
                </div>
                <div className="timeline-events">
                  {evs.map((ev) => (
                    <div key={ev.id + "-" + ev.source} className={"timeline-event " + (ev.doublon ? "doublon" : "")}>
                      <div className="timeline-dot" style={{ backgroundColor: ev.source === "OCC" ? "#3b82f6" : "#10b981" }} />
                      <div className="timeline-event-body">
                        <span>{fmtHour(ev.heure)}</span>
                        <span className={"source-badge " + (ev.source === "OCC" ? "occ" : "mmg")}>{ev.source}</span>
                        <strong>{ev.service}</strong>
                        {ev.montant > 0 && <span> - {formatDT(ev.montant)}</span>}
                        {ev.destinataire && <span> -> {ev.destinataire}</span>}
                        {ev.doublon && <span className="badge badge-warn">Doublon</span>}
                      </div>
                    </div>
                  ))}
                </div>
              </div>
            );
          })
        )}
      </div>
    </div>
  );
}
