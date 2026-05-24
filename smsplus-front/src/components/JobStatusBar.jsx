 
import { useEffect, useState, useRef, useCallback } from 'react';
import api from '../api/axios';

/* ───────── Translation Maps ───────── */

const JOB_LABELS = {
  'etl_agg_from_raw': 'Agrégation CDR',
  'etl_cdr_from_tmp': 'Chargement CDR',
  'import_occ_csv': 'Import OCC',
  'import_mmg_csv': 'Import MMG',
  'import_occ_xlsx': 'Import OCC (Excel)',
  'import_mmg_xlsx': 'Import MMG (Excel)',
  'import_batch_process': 'Import batch',
  'cdr_occ_paginate': 'Lecture CDR OCC',
  'cdr_occ_filter': 'Filtrage CDR OCC',
  'cdr_mmg_paginate': 'Lecture CDR MMG',
  'cdr_mmg_filter': 'Filtrage CDR MMG',
  'export_occ_excel': 'Export OCC Excel',
  'export_mmg_excel': 'Export MMG Excel',
  'export_revenus_excel': 'Export Revenus Excel',
  'export_services_excel': 'Export Services Excel',
  'export_alertes_excel': 'Export Alertes Excel',
  'export_rapport_pdf': 'Génération PDF',
  'prediction_data_collect': 'Collecte historique',
  'prediction_metrics_calc': 'Calcul tendances',
  'prediction_groq_call': 'Analyse Groq IA',
  'prediction_cache_save': 'Mise en cache',
  'ai_chatbot': 'Chatbot IA',
  'ai_risk_score': 'Score risque MSISDN',
  'msisdn_search_occ': 'Recherche OCC',
  'msisdn_search_mmg': 'Recherche MMG',
  'msisdn_search_all': 'Recherche MSISDN',
  'msisdn_reclamations_search': 'Recherche réclamations',
  'msisdn_timeline_build': 'Construction timeline',
  'alerte_auto_detect': 'Détection anomalies',
  'alerte_create': 'Création alerte',
  'alerte_update': 'Mise à jour alerte',
  'alerte_resolve': 'Résolution alerte',
  'notification_send': 'Envoi notification',
  'notifications_load': 'Chargement notifications',
  'notifications_polling': 'Polling notifications',
  'notification_mark_read': 'Lecture notification',
  'notifications_mark_all_read': 'Tout lire',
  'dashboard_stats_load': 'Chargement KPIs',
  'dashboard_revenus_chart': 'Chargement revenus chart',
  'dashboard_anomaly_check': 'Vérification anomalies',
  'services_list_load': 'Chargement services',
  'service_create': 'Création service',
  'service_update': 'Modification service',
  'service_delete': 'Suppression service',
  'user_create': 'Création utilisateur',
  'user_update_role': 'Modification rôle',
  'user_login': 'Connexion',
  'user_2fa_verify': 'Vérification 2FA',
  'user_2fa_send': 'Envoi code 2FA',
  'etl_deduplicate': 'Suppression doublons',
  'report_pdf': 'Rapport PDF mensuel',
};

const JOB_ICONS = {
  'etl_agg_from_raw': '📊',
  'etl_cdr_from_tmp': '💾',
  'import_occ_csv': '📥',
  'import_mmg_csv': '📥',
  'import_batch_process': '📦',
  'cdr_occ_paginate': '🔍',
  'cdr_mmg_paginate': '🔍',
  'export_occ_excel': '📤',
  'export_mmg_excel': '📤',
  'export_rapport_pdf': '📄',
  'prediction_groq_call': '🤖',
  'ai_chatbot': '💬',
  'ai_risk_score': '🛡',
  'alerte_auto_detect': '⚠️',
  'alerte_create': '🚨',
  'alerte_update': '🔄',
  'notification_send': '🔔',
  'notifications_load': '📬',
  'notification_mark_read': '✅',
  'notifications_mark_all_read': '📖',
  'dashboard_stats_load': '📊',
  'dashboard_revenus_load': '💰',
  'services_list_load': '📋',
  'service_create': '➕',
  'service_update': '✏️',
  'service_delete': '🗑️',
  'user_create': '👤',
  'user_login': '🔑',
  'user_2fa_verify': '🔐',
  'user_2fa_send': '📱',
  'msisdn_search_all': '🔎',
  'msisdn_reclamations_search': '📝',
  'msisdn_timeline_build': '�',
  'etl_deduplicate': '🧹',
  'report_pdf': '📋',
};

/* ───────── Helpers ───────── */

const STATUS_ICONS = {
  success: '✓',
  failed: '✗',
  running: '🔄',
  pending: '⏸',
  timeout: '⏱',
};

const STATUS_COLORS = {
  success: '#16a34a',
  failed: '#dc2626',
  running: '#3b82f6',
  pending: '#94a3b8',
  timeout: '#f59e0b',
};

function getCategoryColor(jobName) {
  if (jobName.includes('import')) return '#3b82f6';
  if (jobName.includes('etl') || jobName.includes('agg')) return '#8b5cf6';
  if (jobName.includes('groq') || jobName.includes('prediction') ||
    jobName.includes('chatbot') || jobName.includes('risk')) return '#f59e0b';
  if (jobName.includes('export') || jobName.includes('report')) return '#10b981';
  if (jobName.includes('alerte') || jobName.includes('notif')) return '#ef4444';
  return '#64748b';
}

function formatMetric(job) {
  const meta = job.metadata || {};
  const lines = job.lignes_inserees || job.lignes_traitees || 0;

  // Si 0 lignes → affiche durée seulement
  if (lines === 0 && job.duration_ms) {
    return `Terminé en ${formatDuration(job.duration_ms)}`;
  }

  // Formatte avec séparateur milliers
  const formatted = lines.toLocaleString('fr-FR');

  if (job.job_name.includes('import')) {
    const ignored = job.lignes_ignorees || 0;
    const errors = job.lignes_erreur || 0;
    if (ignored > 0 || errors > 0) {
      return `${formatted} insérées · ${ignored} ignorées · ${errors} erreurs`;
    }
    return `${formatted} lignes importées`;
  }

  if (job.job_name.includes('export')) {
    return `${formatted} lignes exportées`;
  }

  if (job.job_name.includes('agg')) {
    return `${formatted} lignes agrégées`;
  }

  if (job.job_name.includes('groq') || job.job_name.includes('chatbot')) {
    const tokens = meta.tokens_output || 0;
    const duration = formatDuration(job.duration_ms);
    return tokens > 0
      ? `Répondu en ${duration} · ${tokens} tokens`
      : `Terminé en ${duration}`;
  }

  if (job.job_name.includes('risk')) {
    const score = meta.score || 0;
    return score > 0 ? `Score risque: ${score}/100` : 'Calculé';
  }

  return lines > 0 ? `${formatted} lignes traitées` : 'Terminé';
}

function formatDuration(ms) {
  if (!ms && ms !== 0) return '—';
  if (ms < 1000) return `${ms}ms`;
  const s = Math.round(ms / 1000);
  if (s < 60) return `${s}s`;
  const m = Math.floor(s / 60);
  const rs = s % 60;
  return `${m}min ${rs}s`;
}

function relativeTime(dateStr) {
  if (!dateStr || dateStr === 'NaN' ||
    dateStr === null || dateStr === undefined) {
    return '—';
  }
  try {
    const dt = new Date(dateStr);
    if (isNaN(dt.getTime())) return '—';
    const now = new Date();
    const diffSec = Math.round((now - dt) / 1000);
    if (diffSec < 0) return 'à l\'instant';
    if (diffSec < 60) return 'à l\'instant';
    if (diffSec < 3600) return `il y a ${Math.floor(diffSec / 60)}min`;
    if (diffSec < 86400) return `il y a ${Math.floor(diffSec / 3600)}h`;
    return `il y a ${Math.floor(diffSec / 86400)}j`;
  } catch {
    return '—';
  }
}

/* ───────── Sub-components ───────── */

function CompactBar({ jobs, title }) {
  if (!jobs || jobs.length === 0) return null;

  const runningJob = jobs.find((j) => j.is_running);
  const recentSuccess = jobs.filter((j) => j.status === 'success').slice(0, 2);
  const hasFailed = jobs.some((j) => j.status === 'failed');

  // Determine background color based on status
  let bgColor = 'rgba(59, 130, 246, 0.08)'; // default blue light
  let borderColor = '#3b82f6';

  if (hasFailed) {
    bgColor = 'rgba(220, 38, 38, 0.08)';
    borderColor = '#dc2626';
  } else if (runningJob) {
    bgColor = 'rgba(245, 158, 11, 0.08)';
    borderColor = '#f59e0b';
  }

  return (
    <div
      style={{
        height: '44px',
        display: 'flex',
        alignItems: 'center',
        gap: '0.75rem',
        padding: '0 16px',
        borderTop: `2px solid ${borderColor}`,
        background: bgColor,
        fontSize: '13px',
        color: 'var(--text-muted)',
      }}
    >
      <span style={{ fontWeight: 600, display: 'flex', alignItems: 'center', gap: '4px' }}>
        <span style={{ fontSize: '14px' }}>⚙</span>
        {title || 'Traitements'}
      </span>

      {runningJob ? (
        <div style={{ display: 'flex', alignItems: 'center', gap: '8px', color: STATUS_COLORS.running }}>
          <span
            style={{
              width: '10px',
              height: '10px',
              borderRadius: '50%',
              background: STATUS_COLORS.running,
              display: 'inline-block',
              animation: 'job-pulse 1.5s infinite',
            }}
          />
          <span style={{ fontWeight: 500 }}>
            {JOB_LABELS[runningJob.job_name] || runningJob.job_name}
          </span>
          <span>
            {runningJob.pourcentage > 0
              ? `${runningJob.pourcentage}% · ~${Math.ceil((100 - runningJob.pourcentage) / runningJob.pourcentage * 30)}s restantes`
              : 'En cours...'
            }
          </span>
        </div>
      ) : recentSuccess.length > 0 ? (
        <div style={{ display: 'flex', alignItems: 'center', gap: '12px' }}>
          {recentSuccess.map((j) => (
            <span key={j.id} style={{ display: 'flex', alignItems: 'center', gap: '4px' }}>
              <span style={{ color: STATUS_COLORS.success, fontSize: '14px' }}>
                {STATUS_ICONS.success}
              </span>
              <span style={{ fontWeight: 500 }}>
                {JOB_LABELS[j.job_name] || j.job_name}
              </span>
              <span style={{ opacity: 0.7 }}>
                · {formatMetric(j)} · {relativeTime(j.finished_at || j.started_at)}
              </span>
            </span>
          ))}
        </div>
      ) : (
        <span style={{ opacity: 0.6 }}>Aucun traitement récent</span>
      )}
    </div>
  );
}

function FullCard({ jobs, title, onClose }) {
  const [expanded, setExpanded] = useState(true);
  if (!jobs) return null;

  const nbSuccess = jobs.filter(j => j.status === 'success').length;
  const nbFailed = jobs.filter(j => j.status === 'failed').length;
  const lastJob = jobs[0];
  const summaryColor = nbFailed > 0 ? '#dc2626' : '#16a34a';
  const summaryIcon = nbFailed > 0 ? '⚠' : '✓';

  return (
    <div
      style={{
        borderRadius: '8px',
        border: '1px solid var(--border)',
        padding: '16px',
        background: 'var(--bg-surface)',
        marginTop: '1rem',
      }}
    >
      {/* Header */}
      <div
        style={{
          display: 'flex',
          justifyContent: 'space-between',
          alignItems: 'center',
          marginBottom: expanded && jobs.length > 0 ? '16px' : 0,
          cursor: 'pointer',
        }}
        onClick={() => setExpanded(!expanded)}
        role="button"
        tabIndex={0}
        onKeyDown={(e) => {
          if (e.key === 'Enter' || e.key === ' ') setExpanded(!expanded);
        }}
      >
        <div>
          <span style={{ fontWeight: 700, fontSize: '1rem', color: 'var(--text-main)' }}>
            ⚙ {title || 'Derniers traitements'}
          </span>
          <div style={{ fontSize: '0.85rem', color: summaryColor, marginTop: '2px' }}>
            <span style={{ marginRight: '8px' }}>{summaryIcon}</span>
            {nbSuccess} succès · {nbFailed} erreurs · Dernier : {relativeTime(lastJob?.finished_at || lastJob?.started_at)}
          </div>
        </div>
        <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
          <span style={{ fontSize: '0.8rem', color: 'var(--text-muted)' }}>
            {expanded ? '▼ Masquer' : '▶ Voir tout'}
          </span>
          {onClose && (
            <button
              onClick={(e) => {
                e.stopPropagation();
                onClose();
              }}
              style={{
                background: 'none',
                border: 'none',
                cursor: 'pointer',
                color: 'var(--text-muted)',
                fontSize: '14px',
                padding: '2px 6px',
                borderRadius: '4px',
              }}
              type="button"
            >
              ✕
            </button>
          )}
        </div>
      </div>

      {/* Empty state */}
      {expanded && jobs.length === 0 && (
        <p
          style={{
            textAlign: 'center',
            color: 'var(--text-muted)',
            padding: '1rem 0',
            fontSize: '0.85rem',
            margin: 0,
          }}
        >
          Aucun traitement récent
        </p>
      )}

      {/* Jobs list */}
      {expanded &&
        jobs.map((job) => {
          const jobLabel = JOB_LABELS[job.job_name] || job.job_name;
          const jobIcon = JOB_ICONS[job.job_name] || '⚙';
          const statusColor = STATUS_COLORS[job.status] || STATUS_COLORS.pending;
          const statusIcon = STATUS_ICONS[job.status] || STATUS_ICONS.pending;

          return (
            <div
              key={job.id}
              style={{
                background: 'var(--bg-elevated)',
                borderLeft: `3px solid ${statusColor}`,
                borderRadius: '8px',
                padding: '10px 12px',
                marginBottom: '6px',
                border: '1px solid var(--border)',
                transition: 'box-shadow 0.2s, background 0.15s',
                cursor: 'pointer',
              }}
              onMouseEnter={(e) => {
                e.currentTarget.style.boxShadow = '0 2px 8px rgba(0,0,0,0.1)';
              }}
              onMouseLeave={(e) => {
                e.currentTarget.style.boxShadow = 'none';
              }}
            >
              {/* Header line */}
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                  <span style={{ fontSize: '16px' }}>{jobIcon}</span>
                  <span style={{ fontSize: '16px', color: statusColor, fontWeight: 700 }}>
                    {statusIcon}
                  </span>
                  <span style={{ fontWeight: 600, fontSize: '0.9rem', color: 'var(--text-main)' }}>
                    {jobLabel}
                  </span>
                </div>
                <span style={{ fontSize: '0.75rem', color: 'var(--text-muted)' }}>
                  {relativeTime(job.finished_at || job.started_at)}
                </span>
              </div>

              {/* Metric line */}
              <div style={{
                fontSize: '0.8rem',
                color: 'var(--text-muted)',
                marginTop: '4px',
                paddingLeft: '24px'
              }}>
                {formatMetric(job)}
                {job.duration_ms && job.duration_ms > 0 && (
                  <span style={{ marginLeft: '8px' }}>· Durée: {formatDuration(job.duration_ms)}</span>
                )}
              </div>

              {/* Progress line if running */}
              {job.is_running && job.pourcentage > 0 && (
                <div style={{
                  display: 'flex',
                  alignItems: 'center',
                  gap: '8px',
                  marginTop: '6px',
                  paddingLeft: '24px'
                }}>
                  <div style={{
                    flex: 1,
                    height: '6px',
                    background: 'var(--border)',
                    borderRadius: '3px',
                    overflow: 'hidden',
                  }}>
                    <div
                      style={{
                        width: `${job.pourcentage}%`,
                        height: '100%',
                        background: statusColor,
                        borderRadius: '3px',
                        transition: 'width 0.3s',
                      }}
                    />
                  </div>
                  <span style={{
                    fontSize: '0.7rem',
                    color: statusColor,
                    fontWeight: 600,
                    minWidth: '35px'
                  }}>
                    {job.pourcentage}%
                  </span>
                </div>
              )}

              {/* Error line if failed */}
              {job.status === 'failed' && job.error_message && (
                <div style={{
                  fontSize: '0.75rem',
                  color: '#dc2626',
                  marginTop: '4px',
                  paddingLeft: '24px',
                  fontStyle: 'italic',
                }}>
                  {job.error_message.length > 80
                    ? job.error_message.substring(0, 80) + '...'
                    : job.error_message
                  }
                </div>
              )}
            </div>
          );
        })
      }
    </div>
  );
}

function DropdownMode({ jobs }) {
  const [isOpen, setIsOpen] = useState(false);
  const dropdownRef = useRef(null);

  useEffect(() => {
    const handleClickOutside = (event) => {
      if (dropdownRef.current && !dropdownRef.current.contains(event.target)) {
        setIsOpen(false);
      }
    };

    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  if (!jobs || jobs.length === 0) return null;

  const runningCount = jobs.filter(j => j.is_running).length;
  const successCount = jobs.filter(j => j.status === 'success').length;
  const failedCount = jobs.filter(j => j.status === 'failed').length;

  return (
    <div ref={dropdownRef} style={{ position: 'relative' }}>
      <button
        onClick={() => setIsOpen(!isOpen)}
        style={{
          background: 'var(--bg-surface)',
          border: '1px solid var(--border)',
          borderRadius: '6px',
          padding: '6px 12px',
          fontSize: '13px',
          color: 'var(--text-main)',
          cursor: 'pointer',
          display: 'flex',
          alignItems: 'center',
          gap: '6px',
          transition: 'all 0.2s',
        }}
        onMouseEnter={(e) => {
          e.target.style.background = 'var(--bg-hover)';
        }}
        onMouseLeave={(e) => {
          e.target.style.background = 'var(--bg-surface)';
        }}
      >
        <span>⚙</span>
        <span>{jobs.length} traitements</span>
        <span style={{ fontSize: '11px', color: 'var(--text-muted)' }}>{isOpen ? '▲' : '▼'}</span>
      </button>

      {isOpen && (
        <div style={{
          position: 'absolute',
          top: '40px',
          right: '0',
          width: '350px',
          background: 'var(--bg-elevated)',
          border: '1px solid var(--border)',
          borderRadius: '12px',
          boxShadow: '0 12px 32px rgba(0,0,0,0.16)',
          zIndex: 100,
          maxHeight: '400px',
          overflowY: 'auto',
        }}>
          <div style={{
            padding: '12px 16px',
            borderBottom: '1px solid var(--border)',
            fontSize: '0.85rem',
            fontWeight: 600,
            color: 'var(--text-main)',
          }}>
            Derniers traitements
            <div style={{ fontSize: '0.75rem', color: 'var(--text-muted)', marginTop: '2px' }}>
              {successCount} succès · {failedCount} erreurs · {runningCount} en cours
            </div>
          </div>

          <div style={{ padding: '8px' }}>
            {jobs.map((job) => {
              const jobLabel = JOB_LABELS[job.job_name] || job.job_name;
              const jobIcon = JOB_ICONS[job.job_name] || '⚙';
              const statusColor = STATUS_COLORS[job.status] || STATUS_COLORS.pending;
              const statusIcon = STATUS_ICONS[job.status] || STATUS_ICONS.pending;

              return (
                <div
                  key={job.id}
                  style={{
                    background: 'var(--bg-surface)',
                    borderLeft: `3px solid ${statusColor}`,
                    borderRadius: '4px',
                    padding: '8px 10px',
                    marginBottom: '6px',
                  }}
                >
                  <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: '6px' }}>
                      <span style={{ fontSize: '14px' }}>{jobIcon}</span>
                      <span style={{ fontSize: '14px', color: statusColor, fontWeight: 700 }}>
                        {statusIcon}
                      </span>
                      <span style={{ fontWeight: 500, fontSize: '0.8rem', color: 'var(--text-main)' }}>
                        {jobLabel}
                      </span>
                    </div>
                    <span style={{ fontSize: '0.7rem', color: 'var(--text-muted)' }}>
                      {relativeTime(job.finished_at || job.started_at)}
                    </span>
                  </div>

                  <div style={{
                    fontSize: '0.7rem',
                    color: 'var(--text-muted)',
                    marginTop: '2px',
                    paddingLeft: '20px'
                  }}>
                    {formatMetric(job)}
                  </div>

                  {job.is_running && job.pourcentage > 0 && (
                    <div style={{
                      display: 'flex',
                      alignItems: 'center',
                      gap: '6px',
                      marginTop: '4px',
                      paddingLeft: '20px'
                    }}>
                      <div style={{
                        flex: 1,
                        height: '4px',
                        background: 'var(--border)',
                        borderRadius: '2px',
                        overflow: 'hidden',
                      }}>
                        <div
                          style={{
                            width: `${job.pourcentage}%`,
                            height: '100%',
                            background: statusColor,
                            borderRadius: '2px',
                            transition: 'width 0.3s',
                          }}
                        />
                      </div>
                      <span style={{
                        fontSize: '0.65rem',
                        color: statusColor,
                        fontWeight: 600,
                        minWidth: '30px'
                      }}>
                        {job.pourcentage}%
                      </span>
                    </div>
                  )}
                </div>
              );
            })}
          </div>
        </div>
      )}
    </div>
  );
}

function InlineTimeline({ jobs, steps }) {
  if (!steps || steps.length === 0) return null;

  const jobMap = new Map((jobs || []).map((j) => [j.job_name, j]));

  // Calculate overall progress
  // Règle: ne pas afficher l'étape IA (ai_risk_score) comme faite/en cours tant que
  // msisdn_search_all n'est pas success.
  const msisdnJob = jobMap.get('msisdn_search_all');
  const allowAiSteps = msisdnJob?.status === 'success';

  let progressIndex = -1;
  let allDone = true;
  for (let i = 0; i < steps.length; i++) {
    const step = steps[i];
    if (!allowAiSteps && step.jobName === 'ai_risk_score') {
      progressIndex = i;
      allDone = false;
      break;
    }

    const job = jobMap.get(step.jobName);
    if (!job || job.status === 'pending' || job.status === 'running' || job.status === 'failed') {
      progressIndex = i;
      allDone = false;
      break;
    }
  }

  let fillPct = 0;
  if (allDone) {
    fillPct = 100;
  } else if (progressIndex !== -1) {
    const currentStep = steps[progressIndex];
    const currentJob = jobMap.get(currentStep.jobName);
    const isRunning = currentJob?.status === 'running';
    fillPct = (progressIndex / (steps.length - 1)) * 100;
    if (isRunning) {
      fillPct += (0.5 / (steps.length - 1)) * 100; // Animate halfway to the next step
    }
  }

  return (
    <div
      style={{
        background: 'linear-gradient(145deg, var(--bg-surface) 0%, var(--bg-elevated) 100%)',
        borderRadius: '16px',
        padding: '1.5rem',
        border: '1px solid rgba(99, 102, 241, 0.15)',
        boxShadow: '0 8px 30px rgba(0,0,0,0.04)',
      }}
    >
      <div style={{
        fontSize: '1.05rem',
        fontWeight: 800,
        color: 'var(--text-main)',
        marginBottom: '1.5rem',
        display: 'flex',
        alignItems: 'center',
        gap: '8px'
      }}>
        <span style={{ color: '#6366f1', fontSize: '1rem', fontWeight: 900, lineHeight: 1 }}>IA</span> Pipeline d'Analyse IA
      </div>

      {/* Horizontal timeline */}
      <div style={{
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'space-between',
        position: 'relative',
        marginBottom: '16px'
      }}>
        {/* Background line */}
        <div style={{
          position: 'absolute',
          top: '50%',
          left: '20px',
          right: '20px',
          height: '4px',
          background: 'var(--border)',
          borderRadius: '2px',
          zIndex: 1,
          transform: 'translateY(-50%)'
        }} />

        {/* Progress line */}
        <div style={{
          position: 'absolute',
          top: '50%',
          left: '20px',
          width: `calc(${Math.min(100, Math.max(0, fillPct))}% - 40px * (${Math.min(100, Math.max(0, fillPct))} / 100))`,
          height: '4px',
          background: 'linear-gradient(90deg, #6366f1, #3b82f6)',
          borderRadius: '2px',
          zIndex: 1,
          transform: 'translateY(-50%)',
          transition: 'width 1s ease-in-out'
        }} />

        {steps.map((step, index) => {
          const job = jobMap.get(step.jobName);
          const isDone = job?.status === 'success';
          const isRunning = job?.status === 'running';
          const isFailed = job?.status === 'failed';

          let icon = '○';
          let bgColor = 'var(--bg-surface)';
          let borderColor = 'var(--border)';
          let textColor = 'var(--text-muted)';
          let pulse = 'none';

          // États de la timeline (emoji conservés)
          const stepIsAi = step.jobName === 'ai_risk_score';
          const aiBlocked = stepIsAi && !allowAiSteps;

          if (aiBlocked) {
            icon = '○';
            bgColor = 'var(--bg-surface)';
            borderColor = 'var(--border)';
            textColor = 'var(--text-muted)';
          } else if (isDone) {
            icon = '✓';
            bgColor = '#10b981';
            borderColor = '#10b981';
            textColor = '#fff';
          } else if (isRunning) {
            icon = '🔄';
            bgColor = '#3b82f6';
            borderColor = '#3b82f6';
            textColor = '#fff';
            pulse = 'job-pulse 1.5s infinite';
          } else if (isFailed) {
            icon = '⚠';
            bgColor = '#ef4444';
            borderColor = '#ef4444';
            textColor = '#fff';
          }

          return (
            <div key={step.jobName} style={{ zIndex: 2, display: 'flex', flexDirection: 'column', alignItems: 'center', flex: 1 }}>
              <div
                style={{
                  width: '28px',
                  height: '28px',
                  borderRadius: '50%',
                  background: bgColor,
                  border: `4px solid var(--bg-elevated)`,
                  display: 'flex',
                  alignItems: 'center',
                  justifyContent: 'center',
                  fontSize: '12px',
                  color: textColor,
                  fontWeight: 800,
                  boxShadow: isRunning ? '0 0 0 4px rgba(59,130,246,0.2)' : isDone ? '0 0 0 4px rgba(16,185,129,0.1)' : '0 0 0 4px var(--bg-surface)',
                  animation: pulse,
                  transition: 'all 0.4s ease'
                }}
              >
                {icon}
              </div>
            </div>
          );
        })}
      </div>

      {/* Labels and durations */}
      <div style={{
        display: 'flex',
        justifyContent: 'space-between',
        gap: '8px'
      }}>
        {steps.map((step) => {
          const job = jobMap.get(step.jobName);
          const isDone = job?.status === 'success';
          const isRunning = job?.status === 'running';
          const isFailed = job?.status === 'failed';
          const isPending = !job || job.status === 'pending';

          return (
            <div
              key={step.jobName}
              style={{
                flex: 1,
                textAlign: 'center',
              }}
            >
              <div style={{
                fontWeight: isRunning || isDone ? 700 : 500,
                color: isDone ? 'var(--text-main)' :
                  isRunning ? '#3b82f6' :
                  isFailed ? '#ef4444' :
                  'var(--text-muted)',
                marginBottom: '4px',
                fontSize: '0.8rem',
                transition: 'color 0.3s'
              }}>
                {step.label}
              </div>
              <div style={{
                fontSize: '0.7rem',
                color: isFailed ? '#ef4444' : 'var(--text-muted)',
                fontWeight: isFailed ? 600 : 400
              }}>
                {isDone && job?.duration_ms ? formatDuration(job.duration_ms) :
                  isRunning ? 'En cours...' :
                  isFailed ? 'Erreur' :
                  isPending ? 'En attente' : ''}
              </div>
            </div>
          );
        })}
      </div>
    </div>
  );
}

/* ───────── CSS Animation (inject once) ───────── */
const styleTag = document.createElement('style');
styleTag.textContent = `
  @keyframes job-pulse {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.5; transform: scale(0.85); }
  }
`;
if (!document.head.querySelector('[data-job-status-bar]')) {
  styleTag.setAttribute('data-job-status-bar', '');
  document.head.appendChild(styleTag);
}

/* ───────── Main Component ───────── */

export default function JobStatusBar({
  jobTypes = [],
  title = 'Derniers traitements',
  compact = false,
  autoRefresh = true,
  refreshInterval = 10000,
  mode = 'default',
  steps = [],
}) {
  const [jobs, setJobs] = useState([]);
  const [error, setError] = useState(false);
  const [visible, setVisible] = useState(true);
  const intervalRef = useRef(null);
  const lastFetchRef = useRef(0);

  const fetchJobs = useCallback(async () => {
    if (!jobTypes || jobTypes.length === 0) return;
    if (document.hidden) return;

    const now = Date.now();
    if (now - lastFetchRef.current < 5000) return;
    lastFetchRef.current = now;

    try {
      const params = new URLSearchParams();
      jobTypes.forEach((t) => params.append('types[]', t));
      params.append('limit', '15');

      const res = await api.get(`/etl/jobs/by-types?${params.toString()}`);
      setJobs(res.data?.jobs || []);
      setError(false);
    } catch {
      setError(true);
      setJobs([]);
    }
  }, [jobTypes]);

  useEffect(() => {
    if (!autoRefresh || jobTypes.length === 0) return;

    fetchJobs();
    intervalRef.current = setInterval(fetchJobs, refreshInterval);

    const handleVisibility = () => {
      if (!document.hidden) fetchJobs();
    };
    document.addEventListener('visibilitychange', handleVisibility);

    return () => {
      clearInterval(intervalRef.current);
      document.removeEventListener('visibilitychange', handleVisibility);
    };
  }, [autoRefresh, refreshInterval, fetchJobs, jobTypes.length]);

  // Silent fail — never crash the page
  if (error || !visible) return null;

  // Dropdown mode
  if (mode === 'dropdown') {
    return <DropdownMode jobs={jobs} title={title} />;
  }

  // Timeline mode
  if (mode === 'timeline') {
    return <InlineTimeline jobs={jobs} steps={steps} />;
  }

  // Compact mode
  if (compact) {
    return <CompactBar jobs={jobs} title={title} />;
  }

  // Full card mode
  return <FullCard jobs={jobs} title={title} onClose={() => setVisible(false)} />;
}
