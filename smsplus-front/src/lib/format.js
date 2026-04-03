export function formatDT(value) {
  const n = Number(value);
  if (!Number.isFinite(n)) return '0.000 DT';
  return `${n.toFixed(3)} DT`;
}

export function formatCompactNumber(value) {
  const n = Number(value);
  if (!Number.isFinite(n)) return '0';
  try {
    return new Intl.NumberFormat('fr-FR', { notation: 'compact', maximumFractionDigits: 1 }).format(n);
  } catch {
    return n.toLocaleString('fr-FR');
  }
}

