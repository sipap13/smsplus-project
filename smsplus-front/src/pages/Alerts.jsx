/* eslint-disable react/prop-types */
import { useEffect, useState } from 'react';
import api from '../api/axios';
import { downloadExcel } from '../api/excelDownload';
import Modal from '../components/Modal';

const emptyAlertForm = () => ({
  start_date: new Date().toISOString().slice(0, 10),
  nom_service: '',
  numero_court: '',
  keyword: '',
  nom_fournisseur: '',
  seuil_pct: '',
  count_nb_sms: '',
  motif: '',
});

export default function Alerts() {
  const [alerts, setAlerts] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [filter, setFilter] = useState('all');
  const [usageHigh, setUsageHigh] = useState({ meta: null, items: [] });
  const [usageSource, setUsageSource] = useState('occ_agg');
  const [usageMetric, setUsageMetric] = useState('traffic');
  const [usageMinCount, setUsageMinCount] = useState(50);
  const [usageThresholdPct, setUsageThresholdPct] = useState(20);
  const [usageLoading, setUsageLoading] = useState(false);
  const [showForm, setShowForm] = useState(false);
  const [form, setForm] = useState(emptyAlertForm);
  const [msg, setMsg] = useState('');
  const [saving, setSaving] = useState(false);
  const [exportLoading, setExportLoading] = useState(false);
  const [exportError, setExportError] = useState('');

  const load = () => {
    setLoading(true);
    setError('');
    api
      .get('/alerts')
      .then((r) => {
        setAlerts(r.data);
        setLoading(false);
      })
      .catch(() => {
        setError("Impossible de charger les alertes. Vérifie l'API ou tes droits (ADMIN / ANALYSTE_OP).");
        setAlerts([]);
        setLoading(false);
      });
  };

  useEffect(() => {
    load();
  }, []);

  useEffect(() => {
    let mounted = true;
    setUsageLoading(true);
    const minCount = Number.isFinite(+usageMinCount) ? Math.max(0, parseInt(usageMinCount, 10) || 0) : 0;
    const thrPct = Number.isFinite(+usageThresholdPct) ? Math.max(0, Math.min(500, parseFloat(usageThresholdPct) || 0)) : 0;
    const thr = thrPct / 100;
    api
      .get(`/fraud/usage-high?source=${usageSource}&metric=${usageMetric}&threshold=${encodeURIComponent(thr)}&min_count=${encodeURIComponent(minCount)}`)
      .then((r) => {
        if (!mounted) return;
        setUsageHigh(r.data);
      })
      .catch(() => {
        if (!mounted) return;
        setUsageHigh({ meta: null, items: [] });
      })
      .finally(() => {
        if (mounted) setUsageLoading(false);
      });
    return () => { mounted = false; };
  }, [usageSource, usageMetric, usageMinCount, usageThresholdPct]);

  const filtered =
    filter === 'all' ? alerts : alerts.filter((a) => (filter === 'resolue' ? a.status : !a.status));

  const resolveOne = async (id) => {
    if (!window.confirm('Marquer cette alerte comme résolue ?')) return;
    try {
      await api.put(`/alerts/${id}`, { status: true });
      setMsg('Alerte résolue');
      load();
    } catch {
      setMsg('Impossible de résoudre cette alerte');
    }
    setTimeout(() => setMsg(''), 3000);
  };

  const submitNew = async () => {
    setSaving(true);
    try {
      const body = {
        start_date: form.start_date,
        nom_service: form.nom_service || null,
        numero_court: form.numero_court || null,
        keyword: form.keyword || null,
        nom_fournisseur: form.nom_fournisseur || null,
        motif: form.motif || null,
      };
      if (form.seuil_pct !== '' && form.seuil_pct != null) {
        body.seuil_pct = parseFloat(form.seuil_pct, 10);
      }
      if (form.count_nb_sms !== '' && form.count_nb_sms != null) {
        body.count_nb_sms = parseInt(form.count_nb_sms, 10);
      }
      await api.post('/alerts', body);
      setMsg('Alerte créée');
      setShowForm(false);
      setForm(emptyAlertForm());
      load();
    } catch {
      setMsg("Erreur à la création (champs obligatoires : date)");
    } finally {
      setSaving(false);
    }
    setTimeout(() => setMsg(''), 4000);
  };

  const ouvertes = alerts.filter((a) => !a.status).length;
  const resolues = alerts.filter((a) => a.status).length;
  const bannerOk = !/erreur|impossible/i.test(msg);

  const handleExport = async () => {
    await downloadExcel(
      '/export/alerts',
      {},
      `Alertes_${new Date().toISOString().slice(0, 10)}.xlsx`,
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

  return (
    <div className="page">
      <div className="page-header">
        <div>
          <h1 className="page-title">Alertes fraude</h1>
          <p className="page-subtitle">Surveillance des anomalies de trafic SMS+ — ADMIN & ANALYSTE_OP</p>
        </div>
        <div style={{ display: 'flex', gap: '0.5rem' }}>
          <button
            type="button"
            onClick={handleExport}
            disabled={exportLoading || alerts.length === 0}
            style={{
              background: exportLoading ? '#9ca3af' : '#16a34a',
              color: 'white',
              borderRadius: '8px',
              padding: '8px 16px',
              fontSize: '14px',
              fontWeight: '600',
              border: 'none',
              cursor: exportLoading || alerts.length === 0 ? 'not-allowed' : 'pointer',
              opacity: exportLoading || alerts.length === 0 ? 0.7 : 1,
              display: 'flex',
              alignItems: 'center',
              gap: '6px',
              transition: 'background 0.2s',
            }}
            onMouseEnter={(e) => {
              if (!exportLoading && alerts.length > 0) e.target.style.background = '#15803d';
            }}
            onMouseLeave={(e) => {
              if (!exportLoading && alerts.length > 0) e.target.style.background = '#16a34a';
            }}
          >
            {exportLoading ? (
              <>
                <span style={{ animation: 'spin 1s linear infinite', display: 'inline-block' }}>⟳</span>
                En cours...
              </>
            ) : (
              <>
                <span>⬇</span>
                Excel
              </>
            )}
          </button>
          <button type="button" onClick={() => { setForm(emptyAlertForm()); setShowForm(true); }} className="btn btn-primary">
            Ajouter une alerte
          </button>
        </div>
      </div>

      {msg && (
        <div
          style={{
            padding: '0.75rem 1rem',
            borderRadius: '8px',
            marginBottom: '1rem',
            background: bannerOk ? 'rgba(46, 125, 50, 0.12)' : 'rgba(198, 40, 40, 0.12)',
            color: 'var(--text-main)',
            border: `1px solid ${bannerOk ? 'rgba(46, 125, 50, 0.35)' : 'rgba(198, 40, 40, 0.35)'}`,
          }}
        >
          {msg}
        </div>
      )}
      {error && (
        <div
          style={{
            padding: '0.75rem 1rem',
            borderRadius: '8px',
            marginBottom: '1rem',
            background: 'rgba(245, 158, 11, 0.12)',
            color: 'var(--text-main)',
            border: '1px solid rgba(245, 158, 11, 0.35)',
          }}
        >
          {error}
        </div>
      )}

      {exportError && (
        <div
          style={{
            padding: '0.75rem 1rem',
            borderRadius: '8px',
            marginBottom: '1rem',
            background: 'rgba(198, 40, 40, 0.1)',
            color: 'var(--text-main)',
            border: '1px solid rgba(198, 40, 40, 0.35)',
          }}
        >
          Erreur export : {exportError}
        </div>
      )}

      <div className="kpi-grid-3" style={{ marginBottom: '1.2rem' }}>
        {[
          { label: 'Total alertes', value: alerts.length, color: '#1a237e', bg: 'rgba(26, 35, 126, 0.12)', icon: 'AL' },
          { label: 'Ouvertes', value: ouvertes, color: '#e65100', bg: 'rgba(230, 81, 0, 0.12)', icon: 'OP' },
          { label: 'Résolues', value: resolues, color: '#2e7d32', bg: 'rgba(46, 125, 50, 0.12)', icon: 'OK' },
        ].map((k) => (
          <div key={k.label} className="kpi-card" style={{ padding: '1.5rem', display: 'flex', alignItems: 'center', gap: '1rem' }}>
            <div
              style={{
                width: '50px',
                height: '50px',
                borderRadius: '12px',
                background: k.bg,
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                fontSize: '1.4rem',
              }}
            >
              {k.icon}
            </div>
            <div>
              <p style={{ margin: 0, color: 'var(--text-muted)', fontSize: '0.85rem' }}>{k.label}</p>
              <h3 className="text-heading" style={{ margin: '0.2rem 0 0', fontSize: '1.6rem', fontWeight: 800 }}>
                {k.value}
              </h3>
            </div>
          </div>
        ))}
      </div>

      <div className="surface surface-pad" style={{ marginBottom: '1.2rem' }}>
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', gap: '1rem', marginBottom: '0.9rem' }}>
          <div>
            <h3 className="text-heading" style={{ margin: 0, fontSize: '1.05rem', fontWeight: 800 }}>Usage élevé par service (détection +20% / 7 jours)</h3>
            <p style={{ margin: '0.25rem 0 0', color: 'var(--text-muted)', fontSize: '0.85rem' }}>
              {usageHigh?.meta?.anchor_date ? `Date analysée : ${usageHigh.meta.anchor_date}` : 'Date analysée : —'}
              {usageHigh?.meta?.source ? ` — source : ${usageHigh.meta.source}` : ''}
              {usageHigh?.meta?.metric ? ` — metric : ${usageHigh.meta.metric}` : ''}
            </p>
          </div>
        </div>

        <div className="command-bar" style={{ marginBottom: '0.85rem' }}>
          <div className="field">
            <div className="field-label">Source</div>
            <select className="field-control" value={usageSource} onChange={(e) => setUsageSource(e.target.value)}>
              <option value="occ_agg">OCC (AGG)</option>
              <option value="mmg_agg">MMG (AGG)</option>
              <option value="occ">OCC (détail)</option>
              <option value="mmg">MMG (détail)</option>
            </select>
          </div>
          <div className="field">
            <div className="field-label">Metric</div>
            <select
              className="field-control"
              value={usageMetric}
              onChange={(e) => setUsageMetric(e.target.value)}
              disabled={usageSource !== 'occ_agg'}
              title={usageSource !== 'occ_agg' ? 'Disponible uniquement pour OCC (AGG)' : ''}
            >
              <option value="traffic">Trafic</option>
              <option value="revenue">Revenus</option>
            </select>
          </div>
          <div className="field">
            <div className="field-label">min_count</div>
            <input
              className="field-control"
              type="number"
              min="0"
              step="1"
              value={usageMinCount}
              onChange={(e) => setUsageMinCount(e.target.value)}
              title="Volume minimum du jour pour considérer le service"
            />
          </div>
          <div className="field">
            <div className="field-label">threshold (%)</div>
            <input
              className="field-control"
              type="number"
              min="0"
              step="0.1"
              value={usageThresholdPct}
              onChange={(e) => setUsageThresholdPct(e.target.value)}
              title="Augmentation vs moyenne des 7 jours précédents"
            />
          </div>
        </div>

        {usageLoading ? (
          <p style={{ textAlign: 'center', color: 'var(--text-muted)', padding: '1.5rem 0' }}>Chargement…</p>
        ) : (usageHigh?.items?.length || 0) === 0 ? (
          <p style={{ textAlign: 'center', color: 'var(--text-muted)', padding: '1.5rem 0' }}>Aucune anomalie détectée sur cette période</p>
        ) : (
          <div className="panel table-wrap" style={{ overflow: 'auto' }}>
            <table className="table-mobile table-dense" style={{ width: '100%', borderCollapse: 'collapse' }}>
              <thead>
                <tr>
                  {['Service', 'Volume (jour)', 'Moy. 7 jours', 'Augmentation', 'Statut'].map((h) => (
                    <th
                      key={h}
                      style={{
                        padding: '0.85rem 1rem',
                        textAlign: 'left',
                        fontSize: '0.82rem',
                        color: 'var(--text-muted)',
                        borderBottom: '1px solid var(--border)',
                        background: 'var(--table-head-bg)',
                        position: 'sticky',
                        top: 0,
                        zIndex: 1,
                      }}
                    >
                      {h}
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {usageHigh.items.slice(0, 20).map((it) => {
                  const pct = it.pct_increase == null ? null : (it.pct_increase * 100);
                  const isFlag = !!it.flag;
                  return (
                    <tr key={it.service_key}>
                      <td style={{ padding: '0.85rem 1rem', borderBottom: '1px solid var(--border)', color: 'var(--text-main)', fontWeight: 700 }}>
                        {it.service_key}
                      </td>
                      <td style={{ padding: '0.85rem 1rem', borderBottom: '1px solid var(--border)', color: 'var(--text-main)' }}>
                        {it.vol_curr}
                      </td>
                      <td style={{ padding: '0.85rem 1rem', borderBottom: '1px solid var(--border)', color: 'var(--text-main)' }}>
                        {Number.isFinite(it.avg_prev_7d) ? it.avg_prev_7d.toFixed(1) : '—'}
                      </td>
                      <td style={{ padding: '0.85rem 1rem', borderBottom: '1px solid var(--border)', color: isFlag ? 'var(--danger)' : 'var(--text-muted)', fontWeight: 800 }}>
                        {pct == null ? '∞' : `${pct.toFixed(1)}%`}
                      </td>
                      <td style={{ padding: '0.85rem 1rem', borderBottom: '1px solid var(--border)' }}>
                        <span className={`badge ${isFlag ? 'badge-danger' : 'badge-ok'}`}>
                          {isFlag ? 'Anomalie' : 'Normal'}
                        </span>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        )}
      </div>

      <div className="toolbar toolbar-sticky-mobile" style={{ marginBottom: '1rem' }}>
        {[
          ['all', 'Toutes'],
          ['ouverte', 'Ouvertes'],
          ['resolue', 'Résolues'],
        ].map(([val, label]) => (
          <button
            type="button"
            key={val}
            onClick={() => setFilter(val)}
            className="btn btn-pill"
            style={{
              background: filter === val ? 'var(--primary)' : 'var(--chip-bg)',
              color: filter === val ? '#fff' : 'var(--text-muted)',
              fontSize: '0.88rem',
            }}
          >
            {label}
          </button>
        ))}
      </div>

      {showForm && (
        <Modal title="Nouvelle alerte" onClose={() => setShowForm(false)} wide>
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(220px, 1fr))', gap: '1rem' }}>
            <div>
              <label style={{ display: 'block', marginBottom: '0.35rem', fontWeight: 600, fontSize: '0.85rem', color: 'var(--text-main)' }}>
                Date *
              </label>
              <input
                type="date"
                value={form.start_date}
                onChange={(e) => setForm({ ...form, start_date: e.target.value })}
                style={{ width: '100%', padding: '0.65rem', boxSizing: 'border-box' }}
              />
            </div>
            {[
              { key: 'nom_service', label: 'Nom service' },
              { key: 'numero_court', label: 'N° court' },
              { key: 'keyword', label: 'Keyword' },
              { key: 'nom_fournisseur', label: 'Fournisseur' },
              { key: 'seuil_pct', label: 'Seuil %', type: 'number' },
              { key: 'count_nb_sms', label: 'Nb SMS', type: 'number' },
            ].map((f) => (
              <div key={f.key}>
                <label style={{ display: 'block', marginBottom: '0.35rem', fontWeight: 600, fontSize: '0.85rem', color: 'var(--text-main)' }}>
                  {f.label}
                </label>
                <input
                  type={f.type || 'text'}
                  value={form[f.key]}
                  onChange={(e) => setForm({ ...form, [f.key]: e.target.value })}
                  style={{ width: '100%', padding: '0.65rem', boxSizing: 'border-box' }}
                />
              </div>
            ))}
          </div>
          <div style={{ marginTop: '1rem' }}>
            <label style={{ display: 'block', marginBottom: '0.35rem', fontWeight: 600, fontSize: '0.85rem', color: 'var(--text-main)' }}>
              Motif
            </label>
            <textarea
              value={form.motif}
              onChange={(e) => setForm({ ...form, motif: e.target.value })}
              rows={3}
              style={{ width: '100%', padding: '0.65rem', boxSizing: 'border-box', resize: 'vertical' }}
            />
          </div>
          <div style={{ display: 'flex', gap: '0.75rem', justifyContent: 'flex-end', marginTop: '1.25rem' }}>
            <button type="button" onClick={() => setShowForm(false)} className="btn btn-ghost" disabled={saving}>
              Annuler
            </button>
            <button type="button" onClick={submitNew} className="btn btn-primary" disabled={saving}>
              {saving ? '…' : 'Créer'}
            </button>
          </div>
        </Modal>
      )}

      {loading ? (
        <p style={{ textAlign: 'center', color: 'var(--text-muted)', padding: '3rem' }}>Chargement...</p>
      ) : (
        <div className="panel table-wrap" style={{ overflow: 'auto' }}>
          <table className="table-mobile" style={{ width: '100%', borderCollapse: 'collapse' }}>
            <thead>
              <tr>
                {['ID', 'Statut', 'Date', 'Service', 'Keyword', 'Fournisseur', 'N° court', 'Seuil %', 'Nb SMS', 'Motif', 'Actions'].map((h) => (
                  <th
                    key={h}
                    style={{
                      padding: '0.85rem 1rem',
                      textAlign: 'left',
                      fontSize: '0.82rem',
                      color: 'var(--text-muted)',
                      fontWeight: 600,
                      borderBottom: '2px solid var(--border)',
                      whiteSpace: h === 'Motif' ? 'normal' : 'nowrap',
                    }}
                  >
                    {h}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {filtered.length === 0 ? (
                <tr>
                  <td colSpan={11} style={{ padding: '2rem', textAlign: 'center', color: 'var(--text-muted)' }}>
                    Aucune alerte dans cette catégorie
                  </td>
                </tr>
              ) : (
                filtered.map((alert) => (
                  <tr key={alert.id}>
                    <td data-label="ID" style={{ padding: '0.75rem 1rem', color: 'var(--text-muted)', fontFamily: 'monospace' }}>
                      {alert.id}
                    </td>
                    <td data-label="Statut" style={{ padding: '0.75rem 1rem' }}>
                      <span className={`status-badge ${alert.status ? 'status-up' : 'status-degraded'}`}>
                        <span className="status-dot" />
                        {alert.status ? 'Résolue' : 'Ouverte'}
                      </span>
                    </td>
                    <td data-label="Date" style={{ padding: '0.75rem 1rem', color: 'var(--text-main)' }}>
                      {alert.start_date}
                    </td>
                    <td data-label="Service" style={{ padding: '0.75rem 1rem', fontWeight: 600, color: 'var(--text-main)' }}>
                      {alert.nom_service || '—'}
                    </td>
                    <td data-label="Keyword" style={{ padding: '0.75rem 1rem' }}>
                      {alert.keyword ? <span className="chip">{alert.keyword}</span> : '—'}
                    </td>
                    <td data-label="Fournisseur" style={{ padding: '0.75rem 1rem' }}>
                      {alert.nom_fournisseur || '—'}
                    </td>
                    <td data-label="N° court" style={{ padding: '0.75rem 1rem', fontFamily: 'monospace' }}>
                      {alert.numero_court || '—'}
                    </td>
                    <td data-label="Seuil" style={{ padding: '0.75rem 1rem' }}>
                      {alert.seuil_pct != null ? `${parseFloat(alert.seuil_pct).toFixed(2)} %` : '—'}
                    </td>
                    <td data-label="Nb SMS" style={{ padding: '0.75rem 1rem' }}>
                      {alert.count_nb_sms != null ? parseInt(alert.count_nb_sms, 10).toLocaleString('fr-FR') : '—'}
                    </td>
                    <td data-label="Motif" style={{ padding: '0.75rem 1rem', maxWidth: '280px', fontSize: '0.88rem', color: 'var(--text-muted)' }}>
                      {alert.motif || '—'}
                    </td>
                    <td data-label="Actions" style={{ padding: '0.75rem 1rem' }}>
                      {!alert.status && (
                        <button
                          type="button"
                          onClick={() => resolveOne(alert.id)}
                          style={{
                            padding: '0.45rem 0.85rem',
                            background: 'rgba(46, 125, 50, 0.12)',
                            color: 'var(--success)',
                            border: '1px solid rgba(46, 125, 50, 0.35)',
                            borderRadius: '8px',
                            cursor: 'pointer',
                            fontWeight: 600,
                            fontSize: '0.82rem',
                            whiteSpace: 'nowrap',
                          }}
                        >
                          Résoudre
                        </button>
                      )}
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}
