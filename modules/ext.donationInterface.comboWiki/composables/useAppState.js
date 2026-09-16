const { ref } = require( 'vue' );

const error = ref( null );
const loading = ref( false );
const variant = null;
const showRecurringConvert = ref( false );

module.exports = {
	useAppState: function () {
		return {
			error,
			loading,
			variant,
			showRecurringConvert,
			/**
			 * Sets the error to the message or null
			 *
			 * @param {string} message
			 */
			setError( message ) {
				error.value = message || null;
			},
			clearError() {
				error.value = null;
			},
			/**
			 * Sets the loading flag to true or false
			 *
			 * @param {boolean} value value to pass to the flag.
			 */
			setLoading( value ) {
				loading.value = Boolean( value );
			},
			/**
			 * Sets the showRecurringConvert flag to true or false
			 *
			 * @param {boolean} value value to pass to the flag.
			 */
			setShowRecurringConvert( value ) {
				showRecurringConvert.value = Boolean( value );
			}
		};
	}
};
