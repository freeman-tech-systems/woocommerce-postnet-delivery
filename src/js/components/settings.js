/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';

/**
 * Settings JS handler.
 */
const Settings = {
	storeSelect: null,
	storeEmail: null,
	productImport: null,
	init() {
		this.storeSelect = document.querySelector( '#postnet_store' );
		this.storeEmail = document.querySelector( '#postnet_store_email' );
		this.productImport = document.querySelector( '#postnet_delivery_csv' );
		if ( this.storeSelect && this.storeEmail ) {
			this.bindStoreSelect();
		}
		if ( this.productImport ) {
			this.bindImport();
		}
	},
	bindStoreSelect() {
		this.storeSelect.addEventListener( 'change', () => {
			this.resetEmail();
		} );
	},
	resetEmail() {
		this.storeEmail.value = this.storeSelect.querySelector( `option[value="${ this.storeSelect.value }"]` ).dataset.email;
	},
	bindImport() {
		this.productImport.addEventListener( 'change', () => {
			if ( confirm( __( 'Proceed with import?', 'woocommerce_postnet_delivery' ) ) ) {
				this.productImport.parentNode.submit();
			}
		} );
	}
};

export default Settings;
