import { createRoot } from '@wordpress/element';
import App from './App';

import './style.css';

// Scripts in the footer run after the DOM is ready — no DOMContentLoaded needed.
const rootElement = document.getElementById( 'rhwc-react-app' );
if ( rootElement ) {
	const root = createRoot( rootElement );
	root.render( <App /> );
}
