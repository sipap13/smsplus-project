/* eslint-disable react/prop-types */
import { useEffect, useState } from 'react';
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

  return (
    <div className="page">
      <div className="page-header">
        <div>
          <h1 className="page-title">Gestion des services</h1>
          <p className="page-subtitle">{services.length} service(s) au total — réservé aux administrateurs</p>
        </div>
        <div style={{ display: 'flex', gap: '0.5rem' }}>
          <button
            type="button"
            onClick={handleExport}
            disabled={exportLoading || services.length === 0}
            style={{
              background: exportLoading ? '#9ca3af' : '#16a34a',
              color: 'white',
              borderRadius: '8px',
              padding: '8px 16px',
              fontSize: '14px',
              fontWeight: '600',
              border: 'none',
              cursor: exportLoading || services.length === 0 ? 'not-allowed' : 'pointer',
              opacity: exportLoading || services.length === 0 ? 0.7 : 1,
              display: 'flex',
              alignItems: 'center',
              gap: '6px',
              transition: 'background 0.2s',
            }}
            onMouseEnter={(e) => {
              if (!exportLoading && services.length > 0) e.target.style.background = '#15803d';
            }}
            onMouseLeave={(e) => {
              if (!exportLoading && services.length > 0) e.target.style.background = '#16a34a';
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
          <button type="button" onClick={openNew} className="btn btn-primary">
            Ajouter un service
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
                {['ID', 'Fournisseur', 'Service', 'N° court', 'Keyword', 'Type', 'Prix (DT)', 'Actif', 'Actions'].map((h) => (
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
              {services.map((s) => {
                const prixNum = parseFloat(s.prix, 10);
                const prixLabel = Number.isFinite(prixNum) ? prixNum.toFixed(3) : '—';
                return (
                  <tr key={s.id}>
                    <td data-label="ID" className="mono" style={{ padding: '0.875rem 1rem', color: 'var(--text-muted)' }}>
                      {s.id}
                    </td>
                    <td data-label="Fournisseur" style={{ padding: '0.875rem 1rem', fontWeight: 600, color: 'var(--text-main)' }}>
                      {s.nom_fournisseur}
                    </td>
                    <td data-label="Service" style={{ padding: '0.875rem 1rem', color: 'var(--text-main)' }}>
                      {s.nom_service}
                    </td>
                    <td data-label="N° court" className="text-heading mono" style={{ padding: '0.875rem 1rem', fontWeight: 600 }}>
                      {s.numero_court}
                    </td>
                    <td data-label="Keyword" style={{ padding: '0.875rem 1rem' }}>
                      <span className="chip">{s.keyword}</span>
                    </td>
                    <td data-label="Type" style={{ padding: '0.875rem 1rem' }}>
                      <span
                        style={{
                          background: s.type_service === 'jeu' ? 'rgba(230, 81, 0, 0.15)' : 'rgba(2, 136, 209, 0.15)',
                          color: s.type_service === 'jeu' ? '#e65100' : '#0288d1',
                          padding: '0.2rem 0.6rem',
                          borderRadius: '20px',
                          fontSize: '0.82rem',
                        }}
                      >
                        {s.type_service || '—'}
                      </span>
                    </td>
                    <td data-label="Prix" style={{ padding: '0.875rem 1rem', fontWeight: 600, color: 'var(--success)' }}>
                      {prixLabel}
                    </td>
                    <td data-label="Actif" style={{ padding: '0.875rem 1rem' }}>
                      <span className={`badge ${s.actif ? 'badge-ok' : 'badge-danger'}`}>
                        {s.actif ? 'Actif' : 'Inactif'}
                      </span>
                    </td>
                    <td data-label="Actions" style={{ padding: '0.875rem 1rem' }}>
                      <div style={{ display: 'flex', gap: '0.5rem', flexWrap: 'wrap' }}>
                        <button type="button" onClick={() => openEdit(s)} className="btn btn-soft btn-pill">
                          Modifier
                        </button>
                        <button type="button" onClick={() => del(s.id)} className="btn btn-ghost btn-pill" style={{ borderColor: 'color-mix(in srgb, var(--danger) 40%, var(--border))', color: 'var(--danger)' }}>
                          Supprimer
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
