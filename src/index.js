import { createRoot } from '@wordpress/element';
import App from './App';

import './style.css'; 

window.addEventListener( 'DOMContentLoaded', () => {
    const rootElement = document.getElementById( 'rhwc-react-app' );
    if ( rootElement ) {
        const root = createRoot( rootElement );
        root.render( <App /> );
    }
} );
