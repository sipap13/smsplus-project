import { useState, useEffect } from 'react';
import api from '../api/axios';

let cachedMapping = null;

export function useServiceMapping() {
  const [mapping, setMapping] = useState(
    cachedMapping || {}
  );
  const [services, setServices] = useState(
    cachedMapping ? Object.values(cachedMapping) : []
  );
  const [loading, setLoading] = useState(
    !cachedMapping
  );

  useEffect(() => {
    if (cachedMapping) return;
    
    api.get('/services/mapping')
      .then(res => {
        const map = {};
        res.data.forEach(s => {
          map[s.keyword] = s;
        });
        cachedMapping = map;
        setMapping(map);
        setServices(Object.values(map));
      })
      .catch(() => {})
      .finally(() => setLoading(false));
  }, []);

  // Convertit un keyword en nom lisible
  const getNom = (keyword) => {
    if (!keyword) return '—';
    return mapping[keyword]?.nom_service ?? keyword;
  };

  // Convertit un keyword en label complet
  const getLabel = (keyword) => {
    if (!keyword) return '—';
    return mapping[keyword]?.nom_complet ?? keyword;
  };

  // Convertit un keyword en nom + fournisseur
  const getLabelFull = (keyword) => {
    if (!keyword) return '—';
    return mapping[keyword]?.label ?? keyword;
  };

  return {
    mapping,
    services,
    loading,
    getNom,
    getLabel,
    getLabelFull,
  };
}

export default useServiceMapping;
