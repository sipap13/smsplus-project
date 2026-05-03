/* eslint-disable react/prop-types */

import { useState } from 'react';
import api from '../api/axios';
import JobStatusBar from '../components/JobStatusBar';
import { formatDT } from '../lib/format';
import MsisdnTimeline from '../components/MsisdnTimeline';

const OCC_COLS = [
  { key: 'a_msisdn', label: 'A' },
  { key: 'b_msisdn', label: 'B' },
  { key: 'start_date', label: 'Date' },
  { key: 'start_hour', label: 'H' },
  { key: 'call_type', label: 'Call' },
  { key: 'event_type', label: 'Event' },
  { key: 'subscriber_type', label: 'Ab.' },
  { key: 'charge_amount', label: 'Montant' },
  { key: 'keyword', label: 'Kw' },
];

const MMG_COLS = [
  { key: 'ne', label: 'NE' },
  { key: 'a_msisdn', label: 'A' },
  { key: 'b_msisdn', label: 'B' },
  { key: 'start_date', label: 'Date' },
  { key: 'start_hour', label: 'H' },
  { key: 'event_type', label: 'Event' },
  { key: 'event_status', label: 'Statut' },
  { key: 'subscriber_type', label: 'Ab.' },
  { key: 'service_type', label: 'Svc' },
];

export default function MsisdnSearch() {
  const [msisdn, setMsisdn] = useState('');
  const [reclamations, setReclamations] = useState(null);
  const [cdr, setCdr] = useState(null);
  const [timelineData, setTimelineData] = useState(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');

  const search = async () => {
    const q = msisdn.trim();
    if (!q) {
      setError('Veuillez saisir un numéro MSISDN');
      return;
    }
    setLoading(true);
    setError('');
    setReclamations(null);
    setCdr(null);
    setTimelineData(null);
    const enc = encodeURIComponent(q);
    try {
      const [recRes, cdrRes, tlRes] = await Promise.allSettled([
        api.get(`/reclamations/${enc}`),
        api.get(`/cdr/msisdn/${enc}`),
        api.get(`/cdr/msisdn/${enc}/timeline`),
      ]);
      if (recRes.status === 'fulfilled') {
        setReclamations(recRes.value.data);
      } else {
        setReclamations([]);
      }
      if (tlRes.status === 'fulfilled') { setTimelineData(tlRes.value.data); } else { setTimelineData(null); }
      if (cdrRes.status === 'fulfilled') {
        setCdr(cdrRes.value.data);
      } else {
        setCdr(null);
        if (recRes.status === 'rejected') {
          setError('Erreur lors de la recherche');
        }
      }
    } catch {
      setError('Erreur lors de la recherche');
    } finally {
      setLoading(false);
    }
  };

  const statusBadge = (s) => ({ ouverte: 'badge-warn', en_cours: 'badge', resolue: 'badge-ok' }[s] || 'badge');

  const renderMiniTable = (rows, cols, emptyMsg) => (
    <div style={{ overflow: 'auto', maxHeight: '420px' }}>
      <table className="table-mobile" style={{ width: '100%', borderCollapse: 'collapse', fontSize: '0.78rem' }}>
        <thead>
          <tr>
            {cols.map((c) => (
              <th key={c.key} style={{ padding: '0.45rem 0.4rem', textAlign: 'left', color: 'var(--text-muted)', fontWeight: 600, borderBottom: '1px solid var(--border)', whiteSpace: 'nowrap' }}>
                {c.label}
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {rows.length === 0 ? (
            <tr>
              <td colSpan={cols.length} style={{ padding: '1.25rem', textAlign: 'center', color: 'var(--text-muted)' }}>{emptyMsg}</td>
            </tr>
          ) : (
            rows.map((row) => (
              <tr key={row.id}>
                {cols.map((c) => (
                  <td key={c.key} style={{ padding: '0.4rem', fontFamily: c.key.includes('msisdn') ? 'monospace' : 'inherit', color: 'var(--text-main)' }}>
                    {c.key === 'charge_amount' ? formatDT(row.charge_amount) : (row[c.key] ?? '—')}
                  </td>
                ))}
              </tr>
            ))
          )}
        </tbody>
      </table>
    </div>
  );

  return (
    <div className="page">
      <div className="page-header">
        <div>
          <h1 className="page-title">Recherche réclamations & CDR</h1>
          <p className="page-subtitle">CDR OCC & MMG côte à côte, et réclamations associées</p>
        </div>
      </div>

      <div className="command-bar" style={{ marginBottom: '1rem' }}>
        <div className="field" style={{ flex: '2 1 320px' }}>
          <div className="field-label">MSISDN</div>
          <input
            className="field-control mono"
            type="text"
            value={msisdn}
            onChange={(e) => setMsisdn(e.target.value)}
            onKeyDown={(e) => e.key === 'Enter' && search()}
            placeholder="ex: 21698542320"
          />
        </div>
        <div className="command-actions">
          <button type="button" onClick={search} disabled={loading} className="btn btn-primary" style={{ opacity: loading ? 0.75 : 1 }}>
            {loading ? 'Recherche...' : 'Rechercher'}
          </button>
        </div>
        {error && (
          <div style={{ flexBasis: '100%', color: 'var(--danger)', fontSize: '0.9rem' }}>{error}</div>
        )}
      </div>

      {/* ETL Timeline for MSISDN search */}
      {loading && (
        <JobStatusBar
          mode="timeline"
          steps={[
            { jobName: 'etl_agg_from_raw', label: 'Recherche OCC' },
            { jobName: 'etl_cdr_from_tmp', label: 'Recherche MMG' },
            { jobName: 'import_occ_csv', label: 'Calcul score risque' },
            { jobName: 'import_mmg_csv', label: 'Construction timeline' },
          ]}
        />
      )}

      {(cdr || reclamations !== null) && (
        <>
          {timelineData && <MsisdnTimeline data={timelineData} />}
          <h3 className="text-heading" style={{ fontSize: '1rem', margin: '0 0 0.75rem' }}>Transactions CDR</h3>
          <div
            style={{
              display: 'grid',
              gridTemplateColumns: 'repeat(auto-fit, minmax(min(100%, 420px), 1fr))',
              gap: '1rem',
              marginBottom: '1.75rem',
            }}
          >
            <div className="saas-surface" style={{ borderRadius: '12px', padding: '1rem' }}>
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '0.75rem', flexWrap: 'wrap', gap: '0.5rem' }}>
                <strong className="text-heading" style={{ fontSize: '0.95rem' }}>OCC</strong>
                {cdr && (
                  <span style={{ fontSize: '0.8rem', color: 'var(--text-muted)' }}>
                    {cdr.occ_total?.toLocaleString('fr-FR') ?? 0} ligne(s)
                    {cdr.occ_truncated ? ` — affichage limité à ${cdr.occ_shown}` : ''}
                  </span>
                )}
              </div>
              {cdr ? renderMiniTable(cdr.occ || [], OCC_COLS, 'Aucun enregistrement OCC') : (
                <p style={{ color: 'var(--text-muted)', fontSize: '0.9rem' }}>—</p>
              )}
            </div>
            <div className="saas-surface" style={{ borderRadius: '12px', padding: '1rem' }}>
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '0.75rem', flexWrap: 'wrap', gap: '0.5rem' }}>
                <strong className="text-heading" style={{ fontSize: '0.95rem' }}>MMG</strong>
                {cdr && (
                  <span style={{ fontSize: '0.8rem', color: 'var(--text-muted)' }}>
                    {cdr.mmg_total?.toLocaleString('fr-FR') ?? 0} ligne(s)
                    {cdr.mmg_truncated ? ` — affichage limité à ${cdr.mmg_shown}` : ''}
                  </span>
                )}
              </div>
              {cdr ? renderMiniTable(cdr.mmg || [], MMG_COLS, 'Aucun enregistrement MMG') : (
                <p style={{ color: 'var(--text-muted)', fontSize: '0.9rem' }}>Données CDR non disponibles</p>
              )}
            </div>
          </div>

          <div className="panel table-wrap" style={{ overflow: 'hidden' }}>
            <div style={{ padding: '1rem 1.5rem', borderBottom: '1px solid var(--border)', display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: '0.5rem' }}>
              <h3 className="text-heading" style={{ margin: 0 }}>
                Réclamations pour <span className="chip mono">{msisdn.trim()}</span>
              </h3>
              <span className={`badge ${reclamations.length > 0 ? 'badge-warn' : 'badge-ok'}`}>
                {reclamations.length} réclamation(s)
              </span>
            </div>

            {reclamations.length === 0 ? (
              <div className="empty-state" style={{ padding: '2rem' }}>
                <p style={{ margin: 0, fontSize: '0.95rem', color: 'var(--text-muted)' }}>Aucune réclamation pour ce numéro</p>
              </div>
            ) : (
              <table className="table-mobile table-dense" style={{ width: '100%', borderCollapse: 'collapse' }}>
                <thead>
                  <tr>
                    {['ID', 'Description', 'Service', 'Statut', 'Date'].map((h) => (
                      <th key={h} style={{ padding: '1rem', textAlign: 'left', fontSize: '0.85rem', color: 'var(--text-muted)', fontWeight: 600, borderBottom: '2px solid var(--border)' }}>
                        {h}
                      </th>
                    ))}
                  </tr>
                </thead>
                <tbody>
                  {reclamations.map((r) => (
                    <tr key={r.id}>
                      <td data-label="ID" className="mono" style={{ padding: '0.875rem 1rem', color: 'var(--text-muted)', fontSize: '0.85rem' }}>#{r.id}</td>
                      <td data-label="Description" style={{ padding: '0.875rem 1rem', color: 'var(--text-main)' }}>{r.description}</td>
                      <td data-label="Service" style={{ padding: '0.875rem 1rem', color: 'var(--text-muted)', fontSize: '0.9rem' }}>{r.service?.nom_service || '—'}</td>
                      <td data-label="Statut" style={{ padding: '0.875rem 1rem' }}>
                        <span className={`badge ${statusBadge(r.statut)}`}>
                          {r.statut}
                        </span>
                      </td>
                      <td data-label="Date" style={{ padding: '0.875rem 1rem', color: 'var(--text-muted)', fontSize: '0.85rem' }}>
                        {new Date(r.created_at).toLocaleDateString('fr-FR')}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
          </div>
        </>
      )}
    </div>
  );
}
