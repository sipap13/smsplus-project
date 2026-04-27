import { useState } from 'react';
import ChatMessage from '../components/ChatMessage';
import { analyzeQuestion } from '../api/chatbot';

const examplePrompts = [
  'Combien d’appels OCC ont été traités hier ?',
  'Donne le volume total MMG et le nombre de transactions réussies la semaine dernière.',
  'Quel est le revenu moyen par MSISDN pour les services premium ?',
];

export default function Chatbot() {
  const [messages, setMessages] = useState([]);
  const [input, setInput] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');

  const handleSubmit = async (event) => {
    event.preventDefault();
    const question = input.trim();
    if (!question || loading) {
      return;
    }

    const userMessage = {
      type: 'user',
      content: question,
      timestamp: new Date().toISOString(),
    };

    setMessages((current) => [...current, userMessage]);
    setInput('');
    setError('');
    setLoading(true);

    try {
      const response = await analyzeQuestion(question);
      const aiMessage = {
        type: 'ai',
        content: response.response || 'Aucune réponse générée.',
        data: response.data,
        sql: response.sql_query,
        timestamp: new Date().toISOString(),
      };
      setMessages((current) => [...current, aiMessage]);
    } catch (err) {
      setError('Une erreur est survenue lors de l’analyse. Merci de réessayer.');
      setMessages((current) => [
        ...current,
        {
          type: 'error',
          content: err.response?.data?.message || err.message || 'Erreur inconnue',
          timestamp: new Date().toISOString(),
        },
      ]);
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="chatbot-page">
      <div className="chatbot-panel">
        <header className="chatbot-header">
          <div>
            <h1 className="chatbot-welcome">Chatbot IA CDR</h1>
            <p className="chatbot-hint">Posez votre question en français sur les données CDR et obtenez une analyse basée sur les vraies données.</p>
          </div>
          <div className="chatbot-hint" style={{ textAlign: 'right', minWidth: '220px' }}>
            Essais :<br />{examplePrompts.join(' • ')}
          </div>
        </header>

        <div className="chatbot-area">
          <div className="chatbot-messages">
            {messages.length === 0 && (
              <div className="chatbot-empty">Entrez une question pour démarrer la conversation.</div>
            )}
            {messages.map((message, index) => (
              <ChatMessage key={index} message={message} />
            ))}
            {loading && (
              <div className="chatbot-message ai">
                <div className="chatbot-typing">
                  <span />
                  <span />
                  <span />
                </div>
              </div>
            )}
          </div>

          {error && <div className="chatbot-error">{error}</div>}

          <form className="chatbot-input-row" onSubmit={handleSubmit}>
            <input
              type="text"
              value={input}
              onChange={(event) => setInput(event.target.value)}
              placeholder="Posez votre question sur les données CDR..."
              disabled={loading}
            />
            <button type="submit" disabled={loading || !input.trim()}>
              {loading ? 'Analyse...' : 'Envoyer'}
            </button>
          </form>
        </div>
      </div>
    </div>
  );
}
