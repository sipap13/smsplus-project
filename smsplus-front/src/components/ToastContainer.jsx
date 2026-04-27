/* eslint-disable react/prop-types */
import Toast from './Toast';

export default function ToastContainer({ toasts, onClose }) {
  if (!toasts?.length) {
    return null;
  }

  return (
    <div className="toast-container">
      {toasts.map((toast) => (
        <Toast key={toast.id} toast={toast} onClose={onClose} />
      ))}
    </div>
  );
}
