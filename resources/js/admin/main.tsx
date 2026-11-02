import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { BrowserRouter } from 'react-router-dom';
import { App } from './App';
import { AuthProvider } from './auth';
import { TourProvider } from './tour/TourProvider';

const root = document.getElementById('admin-root');

if (root) {
  createRoot(root).render(
    <StrictMode>
      <BrowserRouter basename={window.__ADMIN__.path}>
        <AuthProvider>
          <TourProvider>
            <App />
          </TourProvider>
        </AuthProvider>
      </BrowserRouter>
    </StrictMode>,
  );
}
