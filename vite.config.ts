import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

export default defineConfig({
  plugins: [react()],
  build: {
    outDir: 'rald-connect/admin/js/dist',
    rollupOptions: {
      input: 'rald-connect/admin/js/src/main.tsx',
      output: {
        entryFileNames: 'admin.js',
        chunkFileNames: '[name].js',
        assetFileNames: '[name].[ext]',
      },
    },
    lib: undefined,
  },
  root: '.',
})
