import { useState, useEffect } from 'react';

export default function useLocalState(key, initial) {
  const [state, setState] = useState(() => {
    try {
      const raw = localStorage.getItem(key);
      if (raw !== null) return JSON.parse(raw);
    } catch (e) {
      // ignore
    }
    return typeof initial === 'function' ? initial() : initial;
  });

  useEffect(() => {
    try { localStorage.setItem(key, JSON.stringify(state)); } catch (e) { /* ignore */ }
  }, [key, state]);

  return [state, setState];
}
