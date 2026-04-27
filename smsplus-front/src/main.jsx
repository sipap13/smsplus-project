import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import '@fontsource/inter/400.css'
import '@fontsource/inter/500.css'
import '@fontsource/inter/600.css'
import '@fontsource/inter/700.css'
import '@fontsource/inter/800.css'
import './index.css'
import App from './App.jsx'
import { initTheme } from './theme'

initTheme()

// Swallow noisy browser-extension promise rejections
window.addEventListener('unhandledrejection', (event) => {
  const msg = String(event.reason?.message || event.reason);
  if (msg.includes('message channel closed') || msg.includes('asynchronous response')) {
    event.preventDefault();
  }
});

createRoot(document.getElementById('root')).render(
  <StrictMode>
    <App />
  </StrictMode>,
)
