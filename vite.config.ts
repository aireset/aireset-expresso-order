import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import { resolve } from 'path';

export default defineConfig({
  plugins: [react()],
  build: {
    outDir: 'assets/admin-spa/dist',
    emptyOutDir: true,
    manifest: true,
    rollupOptions: {
      input: {
        main: resolve(__dirname, 'assets/admin-spa/src/main.tsx'),
        frontend: resolve(__dirname, 'assets/admin-spa/src/frontend.tsx'),
      },
    },
  },
});
