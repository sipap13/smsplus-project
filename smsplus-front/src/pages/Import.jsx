

import { useState, useEffect, useRef, useCallback } from 'react';
import api from '../api/axios';
import JobStatusBar from '../components/JobStatusBar';

/* ───────── Helpers ───────── */
function formatBytes(bytes) {
  if (bytes === 0) return '0 B';
  const k = 1024;
  const sizes = ['B', 'KB', 'MB', 'GB'];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return parseFloat((bytes / k ** i).toFixed(2)) + ' ' + sizes[i];
}

function formatDuration(seconds) {
  if (!seconds && seconds !== 0) return '—';
  if (seconds < 60) return `${seconds}s`;
  const m = Math.floor(seconds / 60);
  const s = seconds % 60;
  return `${m}m ${s}s`;
}

function statusLabel(status) {
  const map = {
    pending: 'En attente',
    processing: 'En cours',
    done: 'Terminé',
    error: 'Erreur',
  };
  return map[status] || status;
}

function statusBadgeClass(status) {
  const map = {
    pending: 'badge-pending',
    processing: 'badge-processing',
    done: 'badge-done',
    error: 'badge-error',
  };
  return `import-status-badge ${map[status] || ''}`;
}

/* ───────── Skeletons ───────── */
function SkeletonCard() {
  return (
    <div className="surface surface-pad" style={{ marginBottom: '1.2rem' }}>
      <div className="skeleton" style={{ height: 16, width: '40%', marginBottom: '1rem' }} />
      <div className="skeleton" style={{ height: 10, width: '90%', marginBottom: '0.5rem' }} />
      <div className="skeleton" style={{ height: 10, width: '70%' }} />
    </div>
  );
}

function SkeletonHistory() {
  return (
    <div className="surface surface-pad">
      <div className="skeleton" style={{ height: 16, width: '30%', marginBottom: '1rem' }} />
      <div className="skeleton" style={{ height: 12, width: '100%', marginBottom: '0.5rem' }} />
      <div className="skeleton" style={{ height: 12, width: '100%', marginBottom: '0.5rem' }} />
      <div className="skeleton" style={{ height: 12, width: '100%' }} />
    </div>
  );
}

/* ───────── DropZone ───────── */
function DropZone({ onFileSelect, disabled }) {
  const [isOver, setIsOver] = useState(false);
  const inputRef = useRef(null);

  const handleDragOver = useCallback((e) => {
    e.preventDefault();
    setIsOver(true);
  }, []);

  const handleDragLeave = useCallback((e) => {
    e.preventDefault();
    setIsOver(false);
  }, []);

  const handleDrop = useCallback((e) => {
    e.preventDefault();
    setIsOver(false);
    const files = e.dataTransfer.files;
    if (files && files[0]) {
      onFileSelect(files[0]);
    }
  }, [onFileSelect]);

  const handleChange = (e) => {
    const files = e.target.files;
    if (files && files[0]) {
      onFileSelect(files[0]);
    }
  };

  return (
    <div
      className={`import-drop-zone ${isOver ? 'drag-over' : ''} ${disabled ? 'disabled' : ''}`}
      onDragOver={handleDragOver}
      onDragLeave={handleDragLeave}
      onDrop={handleDrop}
      onClick={() => !disabled && inputRef.current?.click()}
      role="button"
      tabIndex={0}
      onKeyDown={(e) => {
        if (e.key === 'Enter' || e.key === ' ') {
          !disabled && inputRef.current?.click();
        }
      }}
    >
      <input
        ref={inputRef}
        type="file"
        accept=".csv,.xlsx,.xls"
        style={{ display: 'none' }}
        onChange={handleChange}
        disabled={disabled}
      />
      <div className="import-drop-icon">📤</div>
      <p className="import-drop-text">Glissez votre fichier CSV/Excel ici</p>
      <p className="import-drop-sub">ou cliquez pour sélectionner</p>
      <p className="import-drop-hint">Formats acceptés : .csv .xlsx .xls — Max 50MB</p>
    </div>
  );
}

/* ───────── FilePreview ───────── */
function FilePreview({ file, onCancel }) {
  return (
    <div className="import-file-preview">
      <div className="import-file-info">
        <div className="import-file-name">{file.name}</div>
        <div className="import-file-meta">
          {formatBytes(file.size)} • {file.type || 'Fichier'}
        </div>
      </div>
      <button className="btn btn-sm btn-ghost" onClick={onCancel} type="button">
        Annuler
      </button>
    </div>
  );
}

/* ───────── TypeSelector ───────── */
function TypeSelector({ value, onChange, disabled }) {
  return (
    <div className="import-type-selector">
      <label className={`import-type-option ${value === 'occ' ? 'selected' : ''} ${disabled ? 'disabled' : ''}`}>
        <input
          type="radio"
          name="import_type"
          value="occ"
          checked={value === 'occ'}
          onChange={() => onChange('occ')}
          disabled={disabled}
        />
        <span className="import-type-label">OCC</span>
        <span className="import-type-desc">CDR OCC — Revenus &amp; Charges</span>
      </label>
      <label className={`import-type-option ${value === 'mmg' ? 'selected' : ''} ${disabled ? 'disabled' : ''}`}>
        <input
          type="radio"
          name="import_type"
          value="mmg"
          checked={value === 'mmg'}
          onChange={() => onChange('mmg')}
          disabled={disabled}
        />
        <span className="import-type-label">MMG</span>
        <span className="import-type-desc">CDR MMG — Volumes &amp; Services</span>
      </label>
    </div>
  );
}

/* ───────── ProgressCard ───────── */
function ProgressCard({ importState }) {
  const { status, percentage, imported_rows, total_rows, error_rows, elapsed_seconds, error_message } = importState;

  return (
    <div className="surface surface-pad import-progress-card">
      <h3 className="text-heading" style={{ margin: '0 0 1rem', fontSize: '1.05rem', fontWeight: 700 }}>
        Progression
      </h3>

      <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem', marginBottom: '1rem' }}>
        <span className={statusBadgeClass(status)}>{statusLabel(status)}</span>
        {status === 'processing' && (
          <span style={{ fontSize: '0.8rem', color: 'var(--text-muted)' }}>Import en arrière-plan…</span>
        )}
      </div>

      <div className="import-progress-wrap">
        <div className={`import-progress-bar ${status === 'processing' ? 'striped' : ''}`} style={{ width: `${percentage}%` }} />
      </div>
      <div className="import-progress-text">{percentage}%</div>

      <div className="import-progress-stats">
        <div className="import-stat">
          <div className="import-stat-value">{imported_rows.toLocaleString('fr-FR')}</div>
          <div className="import-stat-label">Lignes importées</div>
        </div>
        <div className="import-stat">
          <div className="import-stat-value">{total_rows.toLocaleString('fr-FR')}</div>
          <div className="import-stat-label">Total</div>
        </div>
        <div className="import-stat">
          <div className="import-stat-value" style={{ color: '#dc2626' }}>{error_rows.toLocaleString('fr-FR')}</div>
          <div className="import-stat-label">Erreurs</div>
        </div>
        <div className="import-stat">
          <div className="import-stat-value">{formatDuration(elapsed_seconds)}</div>
          <div className="import-stat-label">Durée</div>
        </div>
      </div>

      {status === 'done' && (
        <div className="import-message import-message-success">
          ✅ {imported_rows.toLocaleString('fr-FR')} lignes importées avec succès
        </div>
      )}
      {status === 'error' && (
        <div className="import-message import-message-error">
          ❌ Erreur : {error_message || 'Une erreur est survenue'}
        </div>
      )}
    </div>
  );
}

/* ───────── HistoryTable ───────── */
function HistoryTable({ imports, onDelete, loading, page, perPage, total, onPageChange }) {
  const lastPage = Math.max(1, Math.ceil(total / perPage));

  return (
    <div className="surface surface-pad">
      <div style={{ marginBottom: '1rem', display: 'flex', alignItems: 'center', justifyContent: 'space-between', flexWrap: 'wrap', gap: '0.5rem' }}>
        <h3 className="text-heading" style={{ margin: 0, fontSize: '1.05rem', fontWeight: 700 }}>
          Historique des imports
        </h3>
        <span style={{ fontSize: '0.8rem', color: 'var(--text-muted)' }}>{total} import(s)</span>
      </div>

      {loading ? (
        <SkeletonHistory />
      ) : imports.length === 0 ? (
        <div className="empty-state" style={{ padding: '2rem 0' }}>
          <p>Aucun import enregistré</p>
        </div>
      ) : (
        <>
          <div className="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Date</th>
                  <th>Fichier</th>
                  <th>Type</th>
                  <th>Lignes</th>
                  <th>Erreurs</th>
                  <th>Durée</th>
                  <th>Statut</th>
                  <th>Par</th>
                  <th style={{ width: 48 }} />
                </tr>
              </thead>
              <tbody>
                {imports.map((imp) => {
                  const elapsed = imp.started_at && imp.finished_at
                    ? Math.round((new Date(imp.finished_at) - new Date(imp.started_at)) / 1000)
                    : null;
                  return (
                    <tr key={imp.id}>
                      <td>{new Date(imp.created_at).toLocaleString('fr-FR')}</td>
                      <td style={{ maxWidth: 220, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }} title={imp.filename}>
                        {imp.filename}
                      </td>
                      <td><span className="badge">{imp.type.toUpperCase()}</span></td>
                      <td>{imp.imported_rows.toLocaleString('fr-FR')}</td>
                      <td style={{ color: imp.error_rows > 0 ? '#dc2626' : 'inherit', fontWeight: imp.error_rows > 0 ? 700 : 400 }}>
                        {imp.error_rows.toLocaleString('fr-FR')}
                      </td>
                      <td>{formatDuration(elapsed)}</td>
                      <td><span className={statusBadgeClass(imp.status)}>{statusLabel(imp.status)}</span></td>
                      <td>{imp.imported_by || '—'}</td>
                      <td>
                        <button
                          className="btn btn-sm btn-ghost"
                          style={{ color: '#dc2626' }}
                          onClick={() => onDelete(imp.id)}
                          type="button"
                          title="Supprimer"
                        >
                          🗑
                        </button>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>

          {lastPage > 1 && (
            <div className="import-pagination">
              <button className="btn btn-sm" disabled={page <= 1} onClick={() => onPageChange(page - 1)} type="button">
                ‹ Précédent
              </button>
              <span style={{ fontSize: '0.85rem', color: 'var(--text-muted)' }}>
                Page {page} / {lastPage}
              </span>
              <button className="btn btn-sm" disabled={page >= lastPage} onClick={() => onPageChange(page + 1)} type="button">
                Suivant ›
              </button>
            </div>
          )}
        </>
      )}
    </div>
  );
}

/* ───────── Page ───────── */
export default function Import() {
  const [file, setFile] = useState(null);
  const [type, setType] = useState('');
  const [uploading, setUploading] = useState(false);
  const [activeImport, setActiveImport] = useState(null);
  const [importState, setImportState] = useState(null);
  const [history, setHistory] = useState([]);
  const [historyLoading, setHistoryLoading] = useState(true);
  const [historyPage, setHistoryPage] = useState(1);
  const perPage = 10;

  const loadHistory = async () => {
    setHistoryLoading(true);
    try {
      const res = await api.get('/imports/history');
      setHistory(res.data || []);
    } catch (err) {
      console.error('Erreur historique', err);
    } finally {
      setHistoryLoading(false);
    }
  };

  useEffect(() => {
    loadHistory();
  }, []);

  /* Polling statut actif */
  useEffect(() => {
    if (!activeImport) return;
    let cancelled = false;

    const poll = async () => {
      try {
        const res = await api.get(`/imports/${activeImport.id}/status`);
        if (cancelled) return;
        setImportState(res.data);
        if (res.data.status === 'done' || res.data.status === 'error') {
          loadHistory();
        }
      } catch (err) {
        console.error('Erreur polling', err);
      }
    };

    poll();
    const interval = setInterval(() => {
      if (!cancelled && importState?.status === 'processing') {
        poll();
      } else if (importState?.status === 'done' || importState?.status === 'error') {
        clearInterval(interval);
      }
    }, 2000);

    return () => { cancelled = true; clearInterval(interval); };
  }, [activeImport]);

  const handleUpload = async () => {
    if (!file || !type) return;
    setUploading(true);
    try {
      const formData = new FormData();
      formData.append('file', file);
      formData.append('type', type);
      const res = await api.post('/imports/upload', formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });
      setActiveImport({ id: res.data.import_id });
      setImportState({ status: 'pending', percentage: 0, imported_rows: 0, total_rows: 0, error_rows: 0, elapsed_seconds: null });
      setFile(null);
      setType('');
    } catch (err) {
      alert(err.response?.data?.message || "Erreur lors de l'upload");
    } finally {
      setUploading(false);
    }
  };

  const handleDelete = async (id) => {
    if (!confirm('Supprimer cet import ?')) return;
    try {
      await api.delete(`/imports/${id}`);
      loadHistory();
      if (activeImport?.id === id) {
        setActiveImport(null);
        setImportState(null);
      }
    } catch (err) {
      alert(err.response?.data?.message || 'Erreur lors de la suppression');
    }
  };

  const historySlice = history.slice((historyPage - 1) * perPage, historyPage * perPage);

  return (
    <div className="page" style={{ minHeight: '100%' }}>
      <div className="page-header tt-page-head" style={{ marginBottom: '1.2rem' }}>
        <div>
          <h1 className="page-title">Import de Données</h1>
          <p className="page-subtitle">Importez vos fichiers CDR OCC ou MMG via CSV/Excel</p>
        </div>
      </div>

      <div className="import-layout">
        {/* ── COLONNE GAUCHE ── */}
        <div className="import-col">
          <div className="surface surface-pad" style={{ marginBottom: '1.2rem' }}>
            <h3 className="text-heading" style={{ margin: '0 0 1rem', fontSize: '1.05rem', fontWeight: 700 }}>
              Fichier à importer
            </h3>
            {!file ? (
              <DropZone onFileSelect={setFile} disabled={uploading || activeImport?.status === 'processing'} />
            ) : (
              <FilePreview file={file} onCancel={() => setFile(null)} />
            )}

            <div style={{ marginTop: '1.2rem' }}>
              <h4 style={{ margin: '0 0 0.6rem', fontSize: '0.9rem', fontWeight: 600, color: 'var(--text-main)' }}>
                Type de données
              </h4>
              <TypeSelector
                value={type}
                onChange={setType}
                disabled={uploading || activeImport?.status === 'processing'}
              />
            </div>

            <button
              className="btn btn-primary import-launch-btn"
              onClick={handleUpload}
              disabled={!file || !type || uploading || activeImport?.status === 'processing'}
              type="button"
            >
              {uploading ? 'Envoi en cours…' : 'Lancer l\'import'}
            </button>
          </div>
        </div>

        {/* ── COLONNE DROITE ── */}
        <div className="import-col">
          {importState ? (
            <ProgressCard importState={importState} />
          ) : (
            <div className="surface surface-pad import-progress-card">
              <h3 className="text-heading" style={{ margin: '0 0 1rem', fontSize: '1.05rem', fontWeight: 700 }}>
                Progression
              </h3>
              <div className="empty-state" style={{ padding: '2rem 0' }}>
                <p>Aucun import en cours</p>
                <p style={{ fontSize: '0.85rem', color: 'var(--text-muted)', marginTop: '0.5rem' }}>
                  Sélectionnez un fichier et lancez un import pour voir la progression ici.
                </p>
              </div>
            </div>
          )}
        </div>
      </div>

            {/* ── ETL MONITORING ── */}
      <JobStatusBar
        jobTypes={['import_occ_csv', 'import_occ_xlsx', 'import_mmg_csv', 'import_mmg_xlsx', 'etl_cdr_from_tmp', 'etl_agg_from_raw']}
        title="Traitements ETL"
        compact={false}
        refreshInterval={5000}
      />

      {/* ── HISTORIQUE ── */}
      <HistoryTable
        imports={historySlice}
        onDelete={handleDelete}
        loading={historyLoading}
        page={historyPage}
        perPage={perPage}
        total={history.length}
        onPageChange={setHistoryPage}
      />
    </div>
  );
}

