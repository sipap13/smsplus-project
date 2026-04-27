import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

// https://vite.dev/config/
export default defineConfig(({ mode }) => {
  // API target: inside Docker use service name, outside use localhost
  const apiTarget = process.env.API_TARGET || 'http://localhost:8001'

  return {
    plugins: [react()],
    server: {
      host: '0.0.0.0',
      strictPort: true,
      hmr: {
        host: 'localhost',
        port: 5173,
        overlay: false,
      },
      watch: {
        usePolling: true,
      },
      proxy: {
        '/api': {
          target: apiTarget,
          changeOrigin: true,
          secure: false,
        }
      }
    }
  }
})
