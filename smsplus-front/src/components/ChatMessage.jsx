import PropTypes from 'prop-types';

export default function ChatMessage({ message }) {
  const formatTimestamp = (timestamp) => {
    try {
      return new Date(timestamp).toLocaleTimeString('fr-FR', {
        hour: '2-digit',
        minute: '2-digit',
      });
    } catch {
      return '';
    }
  };

  return (
    <div className={`chatbot-message ${message.type}`}>
      <div style={{ whiteSpace: 'pre-wrap' }}>{message.content}</div>
      
      {message.sql && (
        <details style={{
          marginTop: '12px',
          fontSize: '11px',
          color: '#94a3b8',
          borderTop: '1px solid rgba(226, 232, 240, 0.1)',
          paddingTop: '8px'
        }}>
          <summary style={{ cursor: 'pointer', fontWeight: 600, color: '#64748b' }}>
            Voir la requête SQL exécutée
          </summary>
          <pre style={{
            background: '#0f172a',
            color: '#38bdf8',
            padding: '10px',
            borderRadius: '6px',
            fontSize: '11px',
            marginTop: '6px',
            overflow: 'auto',
            border: '1px solid #1e293b',
            fontFamily: 'Fira Code, monospace'
          }}>
            {message.sql}
          </pre>
          
          <div style={{ 
            marginTop: '6px', 
            display: 'flex', 
            alignItems: 'center', 
            gap: '8px',
            fontSize: '10px',
            color: '#64748b'
          }}>
            <span style={{ 
              background: '#1e293b', 
              padding: '2px 6px', 
              borderRadius: '4px' 
            }}>
              {message.data?.count || 0} lignes analysées
            </span>
            
            {message.data?.is_complete === false && (
              <span style={{ color: '#f59e0b', display: 'flex', alignItems: 'center', gap: '4px' }}>
                <span style={{ width: '6px', height: '6px', background: '#f59e0b', borderRadius: '50%' }}></span>
                Résultats tronqués à 50 pour l'affichage
              </span>
            )}

            {message.data?.is_complete === true && (
              <span style={{ color: '#10b981', display: 'flex', alignItems: 'center', gap: '4px' }}>
                <span style={{ width: '6px', height: '6px', background: '#10b981', borderRadius: '50%' }}></span>
                Résultats complets
              </span>
            )}
          </div>
        </details>
      )}

      {message.data && message.type === 'ai' && !message.sql && (
         <details style={{ marginTop: '8px', fontSize: '11px' }}>
            <summary style={{ cursor: 'pointer', color: '#94a3b8' }}>Données brutes</summary>
            <pre style={{ fontSize: '10px', background: '#f8fafc', padding: '8px' }}>
              {JSON.stringify(message.data.sample || message.data.data, null, 2)}
            </pre>
         </details>
      )}

      <div className="chatbot-message-meta" style={{ marginTop: '4px', opacity: 0.6, fontSize: '10px' }}>
        {formatTimestamp(message.timestamp)}
      </div>
    </div>
  );
}

ChatMessage.propTypes = {
  message: PropTypes.shape({
    type: PropTypes.string.isRequired,
    content: PropTypes.string,
    data: PropTypes.shape({
      count: PropTypes.number,
    }),
    sql: PropTypes.string,
    timestamp: PropTypes.string,
  }).isRequired,
};
