import api from './axios';

export const analyzeQuestion = async (question) => {
  const response = await api.post('/chatbot/analyze', { question });
  return response.data;
};
