import { useEffect, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import api from '../api/axios';
import { formatDT } from '../lib/format';
import MsisdnTimeline from '../components/MsisdnTimeline';
import useServiceMapping from '../hooks/useServiceMapping';

const OCC_COLS = [
  { key: 'a_msisdn', label: 'MSISDN Appelant' },
  { key: 'b_msisdn', label: 'MSISDN Destinataire' },
  { key: 'start_date', label: 'Date' },
  { key: 'start_hour', label: 'Heure' },
  { key: 'call_type', label: 'Type' },
  { key: 'event_type', label: 'Événement' },
  { key: 'charge_amount', label: 'Montant' },
  { key: 'keyword', label: 'Service' },
];

const MMG_COLS = [
  { key: 'a_msisdn', label: 'MSISDN Appelant' },
  { key: 'b_msisdn', label: 'MSISDN Destinataire' },
  { key: 'start_date', label: 'Date' },
  { key: 'start_hour', label: 'Heure' },
  { key: 'event_type', label: 'Événement' },
  { key: 'event_status', label: 'Statut' },
  { key: 'service_type', label: 'Service' },
];

const STATUS_BADGE = {
  ouverte:  { label: 'Ouverte',  bg: 'rgba(245,158,11,0.15)',  color: '#f59e0b' },
  en_cours: { label: 'En cours', bg: 'rgba(99,102,241,0.15)',  color: '#6366f1' },
  resolue:  { label: 'Résolue',  bg: 'rgba(16,185,129,0.15)',  color: '#10b981' },
};

const StatChip = ({ label, value, color }) => (
  <div style={{
    background: 'var(--bg-elevated)', border: '1px solid var(--border)',
    borderRadius: '10px', padding: '0.6rem 1rem', textAlign: 'center', minWidth: '110px'
  }}>
    <p style={{ margin: 0, fontSize: '0.7rem', color: 'var(--text-muted)', fontWeight: 500 }}>{label}</p>
    <p style={{ margin: '2px 0 0', fontWeight: 800, fontSize: '1.15rem', color: color || 'var(--text-main)' }}>{value}</p>
  </div>
);

export default function MsisdnSearch() {
  const [searchParams] = useSearchParams();
  const [msisdn, setMsisdn]           = useState(searchParams.get('q') || '');
  const [reclamations, setReclamations] = useState(null);
  const [cdr, setCdr]                 = useState(null);
  const [timelineData, setTimelineData] = useState(null);
  const [loading, setLoading]         = useState(false);
  const [error, setError]             = useState('');
  const [activeTab, setActiveTab]     = useState('occ');

  const { getNom } = useServiceMapping();

  useEffect(() => {
    const q = searchParams.get('q');
    if (q) { setMsisdn(q); search(q); }
  }, [searchParams]);

  const search = async (overrideQ) => {
    const q = (overrideQ || msisdn).trim();
    if (!q) { setError('Veuillez saisir un numéro MSISDN'); return; }
    setLoading(true); setError('');
    setReclamations(null); setCdr(null); setTimelineData(null);
    const enc = encodeURIComponent(q);
    try {
      const [recRes, cdrRes, tlRes] = await Promise.allSettled([
        api.get(`/reclamations/${enc}`),
        api.get(`/cdr/msisdn/${enc}`),
        api.get(`/cdr/msisdn/${enc}/timeline`),
      ]);
      if (recRes.status === 'fulfilled') setReclamations(recRes.value.data); else setReclamations([]);
      if (tlRes.status  === 'fulfilled') setTimelineData(tlRes.value.data);
      if (cdrRes.status === 'fulfilled') setCdr(cdrRes.value.data);
      else if (recRes.status === 'rejected') setError('Erreur lors de la recherche');
    } catch { setError('Erreur lors de la recherche'); }
    finally { setLoading(false); }
  };

  const hasResults = cdr || reclamations !== null;

  const thStyle = {
    padding: '0.6rem 0.75rem', textAlign: 'left',
    fontWeight: 600, fontSize: '0.75rem', color: 'var(--text-muted)',
    borderBottom: '1px solid var(--border)', whiteSpace: 'nowrap',
    textTransform: 'uppercase', letterSpacing: '0.05em'
  };
  const tdStyle = (mono) => ({
    padding: '0.55rem 0.75rem', fontSize: '0.8rem',
    color: 'var(--text-main)', borderBottom: '1px solid var(--border)',
    fontFamily: mono ? 'monospace' : 'inherit'
  });

  const renderTable = (rows, cols, emptyMsg) => (
    <div style={{ overflowX: 'auto' }}>
      <table style={{ minWidth: '100%', borderCollapse: 'collapse', fontSize: '0.8rem', tableLayout: 'auto' }}>
        <thead>
          <tr style={{ background: 'var(--bg-surface)' }}>
            {cols.map(c => <th key={c.key} style={thStyle}>{c.label}</th>)}
          </tr>
        </thead>
        <tbody>
          {rows.length === 0 ? (
            <tr><td colSpan={cols.length} style={{ padding: '2rem', textAlign: 'center', color: 'var(--text-muted)' }}>{emptyMsg}</td></tr>
          ) : rows.map((row, i) => (
            <tr key={row.id || i} style={{ transition: 'background 0.1s' }}
              onMouseEnter={e => e.currentTarget.style.background = 'var(--bg-surface)'}
              onMouseLeave={e => e.currentTarget.style.background = 'transparent'}
            >
              {cols.map(c => (
                <td key={c.key} style={tdStyle(c.key.includes('msisdn'))}>
                  {c.key === 'charge_amount' ? (
                    <span style={{ fontWeight: 700, color: '#10b981' }}>{formatDT(row.charge_amount)}</span>
                  ) : (c.key === 'keyword' || c.key === 'service_type') ? (
                    <div>
                      <div style={{ fontWeight: 600, fontSize: '0.8rem' }}>{getNom(row[c.key])}</div>
                      <code style={{ fontSize: '0.7rem', color: '#94a3b8' }}>{row[c.key]}</code>
                    </div>
                  ) : (c.key === 'event_status') ? (
                    <span style={{
                      fontSize: '0.7rem', fontWeight: 700, padding: '2px 8px', borderRadius: '6px',
                      background: row[c.key] === 'SUCCESS' ? 'rgba(16,185,129,0.15)' : 'rgba(239,68,68,0.15)',
                      color: row[c.key] === 'SUCCESS' ? '#10b981' : '#ef4444'
                    }}>{row[c.key] ?? '—'}</span>
                  ) : (row[c.key] ?? '—')}
                </td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );

  return (
    <div className="page" style={{ padding: '2rem', maxWidth: '1400px', margin: '0 auto' }}>

      {/* Header */}
      <div style={{ marginBottom: '1.5rem' }}>
        <h1 style={{ fontSize: '1.75rem', fontWeight: 800, color: 'var(--text-main)', margin: '0 0 0.25rem' }}>
          Recherche MSISDN
        </h1>
        <p style={{ color: 'var(--text-muted)', margin: 0 }}>
          CDR OCC & MMG + réclamations associées à un numéro abonné.
          <span style={{ marginLeft: '10px', fontSize: '0.8rem', padding: '2px 8px', background: 'rgba(99,102,241,0.1)', color: '#6366f1', borderRadius: '4px', fontWeight: 500 }}>
            Analyse IA : Détection de comportements à risque et fraude.
          </span>
        </p>
      </div>

      {/* Search bar */}
      <div style={{
        background: 'var(--bg-elevated)', border: '1px solid var(--border)',
        borderRadius: '16px', padding: '1.25rem 1.5rem', marginBottom: '1.25rem',
        display: 'flex', gap: '1rem', alignItems: 'flex-end', flexWrap: 'wrap'
      }}>
        <div style={{ flex: '1 1 300px' }}>
          <label style={{ display: 'block', fontSize: '0.78rem', fontWeight: 600, color: 'var(--text-muted)', marginBottom: '6px', textTransform: 'uppercase', letterSpacing: '0.05em' }}>
            Numéro MSISDN
          </label>
          <input
            value={msisdn}
            onChange={e => setMsisdn(e.target.value)}
            onKeyDown={e => e.key === 'Enter' && search()}
            placeholder="ex: 21698542320"
            style={{
              width: '100%', height: '44px', border: '2px solid var(--border)', borderRadius: '10px',
              padding: '0 1rem', fontSize: '1rem', fontFamily: 'monospace', letterSpacing: '0.1em',
              background: 'var(--bg-surface)', color: 'var(--text-main)', boxSizing: 'border-box',
              transition: 'border-color 0.2s'
            }}
            onFocus={e => e.target.style.borderColor = '#6366f1'}
            onBlur={e => e.target.style.borderColor = 'var(--border)'}
          />
        </div>
        <button
          className="btn btn-primary"
          onClick={() => search()}
          disabled={loading}
          style={{ height: '44px', padding: '0 2rem', fontWeight: 700, fontSize: '1rem', flexShrink: 0 }}
        >
          {loading ? 'Recherche en cours…' : 'Rechercher'}
        </button>
        {error && <div style={{ flexBasis: '100%', color: '#ef4444', fontSize: '0.88rem', fontWeight: 500 }}>⚠ {error}</div>}
      </div>


      {hasResults && (
        <>
          {/* Stats strip */}
          <div style={{ display: 'flex', gap: '0.75rem', flexWrap: 'wrap', margin: '1.25rem 0' }}>
            <StatChip label="CDR OCC" value={(cdr?.occ_total ?? 0).toLocaleString('fr-FR')} color="#6366f1" />
            <StatChip label="CDR MMG" value={(cdr?.mmg_total ?? 0).toLocaleString('fr-FR')} color="#8b5cf6" />
            <StatChip 
              label="Score Risque IA" 
              value={cdr?.risk_analysis ? `${cdr.risk_analysis.score}/100` : '—'} 
              color={cdr?.risk_analysis?.level === 'CRITICAL' ? '#ef4444' : (cdr?.risk_analysis?.level === 'WARNING' ? '#f59e0b' : '#10b981')} 
            />
            <StatChip label="Réclamations" value={reclamations?.length ?? 0} color={reclamations?.length > 0 ? '#ef4444' : '#10b981'} />
            
            {cdr?.risk_analysis?.reasons?.length > 0 && (
              <div style={{ 
                fontSize: '0.75rem', background: 'rgba(99,102,241,0.05)', border: '1px dashed #6366f1', 
                borderRadius: '8px', padding: '0.5rem 0.8rem', display: 'flex', alignItems: 'center', gap: '8px'
              }}>
                <span style={{ fontWeight: 700, color: '#6366f1' }}>Analyse IA :</span>
                <span style={{ color: 'var(--text-muted)' }}>{cdr.risk_analysis.reasons.join(' · ')}</span>
              </div>
            )}

            {cdr?.occ_truncated && (
              <div style={{ display: 'flex', alignItems: 'center', gap: '6px', fontSize: '0.8rem', color: '#f59e0b', fontWeight: 500 }}>
                Résultats limités à {cdr.occ_shown} (OCC)
              </div>
            )}
          </div>

          {/* Timeline */}
          {timelineData && <MsisdnTimeline data={timelineData} />}

          {/* CDR Tabs */}
          <div style={{ background: 'var(--bg-elevated)', border: '1px solid var(--border)', borderRadius: '16px', overflow: 'hidden', marginBottom: '1.25rem' }}>
            <div style={{ display: 'flex', borderBottom: '1px solid var(--border)', background: 'var(--bg-surface)' }}>
              {[
                { id: 'occ', label: `OCC (${(cdr?.occ || []).length})` },
                { id: 'mmg', label: `MMG (${(cdr?.mmg || []).length})` },
              ].map(tab => (
                <button key={tab.id} onClick={() => setActiveTab(tab.id)}
                  style={{
                    padding: '0.85rem 1.5rem', fontWeight: 700, fontSize: '0.88rem', cursor: 'pointer',
                    background: 'none', border: 'none',
                    color: activeTab === tab.id ? '#6366f1' : 'var(--text-muted)',
                    borderBottom: activeTab === tab.id ? '2px solid #6366f1' : '2px solid transparent',
                    transition: 'all 0.15s'
                  }}
                >{tab.label}</button>
              ))}
              <span style={{ marginLeft: 'auto', padding: '0 1rem', display: 'flex', alignItems: 'center', fontSize: '0.78rem', color: 'var(--text-muted)' }}>
                {msisdn.trim()}
              </span>
            </div>
            <div style={{ maxHeight: 400, overflowY: 'auto' }}>
              {activeTab === 'occ'
                ? renderTable(cdr?.occ || [], OCC_COLS, 'Aucun enregistrement OCC')
                : renderTable(cdr?.mmg || [], MMG_COLS, 'Aucun enregistrement MMG')}
            </div>
          </div>

          {/* Reclamations */}
          <div style={{ background: 'var(--bg-elevated)', border: '1px solid var(--border)', borderRadius: '16px', overflow: 'hidden' }}>
            <div style={{ padding: '1rem 1.5rem', borderBottom: '1px solid var(--border)', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
              <h3 style={{ margin: 0, fontWeight: 700, fontSize: '1rem' }}>Réclamations</h3>
              <span style={{
                fontSize: '0.75rem', fontWeight: 700, padding: '3px 12px', borderRadius: '99px',
                background: reclamations?.length > 0 ? 'rgba(245,158,11,0.15)' : 'rgba(16,185,129,0.15)',
                color: reclamations?.length > 0 ? '#f59e0b' : '#10b981'
              }}>
                {reclamations?.length ?? 0} réclamation{reclamations?.length !== 1 ? 's' : ''}
              </span>
            </div>
            {!reclamations?.length ? (
              <div style={{ padding: '2rem', textAlign: 'center', color: 'var(--text-muted)' }}>
                <p style={{ margin: '0.5rem 0 0', fontWeight: 500 }}>Aucune réclamation pour ce numéro</p>
              </div>
            ) : (
              <table style={{ width: '100%', borderCollapse: 'collapse' }}>
                <thead>
                  <tr style={{ background: 'var(--bg-surface)' }}>
                    {['#', 'Description', 'Service', 'Statut', 'Date'].map(h => <th key={h} style={thStyle}>{h}</th>)}
                  </tr>
                </thead>
                <tbody>
                  {reclamations.map(r => {
                    const s = STATUS_BADGE[r.statut] || { label: r.statut, bg: 'var(--bg-surface)', color: 'var(--text-muted)' };
                    return (
                      <tr key={r.id}
                        onMouseEnter={e => e.currentTarget.style.background = 'var(--bg-surface)'}
                        onMouseLeave={e => e.currentTarget.style.background = 'transparent'}
                        style={{ transition: 'background 0.1s' }}
                      >
                        <td style={{ ...tdStyle(true), color: 'var(--text-muted)' }}>#{r.id}</td>
                        <td style={tdStyle(false)}>{r.description}</td>
                        <td style={{ ...tdStyle(false), color: 'var(--text-muted)' }}>{r.service?.nom_service || '—'}</td>
                        <td style={tdStyle(false)}>
                          <span style={{ fontSize: '0.72rem', fontWeight: 700, padding: '2px 10px', borderRadius: '99px', background: s.bg, color: s.color }}>{s.label}</span>
                        </td>
                        <td style={{ ...tdStyle(true), color: 'var(--text-muted)' }}>{new Date(r.created_at).toLocaleDateString('fr-FR')}</td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            )}
          </div>
        </>
      )}

      {/* Placeholder when no search */}
      {!hasResults && !loading && !error && (
        <div style={{ padding: '4rem', textAlign: 'center', color: 'var(--text-muted)' }}>
          <p style={{ fontWeight: 600, fontSize: '1.1rem', margin: '0 0 0.5rem', color: 'var(--text-main)' }}>Recherche par numéro abonné</p>
          <p style={{ margin: 0 }}>Saisissez un MSISDN pour voir ses CDR OCC/MMG et réclamations associées</p>
        </div>
      )}
    </div>
  );
}
