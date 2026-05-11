/* eslint-disable react/prop-types */
import { useEffect, useMemo, useRef, useState } from 'react';
import api from '../api/axios';

const suggestions = [
  'Quels sont les revenus totaux ?',
  'Quel est le service le plus utilisé ?',
  'Combien d\'abonnés actifs ?',
  'Compare OCC vs MMG',
];

export default function AiChatWidget({  }) {
  const [open, setOpen] = useState(false);
  const [messages, setMessages] = useState([]);
  const [draft, setDraft] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const scrollRef = useRef(null);

  const welcomeMessage = useMemo(
    () => ({
      role: 'assistant',
      text: 'Bonjour ! Je suis l\'assistant SMS+ IA. Demande-moi quelque chose sur les CDR.',
      id: 'welcome',
    }),
    []
  );

  useEffect(() => {
    if (!open) return;
    setMessages((current) => {
      if (current.some((message) => message.id === 'welcome')) {
        return current;
      }
      return [welcomeMessage, ...current];
    });
  }, [open, welcomeMessage]);

  useEffect(() => {
    if (scrollRef.current) {
      scrollRef.current.scrollTop = scrollRef.current.scrollHeight;
    }
  }, [messages, loading]);

  const sendMessage = async (text) => {
    if (!text || loading) return;
    setError('');

    const nextMessage = { role: 'user', text, id: `user-${Date.now()}` };
    setMessages((current) => [...current, nextMessage]);
    setDraft('');
    setLoading(true);

    const token = (localStorage.getItem('token') || '').trim();
    if (!token) {
      setLoading(false);
      setError('Jeton d\'authentification introuvable. Veuillez vous reconnecter.');
      return;
    }

    try {
      const { data: payload } = await api.post('/ai/chat', { message: text });
      setMessages((current) => [
        ...current,
        { role: 'assistant', text: payload.response || "Je n'ai pas de réponse.", id: `assistant-${Date.now()}` },
      ]);
    } catch (err) {
      const msg = err.response?.data?.error || err.response?.data?.message || err.message || 'Une erreur est survenue.';
      setError(msg);
    } finally {
      setLoading(false);
    }
  };

  const handleSubmit = (event) => {
    event.preventDefault();
    sendMessage(draft.trim());
  };

  const handleSuggestion = (suggestion) => {
    sendMessage(suggestion);
  };

  return (
    <div>
      <button
        type="button"
        onClick={() => setOpen((prev) => !prev)}
        style={{
          position: 'fixed',
          right: 24,
          bottom: 24,
          width: 56,
          height: 56,
          borderRadius: '50%',
          border: 'none',
          background: '#1d4ed8',
          color: '#ffffff',
          fontSize: 24,
          cursor: 'pointer',
          boxShadow: '0 12px 24px rgba(0,0,0,0.18)',
          zIndex: 1100,
        }}
        aria-label={open ? 'Fermer l\'assistant IA' : 'Ouvrir l\'assistant IA'}
      >
        🤖
      </button>

      {open && (
        <div
          style={{
            position: 'fixed',
            right: 24,
            bottom: 96,
            width: 380,
            maxWidth: 'calc(100vw - 32px)',
            height: 500,
            background: '#ffffff',
            borderRadius: 18,
            boxShadow: '0 24px 48px rgba(15,23,42,0.18)',
            display: 'flex',
            flexDirection: 'column',
            overflow: 'hidden',
            zIndex: 1200,
          }}
        >
          <div
            style={{
              padding: '16px 18px',
              background: '#1d4ed8',
              color: '#ffffff',
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'space-between',
            }}
          >
            <div>
              <div style={{ fontSize: 16, fontWeight: 700 }}>Assistant SMS+ IA</div>
              <div style={{ fontSize: 12, marginTop: 4, opacity: 0.85 }}>Posez votre question sur les CDR.</div>
            </div>
            <button
              type="button"
              onClick={() => setOpen(false)}
              style={{
                border: 'none',
                background: 'transparent',
                color: '#ffffff',
                cursor: 'pointer',
                fontSize: 20,
                lineHeight: 1,
              }}
              aria-label="Fermer"
            >
              ×
            </button>
          </div>

          <div style={{ padding: 14, overflowY: 'auto', flex: 1 }} ref={scrollRef}>
            {messages.length === 0 && (
              <div style={{ color: '#475569', fontSize: 14, lineHeight: 1.6 }}>
                Posez votre première question ou utilisez une suggestion.
              </div>
            )}

            {messages.map((message) => (
              <div
                key={message.id}
                style={{
                  display: 'flex',
                  justifyContent: message.role === 'user' ? 'flex-end' : 'flex-start',
                  marginBottom: 10,
                }}
              >
                <div
                  style={{
                    maxWidth: '78%',
                    padding: '10px 14px',
                    borderRadius: 16,
                    background: message.role === 'user' ? '#1d4ed8' : '#f3f4f6',
                    color: message.role === 'user' ? '#ffffff' : '#0f172a',
                    fontSize: 14,
                    lineHeight: 1.5,
                    whiteSpace: 'pre-wrap',
                    wordBreak: 'break-word',
                  }}
                >
                  {message.text}
                </div>
              </div>
            ))}
            {loading && (
              <div style={{ color: '#64748b', fontSize: 14 }}>...</div>
            )}
            {error && (
              <div style={{ color: '#b91c1c', fontSize: 13, marginTop: 8 }}>{error}</div>
            )}
          </div>

          <div style={{ padding: 14, borderTop: '1px solid #e2e8f0' }}>
            <div style={{ display: 'grid', gap: 8, marginBottom: 12 }}>
              {suggestions.map((suggestion) => (
                <button
                  key={suggestion}
                  type="button"
                  onClick={() => handleSuggestion(suggestion)}
                  style={{
                    border: '1px solid #cbd5e1',
                    borderRadius: 9999,
                    background: '#f8fafc',
                    color: '#0f172a',
                    padding: '8px 12px',
                    fontSize: 13,
                    cursor: 'pointer',
                    textAlign: 'left',
                  }}
                >
                  {suggestion}
                </button>
              ))}
            </div>

            <form onSubmit={handleSubmit} style={{ display: 'flex', gap: 8 }}>
              <input
                type="text"
                value={draft}
                onChange={(event) => setDraft(event.target.value)}
                placeholder="Écrire votre question..."
                style={{
                  flex: 1,
                  padding: '10px 12px',
                  borderRadius: 12,
                  border: '1px solid #cbd5e1',
                  outline: 'none',
                  fontSize: 14,
                }}
                disabled={loading}
              />
              <button
                type="submit"
                disabled={loading || !draft.trim()}
                style={{
                  border: 'none',
                  borderRadius: 12,
                  padding: '0 16px',
                  background: '#1d4ed8',
                  color: '#ffffff',
                  cursor: loading || !draft.trim() ? 'not-allowed' : 'pointer',
                  opacity: loading || !draft.trim() ? 0.6 : 1,
                  fontWeight: 700,
                }}
              >
                Envoyer
              </button>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
