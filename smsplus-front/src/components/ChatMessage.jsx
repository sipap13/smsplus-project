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
      <div>{message.content}</div>
      {message.data && (
        <details className="chatbot-ai-details">
          <summary>Détails des résultats ({message.data.count} lignes)</summary>
          <pre>{JSON.stringify(message.data, null, 2)}</pre>
        </details>
      )}
      {message.sql && (
        <details className="chatbot-ai-details">
          <summary>Requête SQL générée</summary>
          <pre>{message.sql}</pre>
        </details>
      )}
      <div className="chatbot-message-meta">{formatTimestamp(message.timestamp)}</div>
    </div>
  );
}
