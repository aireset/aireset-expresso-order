import React from 'react';
import ReactDOM from 'react-dom/client';
import FrontendApp from './FrontendApp';

const mount = document.getElementById('eop-frontend-app');

if (mount) {
  ReactDOM.createRoot(mount).render(
    <React.StrictMode>
      <FrontendApp />
    </React.StrictMode>
  );
}
