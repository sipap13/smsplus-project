import api from '../api/axios';

export function getStoredUser() {
  const saved = localStorage.getItem('user');
  return saved ? JSON.parse(saved) : null;
}

export function clearAuth() {
  localStorage.removeItem('token');
  localStorage.removeItem('user');
}

export async function fetchMe() {
  const res = await api.get('/me');
  localStorage.setItem('user', JSON.stringify(res.data));
  return res.data;
}

