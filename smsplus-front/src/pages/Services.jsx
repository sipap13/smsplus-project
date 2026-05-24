 
import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import api from '../api/axios';
import { downloadExcel } from '../api/excelDownload';
import Modal from '../components/Modal';

const TYPE_OPTIONS = ['Service', 'jeu'];

const emptyForm = () => ({
  nom_fournisseur: '',
  nom_service: '',
  numero_court: '',
  keyword: '',
  type_service: 'Service',
  prix: '',
  actif: true,
});

export default function Services() {
  const [services, setServices] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [showForm, setShowForm] = useState(false);
  const [editing, setEditing] = useState(null);
  const [form, setForm] = useState(emptyForm);
  const [msg, setMsg] = useState('');
  const [exportLoading, setExportLoading] = useState(false);
  const [exportError, setExportError] = useState('');
  const [filtreAlerte, setFiltreAlerte] = useState('tous');
  const [filtreFournisseur, setFiltreFournisseur] = useState('tous');
  const navigate = useNavigate();

  const load = () => {
    setLoading(true);
    setError('');
    api
      .get('/services')
      .then((r) => {
        setServices(r.data);
        setLoading(false);
      })
      .catch(() => {
        setError("Impossible de charger les services. Vérifie l'API.");
        setLoading(false);
      });
  };

  useEffect(() => {
    load();
  }, []);

  const openNew = () => {
    setEditing(null);
    setForm(emptyForm());
    setShowForm(true);
  };

  const openEdit = (s) => {
    setEditing(s.id);
    setForm({
      nom_fournisseur: s.nom_fournisseur ?? '',
      nom_service: s.nom_service ?? '',
      numero_court: s.numero_court ?? '',
      keyword: s.keyword ?? '',
      type_service: s.type_service || 'Service',
      prix: s.prix != null ? String(s.prix) : '',
      actif: Boolean(s.actif === true || s.actif === 1 || s.actif === '1'),
    });
    setShowForm(true);
  };

  const save = async () => {
    try {
      const body = {
        ...form,
        prix: parseFloat(form.prix, 10),
        actif: Boolean(form.actif),
      };
      if (Number.isNaN(body.prix)) {
        setMsg('Prix invalide');
        setTimeout(() => setMsg(''), 3000);
        return;
      }
      if (editing) {
        await api.put(`/services/${editing}`, body);
        setMsg('Service modifié avec succès');
      } else {
        await api.post('/services', body);
        setMsg('Service ajouté avec succès');
      }
      setShowForm(false);
      load();
      setTimeout(() => setMsg(''), 3000);
    } catch {
      setMsg("Erreur lors de l'enregistrement");
      setTimeout(() => setMsg(''), 4000);
    }
  };

  const del = async (id) => {
    if (!window.confirm('Supprimer ce service ?')) return;
    try {
      await api.delete(`/services/${id}`);
      setMsg('Service supprimé');
      load();
    } catch {
      setMsg('Erreur lors de la suppression');
    }
    setTimeout(() => setMsg(''), 3000);
  };

  const bannerOk = !/erreur|invalide/i.test(msg);

  const handleExport = async () => {
    await downloadExcel(
      '/export/services',
      {},
      `Services_${new Date().toISOString().slice(0, 10)}.xlsx`,
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

  const fournisseurs = [...new Set(services.map(s => s.nom_fournisseur).filter(Boolean))].sort();

  const servicesFiltres = services.filter((s) => {
    if (filtreAlerte === 'avec_alertes' && (s.nb_alertes_ouvertes || 0) === 0) return false;
    if (filtreAlerte === 'sans_alertes' && (s.nb_alertes_ouvertes || 0) > 0) return false;
    if (filtreFournisseur !== 'tous' && s.nom_fournisseur !== filtreFournisseur) return false;
    return true;
  });

  const servicesGroupes = servicesFiltres.reduce((acc, s) => {
    const key = `${s.nom_service}-${s.nom_fournisseur}`;
    if (!acc[key]) {
      acc[key] = { ...s, all_ids: [s.id], numeros: [s.numero_court], prix_list: [s.prix], keywords: [s.keyword] };
    } else {
      acc[key].all_ids.push(s.id);
      if (!acc[key].numeros.includes(s.numero_court)) acc[key].numeros.push(s.numero_court);
      if (!acc[key].prix_list.includes(s.prix)) acc[key].prix_list.push(s.prix);
      if (!acc[key].keywords.includes(s.keyword)) acc[key].keywords.push(s.keyword);
      acc[key].nb_cdr_30j = Number(acc[key].nb_cdr_30j || 0) + Number(s.nb_cdr_30j || 0);
      acc[key].revenus_30j = Number(acc[key].revenus_30j || 0) + Number(s.revenus_30j || 0);
      acc[key].nb_abonnes_30j = Number(acc[key].nb_abonnes_30j || 0) + Number(s.nb_abonnes_30j || 0);
      acc[key].nb_alertes_ouvertes = Number(acc[key].nb_alertes_ouvertes || 0) + Number(s.nb_alertes_ouvertes || 0);
      acc[key].total_sms_suspects = Number(acc[key].total_sms_suspects || 0) + Number(s.total_sms_suspects || 0);
    }
    return acc;
  }, {});

  const displayedServices = Object.values(servicesGroupes).map(s => {
    const prixNums = s.prix_list.map(p => parseFloat(p)).filter(Number.isFinite);
    return {
      ...s,
      numero_court: s.numeros.join(', '),
      keyword: s.keywords.join(', '),
      prixDisplay: prixNums.length > 1 ? `${Math.min(...prixNums).toFixed(3)} - ${Math.max(...prixNums).toFixed(3)}` : (prixNums.length === 1 ? prixNums[0].toFixed(3) : '—'),
      isGroup: s.all_ids.length > 1
    };
  });

  const totalSmsSuspectsGlobal = services.reduce((sum, s) => sum + Number(s.total_sms_suspects || 0), 0);
  const servicesAvecAlertes = services.filter((s) => (s.nb_alertes_ouvertes || 0) > 0);

  return (
    <div className="page" style={{ padding: '2rem', maxWidth: '1400px', margin: '0 auto' }}>

      {/* Header */}
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: '1.5rem', flexWrap: 'wrap', gap: '1rem' }}>
        <div>
          <h1 style={{ fontSize: '1.75rem', fontWeight: 800, color: 'var(--text-main)', margin: '0 0 0.25rem' }}>Gestion des Services VAS</h1>
          <div style={{ display: 'flex', gap: '0.75rem', flexWrap: 'wrap', marginTop: '0.5rem' }}>
            <span style={{ fontSize: '0.8rem', fontWeight: 600, padding: '3px 12px', borderRadius: '99px', background: 'rgba(99,102,241,0.12)', color: '#6366f1' }}>
              {services.length} services
            </span>
            {servicesAvecAlertes.length > 0 && (
              <span style={{ fontSize: '0.8rem', fontWeight: 600, padding: '3px 12px', borderRadius: '99px', background: 'rgba(239,68,68,0.12)', color: '#ef4444' }}>
                ⚠ {servicesAvecAlertes.length} avec alertes
              </span>
            )}
            <span style={{ fontSize: '0.8rem', fontWeight: 600, padding: '3px 12px', borderRadius: '99px', background: 'rgba(16,185,129,0.12)', color: '#10b981' }}>
              {services.filter(s => s.actif).length} actifs
            </span>
          </div>
        </div>
        <div style={{ display: 'flex', gap: '0.75rem' }}>
          <button
            type="button"
            onClick={handleExport}
            disabled={exportLoading || services.length === 0}
            className="btn btn-soft"
            style={{ height: '40px', fontWeight: 600, display: 'flex', alignItems: 'center', gap: '6px' }}
          >
            {exportLoading ? 'Export...' : 'Excel'}
          </button>
          <button type="button" onClick={openNew} className="btn btn-primary" style={{ height: '40px', fontWeight: 600 }}>
            + Ajouter un service
          </button>
        </div>
      </div>

      {/* Alert banner */}
      {servicesAvecAlertes.length > 0 && (
        <div style={{
          background: 'rgba(239,68,68,0.08)', border: '1px solid rgba(239,68,68,0.2)',
          borderRadius: '12px', padding: '0.9rem 1.25rem', marginBottom: '1.25rem',
          display: 'flex', alignItems: 'center', justifyContent: 'space-between',
        }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: '12px' }}>
            <div>
              <div style={{ fontWeight: 700, color: '#ef4444', fontSize: '0.9rem' }}>
                {servicesAvecAlertes.length} service{servicesAvecAlertes.length > 1 ? 's' : ''} avec alertes actives
              </div>
              <div style={{ color: 'var(--text-muted)', fontSize: '0.8rem' }}>
                {totalSmsSuspectsGlobal.toLocaleString('fr-FR')} SMS suspects sur 30j
              </div>
            </div>
          </div>
          <button onClick={() => navigate('/alerts')}
            className="btn btn-primary" style={{ height: '34px', fontSize: '0.82rem', background: '#ef4444', border: 'none' }}
          >Gérer les alertes →</button>
        </div>
      )}

      {/* Filter tabs */}
      <div style={{ display: 'flex', gap: '1rem', marginBottom: '1.25rem', alignItems: 'center', flexWrap: 'wrap' }}>
        <div style={{ display: 'flex', gap: '6px' }}>
          {[
            { id: 'tous', label: `Tous (${services.length})` },
            { id: 'avec_alertes', label: `Avec alertes (${servicesAvecAlertes.length})` },
            { id: 'sans_alertes', label: `Sans alertes (${services.length - servicesAvecAlertes.length})` },
          ].map(f => (
            <button key={f.id}
              onClick={() => setFiltreAlerte(f.id)}
              style={{
                height: '34px', padding: '0 14px', fontSize: '0.82rem', fontWeight: 600,
                borderRadius: '8px', border: '1px solid var(--border)', cursor: 'pointer',
                background: filtreAlerte === f.id ? '#6366f1' : 'var(--bg-surface)',
                color: filtreAlerte === f.id ? 'white' : 'var(--text-muted)',
                transition: 'all 0.15s'
              }}
            >{f.label}</button>
          ))}
        </div>
        <select
          value={filtreFournisseur}
          onChange={(e) => setFiltreFournisseur(e.target.value)}
          style={{ height: '34px', borderRadius: '8px', border: '1px solid var(--border)', padding: '0 12px', fontSize: '0.82rem', fontWeight: 600, background: 'var(--bg-surface)', color: 'var(--text-main)' }}
        >
          <option value="tous">Tous les fournisseurs</option>
          {fournisseurs.map(f => <option key={f} value={f}>{f}</option>)}
        </select>
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
            background: 'rgba(198, 40, 40, 0.1)',
            color: 'var(--text-main)',
            border: '1px solid rgba(198, 40, 40, 0.35)',
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

      {showForm && (
        <Modal
          title={editing ? 'Modifier le service' : 'Nouveau service'}
          onClose={() => setShowForm(false)}
          wide
        >
          {[
            { key: 'nom_fournisseur', label: 'Fournisseur', placeholder: 'ex: TOPNET' },
            { key: 'nom_service', label: 'Nom du service', placeholder: 'ex: SHOFHA' },
            { key: 'numero_court', label: 'Numéro court', placeholder: 'ex: 2168000' },
            { key: 'keyword', label: 'Keyword', placeholder: 'ex: mb1' },
            { key: 'prix', label: 'Prix (DT)', placeholder: 'ex: 0.500', type: 'number' },
          ].map((f) => (
            <div key={f.key} style={{ marginBottom: '1rem' }}>
              <label
                style={{
                  display: 'block',
                  marginBottom: '0.4rem',
                  fontWeight: 600,
                  fontSize: '0.9rem',
                  color: 'var(--text-main)',
                }}
              >
                {f.label}
              </label>
              <input
                type={f.type || 'text'}
                value={form[f.key]}
                onChange={(e) => setForm({ ...form, [f.key]: e.target.value })}
                placeholder={f.placeholder}
                step={f.type === 'number' ? 'any' : undefined}
                style={{ width: '100%', padding: '0.7rem 1rem', fontSize: '0.95rem', boxSizing: 'border-box' }}
              />
            </div>
          ))}
          <div style={{ marginBottom: '1rem' }}>
            <label
              style={{
                display: 'block',
                marginBottom: '0.4rem',
                fontWeight: 600,
                fontSize: '0.9rem',
                color: 'var(--text-main)',
              }}
            >
              Type
            </label>
            <select
              value={form.type_service}
              onChange={(e) => setForm({ ...form, type_service: e.target.value })}
              style={{ width: '100%', padding: '0.7rem 1rem', fontSize: '0.95rem' }}
            >
              {TYPE_OPTIONS.map((t) => (
                <option key={t} value={t}>
                  {t}
                </option>
              ))}
            </select>
          </div>
          <label style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', marginBottom: '1.5rem', cursor: 'pointer', color: 'var(--text-main)' }}>
            <input
              type="checkbox"
              checked={form.actif}
              onChange={(e) => setForm({ ...form, actif: e.target.checked })}
            />
            Service actif
          </label>
          <div style={{ display: 'flex', gap: '0.75rem', justifyContent: 'flex-end' }}>
            <button type="button" onClick={() => setShowForm(false)} className="btn btn-ghost">
              Annuler
            </button>
            <button type="button" onClick={save} className="btn btn-primary">
              Enregistrer
            </button>
          </div>
        </Modal>
      )}

      {loading ? (
        <p style={{ color: 'var(--text-muted)', textAlign: 'center', padding: '3rem' }}>Chargement...</p>
      ) : (
        <div className="panel table-wrap" style={{ overflow: 'auto' }}>
          <table className="table-mobile table-dense" style={{ width: '100%', borderCollapse: 'collapse' }}>
            <thead>
              <tr>
                {['Service', 'Fournisseur', 'Keyword', 'Prix', 'Activité 30j', 'Alertes', 'Statut', 'Actions'].map((h) => (
                  <th
                    key={h}
                    style={{
                      padding: '1rem',
                      textAlign: 'left',
                      fontSize: '0.85rem',
                      color: 'var(--text-muted)',
                      fontWeight: 600,
                      borderBottom: '2px solid var(--border)',
                      whiteSpace: 'nowrap',
                    }}
                  >
                    {h}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {displayedServices.map((s) => {
                const prixLabel = s.prixDisplay;
                const hasAlerts = (s.nb_alertes_ouvertes || 0) > 0;

                return (
                  <tr key={s.id} style={{ background: hasAlerts ? 'rgba(254, 242, 242, 0.3)' : 'transparent' }}>
                    <td data-label="Service" style={{ padding: '0.875rem 1rem' }}>
                      <div style={{ fontWeight: 700, color: 'var(--text-main)' }}>{s.nom_service}</div>
                      <div style={{ fontSize: '11px', color: 'var(--text-muted)' }}>{s.type_service} · {s.numero_court}</div>
                    </td>
                    <td data-label="Fournisseur" style={{ padding: '0.875rem 1rem', color: 'var(--text-main)' }}>
                      {s.nom_fournisseur}
                    </td>
                    <td data-label="Keyword" style={{ padding: '0.875rem 1rem' }}>
                      <span className="chip">{s.keyword}</span>
                    </td>
                    <td data-label="Prix" style={{ padding: '0.875rem 1rem', fontWeight: 600, color: 'var(--success)' }}>
                      {prixLabel} DT
                    </td>
                    <td data-label="Activité 30j" style={{ padding: '0.875rem 1rem' }}>
                      {s.nb_cdr_30j > 0 ? (
                        <>
                          <div style={{ fontWeight: 600, fontSize: '13px' }}>{Number(s.nb_cdr_30j).toLocaleString('fr-FR')} CDR</div>
                          <div style={{ fontSize: '11px', color: 'var(--text-muted)' }}>
                            {Number(s.revenus_30j).toFixed(3)} DT · {s.nb_abonnes_30j} abonnés
                          </div>
                        </>
                      ) : (
                        <span style={{ color: '#94a3b8', fontSize: '12px' }}>Inactif</span>
                      )}
                    </td>
                    <td data-label="Alertes" style={{ padding: '0.875rem 1rem', minWidth: '220px' }}>
                      {hasAlerts ? (
                        <div style={{ display: 'flex', flexDirection: 'column', gap: '4px' }}>
                          <span style={{
                            display: 'inline-flex',
                            alignItems: 'center',
                            gap: '4px',
                            background: s.urgence_alerte === 'critique' ? 'rgba(220, 38, 38, 0.15)' : s.urgence_alerte === 'haute' ? 'rgba(245, 158, 11, 0.15)' : 'var(--bg-surface)',
                            color: s.urgence_alerte === 'critique' ? 'var(--danger)' : s.urgence_alerte === 'haute' ? 'var(--warning)' : 'var(--text-muted)',
                            border: '1px solid',
                            borderColor: s.urgence_alerte === 'critique' ? '#fecaca' : s.urgence_alerte === 'haute' ? '#fde68a' : '#fed7aa',
                            borderRadius: '999px',
                            padding: '2px 10px',
                            fontSize: '11px',
                            fontWeight: 700,
                            width: 'fit-content',
                          }}>
                            ⚠ {s.nb_alertes_ouvertes} alerte{s.nb_alertes_ouvertes > 1 ? 's' : ''} · {s.urgence_alerte.toUpperCase()}
                          </span>
                          <div style={{ fontSize: '11px', color: '#64748b' }}>
                            {Number(s.total_sms_suspects).toLocaleString('fr-FR')} SMS suspects
                            {s.ratio_suspects_pct > 0 && (
                              <span style={{ color: '#dc2626', fontWeight: 600 }}> ({s.ratio_suspects_pct}%)</span>
                            )}
                          </div>
                          <div style={{ fontSize: '10px', color: '#94a3b8' }}>Seuil max : {s.seuil_max}%</div>
                          <div style={{ fontSize: '10px', color: '#94a3b8' }}>Depuis : {new Date(s.derniere_alerte).toLocaleDateString('fr-FR')}</div>
                          <button
                            onClick={() => navigate(`/alerts?keyword=${s.keyword}`)}
                            style={{
                              background: 'var(--bg-elevated)',
                              border: '1px solid var(--border)',
                              borderRadius: '4px',
                              padding: '3px 8px',
                              fontSize: '11px',
                              color: 'var(--primary)',
                              cursor: 'pointer',
                              marginTop: '4px',
                              width: 'fit-content',
                              fontWeight: 500
                            }}
                          >
                            Voir les alertes →
                          </button>
                        </div>
                      ) : (
                        <span style={{ color: '#16a34a', fontSize: '12px', fontWeight: 500 }}>✓ Aucune alerte</span>
                      )}
                    </td>
                    <td data-label="Statut" style={{ padding: '0.875rem 1rem' }}>
                      <span className={`badge ${s.actif ? 'badge-ok' : 'badge-danger'}`}>
                        {s.actif ? 'Actif' : 'Inactif'}
                      </span>
                    </td>
                    <td data-label="Actions" style={{ padding: '0.875rem 1rem', whiteSpace: 'nowrap', minWidth: '150px' }}>
                      <div style={{ display: 'flex', gap: '0.5rem' }}>
                        <button type="button" onClick={() => openEdit(s)} className="btn btn-soft btn-pill">
                          Détails
                        </button>
                        <button type="button" onClick={() => del(s.id)} className="btn btn-ghost btn-pill" style={{ color: 'var(--danger)' }}>
                          Suppr.
                        </button>
                      </div>
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}
