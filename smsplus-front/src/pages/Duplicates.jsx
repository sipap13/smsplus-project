import { useState, useEffect, useCallback, Fragment } from 'react';
import api from '../api/axios';
import { formatDT } from '../lib/format';
import useServiceMapping from '../hooks/useServiceMapping';
import {
  BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer,
  PieChart, Pie, Cell, Legend
} from 'recharts';

const COLORS = ['#0f2744', '#1e3a5f', '#2a5082', '#3b6fa0', '#4a8ec2'];

const IconWrapper = ({ children, size = 24 }) => (
  <svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
    {children}
  </svg>
);

const Icons = {
  AlertCircle: (props) => <IconWrapper {...props}><circle cx="12" cy="12" r="10" /><line x1="12" y1="8" x2="12" y2="12" /><line x1="12" y1="16" x2="12.01" y2="16" /></IconWrapper>,
  Trash2: (props) => <IconWrapper {...props}><polyline points="3 6 5 6 21 6" /><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" /><line x1="10" y1="11" x2="10" y2="17" /><line x1="14" y1="11" x2="14" y2="17" /></IconWrapper>,
  CheckCircle: (props) => <IconWrapper {...props}><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" /><polyline points="22 4 12 14.01 9 11.01" /></IconWrapper>,
  Calendar: (props) => <IconWrapper {...props}><rect x="3" y="4" width="18" height="18" rx="2" ry="2" /><line x1="16" y1="2" x2="16" y2="6" /><line x1="8" y1="2" x2="8" y2="6" /><line x1="3" y1="10" x2="21" y2="10" /></IconWrapper>,
  Filter: (props) => <IconWrapper {...props}><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3" /></IconWrapper>,
  TrendingDown: (props) => <IconWrapper {...props}><polyline points="23 18 13.5 8.5 8.5 13.5 1 6" /><polyline points="17 18 23 18 23 12" /></IconWrapper>,
  Users: (props) => <IconWrapper {...props}><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" /><circle cx="9" cy="7" r="4" /><path d="M23 21v-2a4 4 0 0 0-3-3.87" /><path d="M16 3.13a4 4 0 0 1 0 7.75" /></IconWrapper>,
  Package: (props) => <IconWrapper {...props}><line x1="16.5" y1="9.4" x2="7.5" y2="4.21" /><polyline points="21 16 12 21 3 16" /><polyline points="3 8 12 13 21 8" /><line x1="12" y1="22.08" x2="12" y2="13" /><line x1="12" y1="3" x2="12" y2="13" /></IconWrapper>,
  RefreshCw: (props) => <IconWrapper {...props}><polyline points="23 4 23 10 17 10" /><polyline points="1 20 1 14 7 14" /><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15" /></IconWrapper>,
  ChevronDown: (props) => <IconWrapper {...props}><polyline points="6 9 12 15 18 9" /></IconWrapper>,
  ChevronUp: (props) => <IconWrapper {...props}><polyline points="18 15 12 9 6 15" /></IconWrapper>,
  Info: (props) => <IconWrapper {...props}><circle cx="12" cy="12" r="10" /><line x1="12" y1="16" x2="12" y2="12" /><line x1="12" y1="8" x2="12.01" y2="8" /></IconWrapper>,
  Download: (props) => <IconWrapper {...props}><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" /><polyline points="7 10 12 15 17 10" /><line x1="12" y1="15" x2="12" y2="3" /></IconWrapper>
};

const KPICard = ({ title, value, subValue, icon: Icon, color, badge, loading }) => (
  <div className="saas-surface" style={{ padding: '1.5rem', borderRadius: '16px', position: 'relative', overflow: 'hidden' }}>
    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start' }}>
      <div>
        <p style={{ color: 'var(--text-muted)', fontSize: '0.875rem', fontWeight: 500, marginBottom: '0.5rem' }}>{title}</p>
        <h3 style={{ fontSize: '1.75rem', fontWeight: 800, margin: 0, color: color || 'inherit' }}>
          {loading ? '...' : value}
        </h3>
        {subValue && <p style={{ color: 'var(--text-muted)', fontSize: '0.75rem', marginTop: '0.25rem' }}>{subValue}</p>}
      </div>
      <div style={{ 
        background: `${color}15`, 
        color: color, 
        padding: '0.75rem', 
        borderRadius: '12px',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center'
      }}>
        <Icon size={24} />
      </div>
    </div>
    {badge && (
      <div style={{ 
        position: 'absolute', 
        top: '0.75rem', 
        right: '4.5rem',
        background: '#ef4444',
        color: 'white',
        fontSize: '0.65rem',
        fontWeight: 700,
        padding: '2px 8px',
        borderRadius: '99px',
        textTransform: 'uppercase'
      }}>
        {badge}
      </div>
    )}
  </div>
);

export default function Duplicates() {
  const [loading, setLoading] = useState(false);
  const [statsLoading, setStatsLoading] = useState(true);
  const [stats, setStats] = useState(null);
  const [results, setResults] = useState([]);
  const [source, setSource] = useState('all');
  const [dateDebut, setDateDebut] = useState('');
  const [minOccurrences, setMinOccurrences] = useState(2);
  const [keyword, setKeyword] = useState('');
  const [callType, setCallType] = useState('VAS');
  const [expandedRows, setExpandedRows] = useState(new Set());
  const [report, setReport] = useState(null);
  const [showReport, setShowReport] = useState(false);

  const { getNom, services: mappedServices } = useServiceMapping();

  const fetchStats = useCallback(async () => {
    setStatsLoading(true);
    try {
      const res = await api.get(`/duplicates/stats?date_debut=${dateDebut}`);
      setStats(res.data);
      if (!dateDebut && res.data.date_debut_effective) {
        setDateDebut(res.data.date_debut_effective);
      }
    } catch (err) {
      console.error("Failed to fetch duplicate stats", err);
    } finally {
      setStatsLoading(false);
    }
  }, [dateDebut]);

  useEffect(() => {
    const syncDate = async () => {
      try {
        const res = await api.get('/dashboard/range');
        const maxDate = res.data.max_date;
        if (maxDate && (!dateDebut || new Date(dateDebut) > new Date(maxDate))) {
          // If no date or date is in the future relative to data, set to 30 days before maxDate
          const suggestedDate = new Date(new Date(maxDate).getTime() - 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];
          setDateDebut(suggestedDate);
        }
      } catch (err) {
        console.error("Failed to sync date range", err);
      }
    };
    syncDate();
  }, []);

  const fetchResults = async () => {
    setLoading(true);
    try {
      if (source === 'all') {
        const [resOcc, resMmg] = await Promise.all([
          api.get(`/duplicates/occ?date_debut=${dateDebut}&min_occurrences=${minOccurrences}&keyword=${keyword}&call_type=${callType}`),
          api.get(`/duplicates/mmg?date_debut=${dateDebut}&min_occurrences=${minOccurrences}&keyword=${keyword}`)
        ]);
        const merged = [
          ...resOcc.data.map(item => ({ ...item, _source: 'occ' })),
          ...resMmg.data.map(item => ({ ...item, _source: 'mmg' }))
        ];
        merged.sort((a, b) => b.occurrences - a.occurrences);
        setResults(merged);
      } else {
        const endpoint = source === 'occ' ? '/duplicates/occ' : '/duplicates/mmg';
        const res = await api.get(`${endpoint}?date_debut=${dateDebut}&min_occurrences=${minOccurrences}&keyword=${keyword}&call_type=${callType}`);
        setResults(res.data.map(item => ({ ...item, _source: source })));
      }
    } catch (err) {
      console.error("Failed to fetch duplicates", err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchStats();
  }, [fetchStats]);

  const toggleRow = (id) => {
    const next = new Set(expandedRows);
    if (next.has(id)) next.delete(id);
    else next.add(id);
    setExpandedRows(next);
  };

  const handleDelete = async (ids) => {
    if (!window.confirm(`Confirmer la suppression de ${ids.length - 1} doublons ? Un seul enregistrement sera conservé.`)) return;
    
    try {
      const res = await api.post('/duplicates/supprimer-occ', { ids });
      setReport({
        type: 'Séquentielle',
        supprimes: res.data.supprimes,
        revenus: res.data.revenus_corriges,
        time: new Date().toLocaleTimeString()
      });
      setShowReport(true);
      fetchResults();
      fetchStats();
    } catch (err) {
      alert("Erreur lors de la suppression");
    }
  };

  const handleDeleteAll = async () => {
    const total = stats?.occ?.affected_cdr - stats?.occ?.total_doublons;
    if (!window.confirm(`ATTENTION: Cette action supprimera TOUS les doublons OCC détectés depuis le ${dateDebut} (environ ${total} CDR).\n\nImpact financier estimé: ${formatDT(stats?.occ?.revenue_impact)}.\n\nContinuer ?`)) return;

    try {
      const res = await api.post('/duplicates/supprimer-tous-occ', { date_debut: dateDebut });
      setReport({
        type: 'Automatique (Tous)',
        supprimes: res.data.supprimes,
        revenus: res.data.revenus_corriges,
        time: new Date().toLocaleTimeString()
      });
      setShowReport(true);
      fetchResults();
      fetchStats();
    } catch (err) {
      alert("Erreur lors de la suppression massive");
    }
  };

  return (
    <div className="page" style={{ padding: '1.5rem', maxWidth: '1600px', margin: '0 auto' }}>
      <div className="page-header" style={{ marginBottom: '2rem', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
        <div>
          <h1 className="page-title" style={{ fontSize: '1.85rem', fontWeight: 800, color: 'var(--text-main)', marginBottom: '0.5rem' }}>
            Détection de Doublons CDR
          </h1>
          <p className="page-subtitle" style={{ color: 'var(--text-muted)', fontSize: '0.95rem' }}>
            Identifiez et corrigez les anomalies de duplication dans les flux OCC et MMG.
          </p>
        </div>
        <button 
          className="btn btn-primary" 
          onClick={fetchStats}
          style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}
        >
          <Icons.RefreshCw size={18} className={statsLoading ? 'spin' : ''} />
          Actualiser les métriques
        </button>
      </div>

      {/* SECTION 1 — KPI Cards */}
      <div style={{ 
        display: 'grid', 
        gridTemplateColumns: 'repeat(auto-fit, minmax(280px, 1fr))', 
        gap: '1.5rem',
        marginBottom: '2.5rem' 
      }}>
        <KPICard 
          title="Doublons OCC détectés"
          value={stats?.occ?.total_doublons || 0}
          subValue={`${stats?.occ?.affected_cdr || 0} CDR affectés`}
          icon={Icons.AlertCircle}
          color={stats?.occ?.total_doublons > 1000 ? '#ef4444' : '#f59e0b'}
          badge={stats?.occ?.total_doublons > 1000 ? 'Critique' : null}
          loading={statsLoading}
        />
        <KPICard 
          title="Doublons MMG détectés"
          value={stats?.mmg?.total_doublons || 0}
          subValue={`${stats?.mmg?.affected_cdr || 0} CDR affectés`}
          icon={Icons.Package}
          color="#3b82f6"
          loading={statsLoading}
        />
        <KPICard 
          title="Impact financier"
          value={formatDT(stats?.occ?.revenue_impact || 0)}
          subValue="Revenus comptés en double"
          icon={Icons.TrendingDown}
          color="#ef4444"
          badge="À corriger"
          loading={statsLoading}
        />
        <KPICard 
          title="Taux de duplication"
          value={stats && stats.total_sample_count > 0 ? ((stats.total_affected / stats.total_sample_count) * 100).toFixed(2) + '%' : '0.00%'}
          subValue={stats ? `Basé sur ${stats.total_sample_count.toLocaleString()} CDR` : "Basé sur l'échantillon analysé"}
          icon={Icons.Users}
          color="#10b981"
          loading={statsLoading}
        />
      </div>

      {/* SECTION 2 — Filtres */}
      <div className="saas-surface" style={{ padding: '1.5rem', borderRadius: '16px', marginBottom: '2.5rem' }}>
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '1.25rem' }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem' }}>
            <Icons.Filter size={20} color="var(--primary-1)" />
            <h3 style={{ margin: 0, fontSize: '1.1rem', fontWeight: 700 }}>Paramètres d'analyse</h3>
          </div>
          {stats?.date_debut_effective && (
            <div style={{ fontSize: '0.85rem', color: 'var(--text-muted)', background: 'rgba(var(--primary-rgb), 0.05)', padding: '4px 12px', borderRadius: '99px', border: '1px solid var(--border)' }}>
              Analyse du <strong>{new Date(stats.date_debut_effective).toLocaleDateString()}</strong> au <strong>{stats.date_fin_effective ? new Date(stats.date_fin_effective).toLocaleDateString() : "Aujourd'hui"}</strong>
            </div>
          )}
        </div>
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))', gap: '1.5rem', alignItems: 'flex-end' }}>
          <div className="field">
            <label className="field-label">Période d'analyse (Depuis)</label>
            <div style={{ position: 'relative' }}>
              <Icons.Calendar size={16} style={{ position: 'absolute', left: '0.75rem', top: '50%', transform: 'translateY(-50%)', color: 'var(--text-muted)' }} />
              <input 
                type="date" 
                className="field-control" 
                style={{ paddingLeft: '2.5rem' }}
                value={dateDebut}
                onChange={(e) => setDateDebut(e.target.value)}
              />
            </div>
          </div>
          <div className="field">
            <label className="field-label">Source des données</label>
            <select className="field-control" value={source} onChange={(e) => setSource(e.target.value)}>
              <option value="all">Tous (OCC + MMG)</option>
              <option value="occ">OCC (Détail CDR)</option>
              <option value="mmg">MMG (SMS/Signaling)</option>
            </select>
          </div>
          {['all', 'occ'].includes(source) && (
            <div className="field">
              <label className="field-label">Type d'appel</label>
              <select className="field-control" value={callType} onChange={(e) => setCallType(e.target.value)}>
                <option value="VAS">VAS (SMS+)</option>
                <option value="DATA">DATA (Internet)</option>
                <option value="SMS">SMS (Classique)</option>
                <option value="all">Tous les types</option>
              </select>
            </div>
          )}
          <div className="field">
            <label className="field-label">Seuil occurrences (Min)</label>
            <select className="field-control" value={minOccurrences} onChange={(e) => setMinOccurrences(Number(e.target.value))}>
              <option value={2}>2+ occurrences</option>
              <option value={3}>3+ occurrences</option>
              <option value={5}>5+ occurrences</option>
              <option value={10}>10+ occurrences</option>
            </select>
          </div>
          <div className="field">
            <label className="field-label">Service (Optionnel)</label>
            <select className="field-control" value={keyword} onChange={(e) => setKeyword(e.target.value)}>
              <option value="">Tous les services</option>
              {mappedServices.map(s => (
                <option key={s.keyword} value={s.keyword}>{s.nom_service}</option>
              ))}
            </select>
          </div>
          <div>
            <button className="btn btn-primary" style={{ width: '100%', height: '42px', fontWeight: 600 }} onClick={fetchResults}>
              {loading ? 'Analyse en cours...' : "Lancer l'analyse"}
            </button>
          </div>
        </div>
      </div>

      <div style={{ display: 'grid', gridTemplateColumns: '2fr 1fr', gap: '2rem', marginBottom: '2.5rem' }}>
        {/* SECTION 3 — Résultats */}
        <div className="panel" style={{ padding: 0, borderRadius: '16px', overflow: 'hidden' }}>
          <div style={{ padding: '1.25rem 1.5rem', borderBottom: '1px solid var(--border)', display: 'flex', justifyContent: 'space-between', alignItems: 'center', background: 'rgba(var(--primary-rgb), 0.03)' }}>
            <h3 style={{ margin: 0, fontSize: '1.1rem', fontWeight: 700 }}>Groupes de Doublons Détectés ({results.length})</h3>
            {['all', 'occ'].includes(source) && results.some(r => r._source === 'occ') && (
              <button 
                className="btn" 
                style={{ background: '#ef4444', color: 'white', fontSize: '0.8rem', padding: '6px 12px' }}
                onClick={handleDeleteAll}
              >
                <Icons.Trash2 size={14} style={{ marginRight: '6px' }} />
                Supprimer tous les doublons OCC
              </button>
            )}
          </div>
          <div className="table-wrap" style={{ maxHeight: '600px', overflow: 'auto' }}>
            <table className="table-dense" style={{ minWidth: '100%', borderCollapse: 'collapse', tableLayout: 'auto' }}>
              <thead style={{ position: 'sticky', top: 0, background: 'var(--bg-panel)', zIndex: 10 }}>
                <tr>
                  <th style={{ width: '40px' }}></th>
                  <th>Source</th>
                  <th>MSISDN</th>
                  <th>Service</th>
                  <th>Date & Heure</th>
                  {['all', 'occ'].includes(source) && <th>Montant</th>}
                  <th>Occurrences</th>
                  {['all', 'occ'].includes(source) && <th>Impact DT</th>}
                  <th style={{ textAlign: 'right' }}>Actions</th>
                </tr>
              </thead>
              <tbody>
                {results.length === 0 ? (
                  <tr>
                    <td colSpan={['all', 'occ'].includes(source) ? 9 : 7} style={{ textAlign: 'center', padding: '3rem', color: 'var(--text-muted)' }}>
                      {loading ? 'Recherche des anomalies...' : 'Aucun doublon détecté avec ces critères.'}
                    </td>
                  </tr>
                ) : (
                  results.map((row, idx) => (
                    <Fragment key={idx}>
                      <tr key={idx} style={{ background: expandedRows.has(idx) ? 'rgba(var(--primary-rgb), 0.05)' : 'transparent' }}>
                        <td style={{ padding: '0.75rem' }}>
                          <button onClick={() => toggleRow(idx)} style={{ background: 'none', border: 'none', color: 'var(--text-muted)', cursor: 'pointer' }}>
                            {expandedRows.has(idx) ? <Icons.ChevronUp size={16} /> : <Icons.ChevronDown size={16} />}
                          </button>
                        </td>
                        <td>
                          <span style={{ 
                            fontSize: '10px', 
                            padding: '2px 6px', 
                            borderRadius: '4px', 
                            background: row._source === 'occ' ? 'rgba(59, 130, 246, 0.1)' : 'rgba(16, 185, 129, 0.1)',
                            color: row._source === 'occ' ? '#3b82f6' : '#10b981',
                            fontWeight: 700,
                            textTransform: 'uppercase'
                          }}>
                            {row._source}
                          </span>
                        </td>
                        <td className="mono" style={{ fontWeight: 600 }}>{row.a_msisdn}</td>
                        <td>
                          <div style={{ display: 'flex', flexDirection: 'column', gap: '2px' }}>
                            <span style={{ fontWeight: 600, fontSize: '12px', color: 'var(--text-main)' }}>
                              {getNom(row.keyword || row.service_type)}
                            </span>
                            <span style={{ fontSize: '10px', color: '#94a3b8', fontFamily: 'monospace' }}>
                              {row.keyword || row.service_type || '—'}
                            </span>
                          </div>
                        </td>
                        <td>{row.start_date} <span style={{ color: 'var(--text-muted)' }}>{row.start_hour}h</span></td>
                        {['all', 'occ'].includes(source) && <td>{row._source === 'occ' ? formatDT(row.charge_amount) : '—'}</td>}
                        <td>
                          <span style={{ 
                            color: row.occurrences >= 5 ? '#ef4444' : (row.occurrences >= 3 ? '#f59e0b' : 'inherit'),
                            fontWeight: 700
                          }}>
                            {row.occurrences}x
                          </span>
                        </td>
                        {['all', 'occ'].includes(source) && (
                          <td style={{ color: row._source === 'occ' ? '#ef4444' : 'inherit', fontWeight: row._source === 'occ' ? 600 : 'normal' }}>
                            {row._source === 'occ' ? `-${formatDT(row.revenu_a_corriger)}` : '—'}
                          </td>
                        )}
                        <td style={{ textAlign: 'right' }}>
                          {row._source === 'occ' && (
                            <button 
                              className="btn btn-ghost" 
                              style={{ color: '#ef4444', padding: '4px 8px' }}
                              onClick={() => handleDelete(row.ids)}
                            >
                              <Icons.Trash2 size={14} />
                            </button>
                          )}
                        </td>
                      </tr>
                      {expandedRows.has(idx) && (
                        <tr style={{ background: 'rgba(var(--primary-rgb), 0.02)' }}>
                          <td colSpan={['all', 'occ'].includes(source) ? 9 : 7} style={{ padding: '1rem 3rem' }}>
                            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(150px, 1fr))', gap: '1rem' }}>
                              {row.ids.map(id => (
                                <div key={id} style={{ fontSize: '0.75rem', padding: '4px 8px', border: '1px solid var(--border)', borderRadius: '4px', display: 'flex', justifyContent: 'space-between' }}>
                                  <span style={{ color: 'var(--text-muted)' }}>ID:</span>
                                  <span className="mono">{id}</span>
                                </div>
                              ))}
                            </div>
                            <div style={{ marginTop: '0.75rem', fontSize: '0.8rem', color: 'var(--text-muted)', display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                              <Icons.Info size={14} />
                              La suppression conservera l'ID le plus bas et supprimera les {row.ids.length - 1} autres.
                            </div>
                          </td>
                        </tr>
                      )}
                    </Fragment>
                  ))
                )}
              </tbody>
            </table>
          </div>
        </div>

        {/* SECTION 4 — Graphiques */}
        <div style={{ display: 'flex', flexDirection: 'column', gap: '1.5rem' }}>
          <div className="saas-surface" style={{ padding: '1.5rem', borderRadius: '16px', flex: 1 }}>
            <h3 style={{ margin: '0 0 1.25rem 0', fontSize: '1rem', fontWeight: 700 }}>Répartition par Service</h3>
            <div style={{ height: '220px' }}>
              <ResponsiveContainer width="100%" height={280} minWidth={0} debounce={50}>
                <PieChart>
                  <Pie
                    data={stats?.top_services?.map((s, i) => ({ name: getNom(s.keyword), value: Number(s.count) })) || []}
                    cx="50%"
                    cy="50%"
                    innerRadius={60}
                    outerRadius={80}
                    paddingAngle={5}
                    dataKey="value"
                  >
                    {(stats?.top_services || []).map((entry, index) => (
                      <Cell key={`cell-${index}`} fill={COLORS[index % COLORS.length]} />
                    ))}
                  </Pie>
                  <Tooltip 
                    contentStyle={{ background: 'var(--bg-elevated)', border: '1px solid var(--border)', borderRadius: '8px', color: 'var(--text-main)' }} 
                    itemStyle={{ fontSize: '12px' }}
                  />
                  <Legend verticalAlign="bottom" height={36}/>
                </PieChart>
              </ResponsiveContainer>
            </div>
          </div>

          <div className="saas-surface" style={{ padding: '1.5rem', borderRadius: '16px', flex: 1 }}>
            <h3 style={{ margin: '0 0 1.25rem 0', fontSize: '1rem', fontWeight: 700 }}>Doublons par Date</h3>
            <div style={{ height: '220px' }}>
              <ResponsiveContainer width="100%" height={280} minWidth={0} debounce={50}>
                <BarChart data={stats?.by_date || []}>
                  <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="var(--border)" />
                  <XAxis 
                    dataKey="start_date" 
                    fontSize={10} 
                    tickFormatter={(val) => val.split('-').slice(1).reverse().join('/')} 
                  />
                  <YAxis fontSize={10} />
                  <Tooltip 
                    contentStyle={{ background: 'var(--bg-elevated)', border: '1px solid var(--border)', borderRadius: '8px', color: 'var(--text-main)' }} 
                    itemStyle={{ fontSize: '12px' }}
                  />
                  <Bar dataKey="count" fill="var(--primary-1)" radius={[4, 4, 0, 0]} />
                </BarChart>
              </ResponsiveContainer>
            </div>
          </div>
        </div>
      </div>

      {/* SECTION 5 — Rapport de nettoyage */}
      {showReport && report && (
        <div className="panel" style={{ 
          background: 'rgba(16, 185, 129, 0.05)', 
          border: '1px solid rgba(16, 185, 129, 0.3)', 
          padding: '1.5rem', 
          borderRadius: '16px',
          display: 'flex',
          justifyContent: 'space-between',
          alignItems: 'center',
          animation: 'fadeIn 0.5s ease-out'
        }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: '1.25rem' }}>
            <div style={{ background: '#10b981', color: 'white', padding: '0.75rem', borderRadius: '50%' }}>
              <Icons.CheckCircle size={24} />
            </div>
            <div>
              <h4 style={{ margin: 0, color: '#065f46', fontWeight: 700 }}>Rapport de nettoyage terminé</h4>
              <p style={{ margin: '0.25rem 0 0 0', fontSize: '0.9rem', color: '#047857' }}>
                Type: <strong>{report.type}</strong> • 
                CDR supprimés: <strong>{report.supprimes}</strong> • 
                Revenus corrigés: <strong>{formatDT(report.revenus)}</strong> • 
                Heure: <strong>{report.time}</strong>
              </p>
            </div>
          </div>
          <div style={{ display: 'flex', gap: '0.75rem' }}>
            <button className="btn btn-ghost" style={{ border: '1px solid #10b981', color: '#10b981', fontSize: '0.85rem' }}>
              <Icons.Download size={16} style={{ marginRight: '6px' }} />
              Télécharger
            </button>
            <button className="btn btn-ghost" onClick={() => setShowReport(false)}>Fermer</button>
          </div>
        </div>
      )}

      <style>{`
        @keyframes fadeIn {
          from { opacity: 0; transform: translateY(10px); }
          to { opacity: 1; transform: translateY(0); }
        }
        .spin {
          animation: spin 1s linear infinite;
        }
        @keyframes spin {
          from { transform: rotate(0deg); }
          to { transform: rotate(360deg); }
        }
      `}</style>
    </div>
  );
}
