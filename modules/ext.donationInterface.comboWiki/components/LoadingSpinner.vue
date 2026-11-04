<template>
	<div v-show="loading" class="combo-wiki__loading-spinner">
		<div class="combo-wiki__loading-stack">
			<img
				id="loading"
				:src="overlayIconURL"
				class="loading-spinner__img"
				alt="Image of Baby Globe floating in outer space"
			>
		</div>
	</div>
</template>

<script>
const { defineComponent } = require( 'vue' );
const { useAppState } = require( '../composables/useAppState.js' );

module.exports = exports = defineComponent( {
	name: 'LoadingSpinner',
	setup() {
		const { inject } = require( 'vue' );
		const params = inject( 'params' );
		const { loading } = useAppState();
		return {
			loading,
			overlayIconURL: `${ params.script_path }/extensions/DonationInterface/gateway_forms/includes/loading-babyglobe.webp`
		};
	}
} );
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

@combo-wiki-loading-image-in-speed: 400ms;

.combo-wiki__loading-spinner {
	z-index: 999;
	position: fixed;
	left: 0;
	top: 0;
	bottom: 0;
	right: 0;
	inset: 0;
	display: flex;
	align-items: center;
	justify-content: center;
	background: @background-color-backdrop-dark;

	img {
		width: 160px;
	}

	.combo-wiki__loading-stack {
		position: relative;
		display: inline-block;
		line-height: 0;
	}

	// Fades and zooms the image in each time the overlay is shown
	.loading-spinner__img {
		animation: combo-wiki-loading-image-in @combo-wiki-loading-image-in-speed ease-out both;

		@media ( prefers-reduced-motion: reduce ) {
			animation-name: combo-wiki-loading-image-fade;
		}
	}

	@keyframes combo-wiki-loading-image-in {
		from {
			opacity: 0;
			transform: scale( 0.92 );
		}

		to {
			opacity: 1;
			transform: scale( 1 );
		}
	}

	// Fade only, for reduced motion
	@keyframes combo-wiki-loading-image-fade {
		from {
			opacity: 0;
		}

		to {
			opacity: 1;
		}
	}
}
</style>
