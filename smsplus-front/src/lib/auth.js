import api from '../api/axios';
import { isValidSessionUser } from './sessionUser';

export function getStoredUser() {
  const saved = localStorage.getItem('user');
  if (!saved) return null;
  try {
    return JSON.parse(saved);
  } catch {
    localStorage.removeItem('user');
    return null;
  }
}

function getTokenRaw() {
  try {
    return localStorage.getItem('token');
  } catch {
    return null;
  }
}

/** User in localStorage is only meaningful alongside a non-empty token and a valid shape. */
export function syncStoredUserWithToken() {
  const token = (getTokenRaw() || '').trim();
  if (!token) {
    if (getTokenRaw()) localStorage.removeItem('token');
    if (localStorage.getItem('user')) localStorage.removeItem('user');
    return null;
  }
  const u = getStoredUser();
  if (!isValidSessionUser(u)) {
    if (u) localStorage.removeItem('user');
    return null;
  }
  return u;
}

export function clearAuth() {
  localStorage.removeItem('token');
  localStorage.removeItem('user');
}

export async function fetchMe() {
  const res = await api.get('/me', { skipAuthRedirect: true });
  if (!isValidSessionUser(res.data)) {
    throw new Error('Invalid /me payload');
  }
  localStorage.setItem('user', JSON.stringify(res.data));
  return res.data;
}

