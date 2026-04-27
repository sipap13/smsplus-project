/* eslint-disable react/prop-types */
import { useEffect } from 'react';

export default function Toast({ toast, onClose }) {
  useEffect(() => {
    const timer = setTimeout(() => onClose(toast.id), 5000);
    return () => clearTimeout(timer);
  }, [toast.id, onClose]);

  return (
    <div className={`toast toast-${toast.type || 'info'}`}>
      <div style={{ flex: 1 }}>
        <strong style={{ display: 'block', marginBottom: 2 }}>{toast.title}</strong>
        <span style={{ fontSize: '0.82rem' }}>{toast.message}</span>
      </div>
      <button type="button" className="toast-close" onClick={() => onClose(toast.id)}>×</button>
    </div>
  );
}
