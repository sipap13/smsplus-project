import axios from 'axios';
import { authNavigate } from '../lib/authNavigation';

const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL || 'http://127.0.0.1:8001/api',
  headers: { 'Accept': 'application/json' },
  timeout: 30000,
});

api.interceptors.request.use((config) => {
  const token = (localStorage.getItem('token') || '').trim();
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

function isLoginRequest(url) {
  return /(^|\/)login\/?(\?|$)/i.test(String(url));
}

function is2faRequest(url) {
  return /(^|\/)(verify-2fa|resend-2fa)\/?(\?|$)/i.test(String(url));
}

function isNetworkError(err) {
  return !err.response && (err.code === 'ECONNABORTED' || err.code === 'ERR_NETWORK' || err.message?.includes('Network Error'));
}

api.interceptors.response.use(
  (res) => res,
  async (err) => {
    const status = err.response?.status;
    const url = err.config?.url || '';

    // Retry once on network / empty-response errors
    if (isNetworkError(err) && err.config && !err.config.__retryCount) {
      err.config.__retryCount = 1;
      await new Promise(r => setTimeout(r, 800));
      return api.request(err.config);
    }

    if (status === 401 && !isLoginRequest(url) && !is2faRequest(url)) {
      localStorage.removeItem('token');
      localStorage.removeItem('user');
      if (!err.config?.skipAuthRedirect) {
        const path = window.location.pathname || '/';
        if (path !== '/' && path !== '/login') {
          authNavigate('/');
        }
      }
    }
    return Promise.reject(err);
  },
);

export default api;
