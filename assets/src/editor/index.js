import { createRoot, render } from '@wordpress/element';
import App from './App';
import './editor.css';

const mount = document.getElementById( 'corinthian-editor' );
if ( mount ) {
	if ( createRoot ) {
		createRoot( mount ).render( <App /> );
	} else {
		render( <App />, mount );
	}
}
