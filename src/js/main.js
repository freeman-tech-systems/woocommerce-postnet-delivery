/**
 * Internal dependencies.
 */
import Settings from './components/settings';

const Main = {
	init() {
		Settings.init();
	}
};

window.addEventListener( 'load', () => Main.init() );
