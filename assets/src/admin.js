import { render } from '@wordpress/element';
import SettingsApp from './admin/SettingsApp';

const container = document.getElementById(
	'whaze-term-order-for-posts-settings'
);
if ( container ) {
	render( <SettingsApp />, container );
}
