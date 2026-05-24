 
/**
 * Modal simple (overlay + carte), sans librairie externe.
 * Le parent contrôle l’affichage : ne monter le composant que lorsque la modale est ouverte.
 */
export default function Modal({ title, children, onClose, wide = false }) {
  return (
    <div
      className="modal-overlay"
      role="presentation"
      onClick={onClose}
      onKeyDown={(e) => e.key === 'Escape' && onClose()}
    >
      <div
        className="modal-card"
        role="dialog"
        aria-modal="true"
        aria-labelledby="modal-title"
        style={{ maxWidth: wide ? 'min(92vw, 720px)' : 'min(92vw, 480px)', width: '100%' }}
        onClick={(e) => e.stopPropagation()}
      >
        <div style={{ display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between', gap: '1rem', marginBottom: '1.25rem' }}>
          <h2 id="modal-title" className="text-heading" style={{ margin: 0, fontSize: '1.15rem', fontWeight: 700, flex: 1 }}>
            {title}
          </h2>
          <button
            type="button"
            onClick={onClose}
            aria-label="Fermer"
            className="btn btn-ghost"
            style={{ padding: '0.35rem 0.55rem', fontSize: '1.25rem', lineHeight: 1, flexShrink: 0 }}
          >
            ×
          </button>
        </div>
        {children}
      </div>
    </div>
  );
}
