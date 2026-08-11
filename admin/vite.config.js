import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

export default defineConfig({
  plugins: [react()],
  // Production is served under Apache Alias /admin
  base: process.env.VITE_BASE || '/admin/',
  server: {
    host: '127.0.0.1',
    port: 4401,
    proxy: {
      '/api': {
        target: 'http://127.0.0.1:4402',
        changeOrigin: true,
      },
      '/storage': {
        target: 'http://127.0.0.1:4402',
        changeOrigin: true,
      },
    },
  },
})
