import api from './axios';

/**
 * Fonction pour télécharger un fichier Excel avec les filtres appliqués
 * @param {string} endpoint - URL de l'API d'export (ex: '/export/occ')
 * @param {object} filters - Objet contenant les filtres appliqués
 * @param {string} defaultFilename - Nom par défaut du fichier
 * @param {function} onStart - Callback appelé au début du téléchargement
 * @param {function} onError - Callback appelé en cas d'erreur
 * @param {function} onSuccess - Callback appelé après succès
 */
export const downloadExcel = async (
  endpoint,
  filters = {},
  defaultFilename = 'export.xlsx',
  onStart = null,
  onError = null,
  onSuccess = null
) => {
  try {
    if (onStart) onStart();

    // Construire les paramètres de requête à partir des filtres
    const params = new URLSearchParams();
    Object.keys(filters).forEach((key) => {
      if (filters[key]) {
        params.append(key, filters[key]);
      }
    });

    const url = params.toString() ? `${endpoint}?${params.toString()}` : endpoint;

    // Récupérer le fichier en tant que blob
    const response = await api.get(url, {
      responseType: 'blob',
    });

    // Récupérer le nom du fichier depuis le header Content-Disposition
    const contentDisposition = response.headers['content-disposition'];
    let filename = defaultFilename;
    if (contentDisposition) {
      const filenameMatch = contentDisposition.match(/filename="?([^"]+)"?/);
      if (filenameMatch) {
        filename = filenameMatch[1];
      }
    }

    // Créer un lien temporaire et déclencher le téléchargement
    const url_blob = window.URL.createObjectURL(new Blob([response.data]));
    const link = document.createElement('a');
    link.href = url_blob;
    link.setAttribute('download', filename);
    document.body.appendChild(link);
    link.click();
    link.parentNode.removeChild(link);
    window.URL.revokeObjectURL(url_blob);

    if (onSuccess) onSuccess();
  } catch (error) {
    console.error('Erreur lors du téléchargement Excel:', error);
    if (onError) onError(error?.response?.data?.message || 'Erreur lors de l\'export');
  }
};
