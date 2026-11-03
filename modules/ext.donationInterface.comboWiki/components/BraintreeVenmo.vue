<template>
	<div
		id="braintree-venmo"
		class="payment-method-form"
	>
		<cdx-button
			v-show="ready"
			action="progressive"
			weight="primary"
			class="combo-wiki__button-submit"
			@click="startVenmo"
		>
			{{ $i18n( 'combowiki-method-venmo' ).text() }}
		</cdx-button>
	</div>
</template>

<script>
/* global braintree */
const { defineComponent, inject, onMounted, ref } = require( 'vue' );
const { hasValidWalletName, hasValidWalletEmail, showWalletFallbackFields } = require( 'ext.donationInterface.walletFallback' );
const { useAppState } = require( '../composables/useAppState.js' );
const { CdxButton } = require( '@wikimedia/codex' );

let scriptsLoaded = null;

function loadScripts( scripts ) {
	if ( !scriptsLoaded ) {
		scriptsLoaded = scripts.reduce(
			( previous, src ) => previous.then( () => mw.loader.getScript( src ) ),
			Promise.resolve()
		);
	}
	return scriptsLoaded;
}

module.exports = exports = defineComponent( {
	name: 'BraintreeVenmo',
	components: {
		'cdx-button': CdxButton
	},
	props: {
		donation: {
			type: Object,
			required: true
		},
		donorDetails: {
			type: Object,
			default: null
		}
	},
	emits: [ 'submit', 'error' ],

	setup( props, ctx ) {
		const params = inject( 'params' );
		const appState = useAppState();
		const config = params.braintreeConfiguration || {};
		const ready = ref( false );
		let venmoInstance = null;
		let deviceData = null;
		const donorDetailsValid = () => !props.donorDetails || props.donorDetails.validate();

		const emitError = ( error ) => ctx.emit( 'error', error );

		function getPaymentMethodUsage() {
			return props.donation.frequency === 'monthly' || props.donation.frequency === 'annual' ? 'multi_use' : 'single_use';
		}

		function submitApproved( payload ) {
			const details = payload.details || {};
			const payerInfo = details.payerInfo || {};
			const sendData = {
				payment_token: payload.nonce,
				device_data: deviceData,
				user_name: details.username,
				gateway_session_id: details.paymentContextId,
				first_name: payerInfo.firstName || details.firstName,
				last_name: payerInfo.lastName || details.lastName,
				email: payerInfo.email || details.email,
				phone: payerInfo.phoneNumber,
				street_address: payerInfo.shippingAddress,
				customer_id: payerInfo.externalId
			};

			if ( !hasValidWalletName( sendData.first_name ) ||
				!hasValidWalletName( sendData.last_name ) ||
				!hasValidWalletEmail( sendData.email ) ) {
				showWalletFallbackFields(
					$( '#braintree-venmo' ),
					sendData.email,
					( firstName, lastName, email ) => {
						sendData.first_name = firstName;
						sendData.last_name = lastName;
						sendData.email = email;
						ctx.emit( 'submit', sendData );
					}
				);
				return;
			}

			ctx.emit( 'submit', sendData );
		}

		async function setUpVenmo() {
			await loadScripts( config.scripts || [] );
			const client = await braintree.client.create( { authorization: config.clientToken } );
			deviceData = ( await braintree.dataCollector.create( { client } ) ).deviceData;
			venmoInstance = await braintree.venmo.create( {
				client,
				allowDesktop: true,
				mobileWebFallBack: true,
				allowNewBrowserTab: true,
				allowDesktopWebLogin: false,
				paymentMethodUsage: getPaymentMethodUsage(),
				totalAmount: String( props.donation.amount || '' )
			} );
			ready.value = true;
		}

		onMounted( async () => {
			try {
				await setUpVenmo();
			} catch ( error ) {
				emitError( error );
			}
		} );

		function startVenmo() {
			if ( !donorDetailsValid() || !venmoInstance ) {
				return;
			}

			appState.setLoading( true );
			venmoInstance.tokenize()
				.then( ( payload ) => {
					if ( payload ) {
						submitApproved( payload );
					}
				} )
				.catch( emitError )
				.then( () => {
					appState.setLoading( false );
				} );
		}

		return {
			ready,
			startVenmo
		};
	}
} );
</script>
