/* eslint-disable react/prop-types */
import { useCallback, useEffect, useState } from 'react';
import api from '../api/axios';
import { downloadExcel } from '../api/excelDownload';
import JobStatusBar from '../components/JobStatusBar';
import { formatDT } from '../lib/format';
import useServiceMapping from '../hooks/useServiceMapping';

const PER_PAGE = 50;

export default function CdrOcc() {
  const [data, setData] = useState([]);
  const [total, setTotal] = useState(0);
  const [totalCharge, setTotalCharge] = useState(0);
  const [currentPage, setCurrentPage] = useState(1);
  const [lastPage, setLastPage] = useState(0);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const [startDate, setStartDate] = useState('');
  const [keyword, setKeyword] = useState('');
  const [subscriberType, setSubscriberType] = useState('');
  const [partner, setPartner] = useState('');

  const [keywords, setKeywords] = useState([]);
  const [subscriberTypes, setSubscriberTypes] = useState([]);

  const [applied, setApplied] = useState({
    start_date: '',
    keyword: '',
    subscriber_type: '',
    partner: '',
  });

  const [exportLoading, setExportLoading] = useState(false);
  const [exportError, setExportError] = useState('');

  const loadOptions = useCallback(() => {
    api
      .get('/cdr/occ/filter-options')
      .then((r) => {
        setKeywords(r.data.keywords || []);
        setSubscriberTypes(r.data.subscriber_types || []);
      })
      .catch(() => {});
  }, []);

  const fetchPage = useCallback(
    async (page, filters) => {
      setLoading(true);
      setError('');
      const params = new URLSearchParams();
      params.set('page', String(page));
      params.set('per_page', String(PER_PAGE));
      if (filters.start_date) params.set('start_date', filters.start_date);
      if (filters.keyword) params.set('keyword', filters.keyword);
      if (filters.subscriber_type) params.set('subscriber_type', filters.subscriber_type);
      if (filters.partner) params.set('partner', filters.partner);
      try {
        const res = await api.get(`/cdr/occ?${params.toString()}`);
        setData(res.data.data || []);
        setTotal(res.data.total ?? 0);
        setTotalCharge(Number(res.data.total_charge_amount) || 0);
        setCurrentPage(res.data.current_page ?? page);
        setLastPage(res.data.last_page ?? 0);
      } catch {
        setError("Impossible de charger les CDR OCC (droits ou API).");
        setData([]);
      } finally {
        setLoading(false);
      }
    },
    []
  );

  useEffect(() => {
    loadOptions();
  }, [loadOptions]);

  useEffect(() => {
    fetchPage(currentPage, applied);
  }, [currentPage, applied, fetchPage]);

  const applyFilters = () => {
    setApplied({
      start_date: startDate.trim(),
      keyword: keyword.trim(),
      subscriber_type: subscriberType.trim(),
      partner: partner.trim(),
    });
    setCurrentPage(1);
  };

  const clearFilters = () => {
    setStartDate('');
    setKeyword('');
    setSubscriberType('');
    setPartner('');
    setApplied({ start_date: '', keyword: '', subscriber_type: '', partner: '' });
    setCurrentPage(1);
  };

  const goPage = (p) => {
    if (p < 1 || (lastPage > 0 && p > lastPage)) return;
    setCurrentPage(p);
  };

  const handleExport = async () => {
    await downloadExcel(
      '/export/occ',
      applied,
      `CDR_OCC_${new Date().toISOString().slice(0, 10)}.xlsx`,
      () => {
        setExportLoading(true);
        setExportError('');
      },
      (err) => {
        setExportError(err || 'Erreur lors de l\'export');
        setExportLoading(false);
      },
      () => {
        setExportLoading(false);
      }
    );
  };

  const { services: mappedServices, getNom } = useServiceMapping();

  const cols = [
    { key: 'a_msisdn', label: 'A MSISDN' },
    { key: 'b_msisdn', label: 'B MSISDN' },
    { key: 'start_date', label: 'Date' },
    { key: 'start_hour', label: 'Heure' },
    { key: 'call_type', label: "Type d'appel" },
    { key: 'event_type', label: 'Event' },
    { key: 'subscriber_type', label: 'Abonné' },
    { key: 'roaming_type', label: 'Roaming' },
    { key: 'partner', label: 'Partenaire' },
    { key: 'charge_amount', label: 'Montant' },
    { key: 'keyword', label: 'Service' },
  ];

  return (
    <div className="page">
      <div className="page-header">
        <div>
          <h1 className="page-title">Journaux CDR OCC</h1>
          <p className="page-subtitle">Consultation lecture seule — pagination serveur ({PER_PAGE} lignes / page)</p>
        </div>
      </div>

      <div className="command-bar" style={{ marginBottom: '1rem' }}>
        <div className="field">
          <div className="field-label">Date</div>
          <input className="field-control" type="date" value={startDate} onChange={(e) => setStartDate(e.target.value)} />
        </div>
        <div className="field">
          <div className="field-label">Service</div>
          <select className="field-control" value={keyword} onChange={(e) => setKeyword(e.target.value)}>
            <option value="">(tous les services)</option>
            {mappedServices.map((s) => (
              <option key={s.keyword} value={s.keyword}>{s.nom_service}</option>
            ))}
          </select>
        </div>
        <div className="field">
          <div className="field-label">Type abonné</div>
          <select className="field-control" value={subscriberType} onChange={(e) => setSubscriberType(e.target.value)}>
            <option value="">(tous)</option>
            {subscriberTypes.map((k) => (
              <option key={k} value={k}>{k}</option>
            ))}
          </select>
        </div>
        <div className="field">
          <div className="field-label">Partenaire</div>
          <input className="field-control" value={partner} onChange={(e) => setPartner(e.target.value)} placeholder="contient…" />
        </div>
        <div className="command-actions">
          <button type="button" className="btn btn-primary" onClick={applyFilters}>Appliquer</button>
          <button type="button" className="btn btn-ghost" onClick={clearFilters}>Réinitialiser</button>
        </div>
      </div>

      <div className="saas-surface" style={{ borderRadius: '12px', padding: '1rem 1.25rem', marginBottom: '1rem', display: 'flex', flexWrap: 'wrap', gap: '1.5rem', alignItems: 'center', justifyContent: 'space-between', position: 'relative' }}>
        <div>
          <span style={{ color: 'var(--text-muted)', fontSize: '0.85rem' }}>Total revenus (filtre courant)</span>
          <div className="text-heading" style={{ fontSize: '1.35rem', fontWeight: 800, color: 'var(--primary-2)' }}>{formatDT(totalCharge)}</div>
        </div>
        <div>
          <span style={{ color: 'var(--text-muted)', fontSize: '0.85rem' }}>Lignes correspondantes</span>
          <div className="text-heading" style={{ fontSize: '1.2rem', fontWeight: 700 }}>{total.toLocaleString('fr-FR')}</div>
        </div>
        <div style={{ fontSize: '0.85rem', color: 'var(--text-muted)' }}>
          Page {currentPage}{lastPage ? ` / ${lastPage}` : ''}
        </div>
        <div style={{ marginLeft: 'auto' }}>
          <button
            type="button"
            onClick={handleExport}
            disabled={exportLoading || total === 0}
            className="btn"
            style={{
              background: exportLoading ? '#9ca3af' : '#16a34a',
              color: 'white',
              borderRadius: '8px',
              padding: '8px 16px',
              fontSize: '14px',
              fontWeight: '600',
              border: 'none',
              cursor: exportLoading || total === 0 ? 'not-allowed' : 'pointer',
              opacity: exportLoading || total === 0 ? 0.7 : 1,
              display: 'flex',
              alignItems: 'center',
              gap: '6px',
              transition: 'background 0.2s',
            }}
            onMouseEnter={(e) => {
              if (!exportLoading && total > 0) e.target.style.background = '#15803d';
            }}
            onMouseLeave={(e) => {
              if (!exportLoading && total > 0) e.target.style.background = '#16a34a';
            }}
          >
            {exportLoading ? (
              <>
                <span style={{ animation: 'spin 1s linear infinite', display: 'inline-block' }}>⟳</span>
                Export en cours...
              </>
            ) : (
              <>
                <span>⬇</span>
                Exporter Excel
              </>
            )}
          </button>
          <span style={{ fontSize: '0.75rem', color: 'var(--text-muted)', display: 'block', marginTop: '0.25rem', textAlign: 'center' }}>Max 10 000 lignes</span>
        </div>
        
        {/* Job Status Dropdown */}
        <JobStatusBar
          jobTypes={['etl_agg_from_raw', 'etl_cdr_from_tmp', 'import_occ_csv', 'notifications_polling']}
          title=""
          compact={false}
          refreshInterval={10000}
          mode="dropdown"
        />
      </div>

      {error && (
        <div style={{ padding: '0.75rem 1rem', borderRadius: '8px', marginBottom: '1rem', background: 'rgba(198, 40, 40, 0.1)', border: '1px solid rgba(198, 40, 40, 0.35)', color: 'var(--text-main)' }}>
          {error}
        </div>
      )}

      {exportError && (
        <div style={{ padding: '0.75rem 1rem', borderRadius: '8px', marginBottom: '1rem', background: 'rgba(198, 40, 40, 0.1)', border: '1px solid rgba(198, 40, 40, 0.35)', color: 'var(--text-main)' }}>
          Erreur export : {exportError}
        </div>
      )}

      <div className="panel table-wrap" style={{ overflow: 'auto' }}>
        {loading ? (
          <p style={{ textAlign: 'center', padding: '2rem', color: 'var(--text-muted)' }}>Chargement…</p>
        ) : (
          <table className="table-mobile table-dense" style={{ width: '100%', borderCollapse: 'collapse' }}>
            <thead>
              <tr>
                {cols.map((c) => (
                  <th key={c.key} style={{ padding: '0.65rem 0.75rem', textAlign: 'left', fontSize: '0.78rem', color: 'var(--text-muted)', fontWeight: 600, borderBottom: '2px solid var(--border)', whiteSpace: 'nowrap' }}>
                    {c.label}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {data.length === 0 ? (
                <tr>
                  <td colSpan={cols.length} style={{ padding: '2rem', textAlign: 'center', color: 'var(--text-muted)' }}>Aucune ligne</td>
                </tr>
              ) : (
                data.map((row) => (
                  <tr key={row.id}>
                    {cols.map((c) => (
                      <td key={c.key} data-label={c.label} className={c.key.includes('msisdn') ? 'mono' : ''} style={{ padding: '0.55rem 0.75rem', fontSize: '0.82rem', color: 'var(--text-main)' }}>
                        {c.key === 'charge_amount' ? formatDT(row.charge_amount) : (
                          c.key === 'keyword' ? (
                            <div style={{ display: 'flex', flexDirection: 'column', gap: '2px' }}>
                              <span style={{ fontWeight: 600, fontSize: '13px' }}>
                                {getNom(row.keyword)}
                              </span>
                              <span style={{ fontSize: '11px', color: '#94a3b8', fontFamily: 'monospace' }}>
                                {row.keyword}
                              </span>
                            </div>
                          ) : (row[c.key] ?? '—')
                        )}
                      </td>
                    ))}
                  </tr>
                ))
              )}
            </tbody>
          </table>
        )}
      </div>

      {lastPage > 1 && (
        <div style={{ display: 'flex', justifyContent: 'center', gap: '0.5rem', marginTop: '1rem', flexWrap: 'wrap', alignItems: 'center' }}>
          <button type="button" className="btn btn-ghost" disabled={currentPage <= 1} onClick={() => goPage(1)}>« Première</button>
          <button type="button" className="btn btn-ghost" disabled={currentPage <= 1} onClick={() => goPage(currentPage - 1)}>‹ Préc.</button>
          <span style={{ padding: '0 0.5rem', fontSize: '0.9rem', color: 'var(--text-muted)' }}>{currentPage} / {lastPage}</span>
          <button type="button" className="btn btn-ghost" disabled={currentPage >= lastPage} onClick={() => goPage(currentPage + 1)}>Suiv. ›</button>
          <button type="button" className="btn btn-ghost" disabled={currentPage >= lastPage} onClick={() => goPage(lastPage)}>Dernière »</button>
        </div>
      )}
    </div>
  );
}
