import api from './axios';

export async function fetchAuditLogs(params) {
  const res = await api.get('/audit-logs', { params });
  return res.data;
}

export async function fetchAuditStats() {
  const res = await api.get('/audit-logs/stats');
  return res.data;
}
