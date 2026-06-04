import { defineConfig } from 'vite';

export default defineConfig(({ mode }) => {
  return {
    build: {
      manifest: true,
      rollupOptions: {
        input: 'js/vendor.js',
        output: {
          entryFileNames: (assetInfo) => {
            return `${assetInfo.name}.js`;
          },
        },
      },
      lib: {
        entry: 'js/vendor.js',
        name: 'GuidedTourVendor',
        formats: ['iife'],
        fileName: () => 'js/vendor.js',
      },
      outDir: 'js/dist',
      emptyOutDir: true,
    },
    define: {
      'process.env.NODE_ENV':
        mode === 'production' ? '"production"' : '"development"',
    },
  };
});