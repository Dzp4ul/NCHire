import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

export default defineConfig({
  plugins: [react()],
  server: {
    port: 5173,
    proxy: {
      '/api': 'http://localhost:8080',
      '/admin': 'http://localhost:8080',
      '/user': 'http://localhost:8080',
      '/uploads': 'http://localhost:8080',
    },
  },
  optimizeDeps: {
    exclude: ['lucide-react'],
  },
});
