import { useState, useEffect } from 'react';
import { 
  format, subDays, startOfMonth, endOfMonth, 
  subMonths, differenceInDays 
} from 'date-fns';

export const usePeriode = () => {
  // Try to restore from localStorage
  const saved = localStorage.getItem('dashboard_periode');
  const initial = saved ? JSON.parse(saved) : {
    preset: '7j',
    startDate: format(subDays(new Date(), 7), 'yyyy-MM-dd'),
    endDate: format(new Date(), 'yyyy-MM-dd'),
    label: '7 derniers jours',
  };

  const [periode, setPeriode] = useState(initial);

  useEffect(() => {
    localStorage.setItem('dashboard_periode', JSON.stringify(periode));
  }, [periode]);

  const setPreset = (preset) => {
    const today = new Date();
    const presets = {
      'today': {
        start: format(today, 'yyyy-MM-dd'),
        end: format(today, 'yyyy-MM-dd'),
        label: "Aujourd'hui",
      },
      '7j': {
        start: format(subDays(today, 7), 'yyyy-MM-dd'),
        end: format(today, 'yyyy-MM-dd'),
        label: '7 derniers jours',
      },
      '14j': {
        start: format(subDays(today, 14), 'yyyy-MM-dd'),
        end: format(today, 'yyyy-MM-dd'),
        label: '14 derniers jours',
      },
      '30j': {
        start: format(subDays(today, 30), 'yyyy-MM-dd'),
        end: format(today, 'yyyy-MM-dd'),
        label: '30 derniers jours',
      },
      'ce_mois': {
        start: format(startOfMonth(today), 'yyyy-MM-dd'),
        end: format(today, 'yyyy-MM-dd'),
        label: 'Ce mois',
      },
      'mois_dernier': {
        start: format(startOfMonth(subMonths(today, 1)), 'yyyy-MM-dd'),
        end: format(endOfMonth(subMonths(today, 1)), 'yyyy-MM-dd'),
        label: 'Mois dernier',
      },
    };
    
    if (presets[preset]) {
      setPeriode({
        preset,
        startDate: presets[preset].start,
        endDate: presets[preset].end,
        label: presets[preset].label,
      });
    }
  };

  const setCustom = (startDate, endDate) => {
    if (!startDate || !endDate) return;
    const diffDays = differenceInDays(new Date(endDate), new Date(startDate));
    setPeriode({
      preset: 'custom',
      startDate,
      endDate,
      label: `${format(new Date(startDate), 'dd/MM')} → ${format(new Date(endDate), 'dd/MM/yyyy')} (${diffDays}j)`,
    });
  };

  return { periode, setPreset, setCustom, setPeriode };
};
