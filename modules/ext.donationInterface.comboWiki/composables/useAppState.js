const { reactive, ref } = require( 'vue' );

const loading = ref( false );
const showMonthlyConvert = ref( false );
const variant = null;

module.exports = {
	useAppState: function () {
		return {
			loading,
			showMonthlyConvert,
			variant,
			/**
			 * Sets the monthly convert modal flag to true or false
			 *
			 * @param {boolean} value value to pass to the flag.
			 */
			setMonthlyConvertModalVisibility( value ) {
				showMonthlyConvert.value = value;
			},
			/**
			 * Sets the loading flag to true or false
			 *
			 * @param {boolean} value value to pass to the flag.
			 */
			setLoading( value ) {
				loading.value = value;
			}
		};
	} };
