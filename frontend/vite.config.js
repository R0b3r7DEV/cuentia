import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

// https://vite.dev/config/
export default defineConfig({
  plugins: [react()],
  server: {
    proxy: {
      // In development, forward every request starting with /api to the Symfony
      // backend. The browser only ever talks to Vite (port 5173), so there are no
      // CORS problems, and the frontend code can use relative URLs like "/api/health".
      //
      // ES: En desarrollo, reenvía toda petición que empiece por /api al backend
      // Symfony. El navegador solo habla con Vite (puerto 5173), así que no hay
      // problemas de CORS y el frontend puede usar URLs relativas como "/api/health".
      '/api': 'http://127.0.0.1:8000',
    },
  },
})
