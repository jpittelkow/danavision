import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { Toaster } from '@/Components/ui/sonner';

createInertiaApp({
  title: (title) => (title ? `${title} - DanaVision` : 'DanaVision'),
  resolve: (name) =>
    resolvePageComponent(
      `./Pages/${name}.tsx`,
      import.meta.glob('./Pages/**/*.tsx')
    ),
  setup({ el, App, props }) {
    createRoot(el).render(
      <>
        <App {...props} />
        <Toaster position="top-right" richColors />
      </>
    );
  },
  progress: {
    color: '#6B4EAB',
  },
});
