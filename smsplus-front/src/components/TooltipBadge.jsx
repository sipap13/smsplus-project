import { useState } from 'react';

export default function TooltipBadge({
  icon,
  label,
  tooltip,
  color = 'gray',
  visible = true,
  align = 'center', // 'center', 'left', 'right'
}) {
  const [show, setShow] = useState(false);

  if (!visible) return null;

  const colors = {
    green:  {
      bg: '#f0fdf4', text: '#16a34a',
      border: '#bbf7d0', dot: '#16a34a'
    },
    blue:   {
      bg: '#eff6ff', text: '#1d4ed8',
      border: '#bfdbfe', dot: '#3b82f6'
    },
    orange: {
      bg: '#fffbeb', text: '#d97706',
      border: '#fde68a', dot: '#f59e0b'
    },
    gray:   {
      bg: '#f8fafc', text: '#64748b',
      border: '#e2e8f0', dot: '#94a3b8'
    },
  };

  const c = colors[color] || colors.gray;

  // Styles de positionnement selon l'alignement
  const posStyles = {
    center: { left: '50%', transform: 'translateX(-50%)' },
    left:   { left: '0', transform: 'translateX(0)' },
    right:  { right: '0', transform: 'translateX(0)', left: 'auto' },
  };

  const arrowStyles = {
    center: { left: '50%', transform: 'translateX(-50%) rotate(45deg)' },
    left:   { left: '20px', transform: 'rotate(45deg)' },
    right:  { right: '20px', transform: 'rotate(45deg)', left: 'auto' },
  };

  return (
    <div style={{ position: 'relative', display: 'inline-block' }}>

      {/* Bouton */}
      <button
        onMouseEnter={() => setShow(true)}
        onMouseLeave={() => setShow(false)}
        onFocus={() => setShow(true)}
        onBlur={() => setShow(false)}
        onTouchStart={() => setShow(true)}
        onTouchEnd={() => setShow(false)}
        style={{
          display: 'inline-flex',
          alignItems: 'center',
          gap: '6px',
          background: c.bg,
          color: c.text,
          border: `1px solid ${c.border}`,
          borderRadius: '999px',
          padding: '4px 12px',
          fontSize: '12px',
          fontWeight: 600,
          cursor: 'default',
          userSelect: 'none',
          outline: 'none',
          transition: 'all 0.15s ease',
          boxShadow: show ? `0 0 0 3px ${c.border}` : 'none',
        }}
      >
        {/* Point coloré animé */}
        <span style={{
          width: '7px', height: '7px',
          borderRadius: '50%',
          background: c.dot,
          display: 'inline-block',
          flexShrink: 0,
        }}/>
        {icon && (
          <span style={{ fontSize: '13px' }}>
            {icon}
          </span>
        )}
        {label}
      </button>

      {/* Tooltip */}
      {show && (
        <div style={{
          position: 'absolute',
          top: 'calc(100% + 12px)',
          background: '#0f172a',
          color: '#f1f5f9',
          borderRadius: '8px',
          padding: '12px 16px',
          fontSize: '12px',
          lineHeight: '1.6',
          whiteSpace: 'pre-line',
          width: 'max-content',
          minWidth: '200px',
          maxWidth: '280px',
          zIndex: 9999,
          boxShadow: '0 10px 30px rgba(0,0,0,0.3)',
          pointerEvents: 'none',
          animation: 'fadeInDown 0.15s ease',
          ...posStyles[align]
        }}>
          {/* Flèche */}
          <div style={{
            position: 'absolute',
            top: '-5px',
            background: '#0f172a',
            width: '10px', height: '10px',
            ...arrowStyles[align]
          }}/>
          {tooltip}
        </div>
      )}
    </div>
  );
}
