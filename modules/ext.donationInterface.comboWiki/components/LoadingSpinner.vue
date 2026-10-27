<template>
	<div class="combo-wiki__loading-spinner" v-show="loading">
		<div class="combo-wiki__loading-stack">
			<img
				id="loading"
				:src="overlayIconURL"
				class="loading-spinner__img"
				alt="Image of Baby Globe floating in outer space"
			>
			<div
				class="combo-wiki__spinner"
				role="status"
				aria-label="Loading"
			>
				<i v-for="n in 12" :key="n"><b></b></i>
			</div>
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

@combo-wiki-spinner-size: 24px;
@combo-wiki-spinner-speed: 1.4s;
@combo-wiki-spinner-bars: 12;
@combo-wiki-spinner-float-speed: 3s;
@combo-wiki-spinner-float-height: 9px;
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

	.combo-wiki__spinner {
		position: absolute;
		z-index: 1;
		top: 20%;
		left: 10%;
		width: @combo-wiki-spinner-size;
		height: @combo-wiki-spinner-size;
		margin: ( -@combo-wiki-spinner-size / 2 ) 0 0 ( -@combo-wiki-spinner-size / 2 );
		animation: combo-wiki-spinner-float @combo-wiki-spinner-float-speed ease-in-out infinite;

		// Each <i> is a fixed, rotated slot; the <b> inside it animates
		i {
			position: absolute;
			top: 50%;
			left: 50%;
			width: 10%;
			height: 22%;
			margin: -11% 0 0 -5%;
		}

		b {
			display: block;
			width: 100%;
			height: 100%;
			border-radius: 999px;
			background-color: @color-inverted-fixed;
			opacity: 0.2;
			animation: combo-wiki-spinner-bar @combo-wiki-spinner-speed linear infinite;
		}

		.combo-wiki-spinner-slots( @combo-wiki-spinner-bars );

		@media ( prefers-reduced-motion: reduce ) {
			animation: none;

			b {
				animation-name: combo-wiki-spinner-fade;
				animation-duration: ( @combo-wiki-spinner-speed * 2.5 );
			}
		}
	}

	// Rotates each bar into place and offsets its animation so the bars light up clockwise
	.combo-wiki-spinner-slots( @i ) when ( @i > 0 ) {
		.combo-wiki-spinner-slots( ( @i - 1 ) );

		@n: ( @i - 1 );
		// Kept as a variable: nested parens inside rotate() break VS Code's Less highlighting
		@angle: ( @n * 360deg / @combo-wiki-spinner-bars );

		i:nth-child( @{i} ) {
			transform: rotate( @angle ) translateY( -164% );

			b {
				animation-delay: ( @combo-wiki-spinner-speed * ( @n / @combo-wiki-spinner-bars - 1 ) );
			}
		}
	}

	@keyframes combo-wiki-spinner-bar {
		0% {
			opacity: 1;
			transform: translateY( 0 );
			animation-timing-function: cubic-bezier( 0.33, 0, 0.2, 1 );
		}

		9% {
			transform: translateY( -26% );
			animation-timing-function: cubic-bezier( 0.45, 0, 0.55, 1 );
		}

		24% {
			opacity: 0.8;
			transform: translateY( 0 );
			animation-timing-function: linear;
		}

		45% {
			opacity: 0.55;
		}

		72% {
			opacity: 0.32;
		}

		100% {
			opacity: 0.2;
			transform: translateY( 0 );
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

	// Gentle bob up and down for the whole spinner
	@keyframes combo-wiki-spinner-float {
		0%,
		100% {
			transform: translateY( 0 );
		}

		50% {
			transform: translateY( -@combo-wiki-spinner-float-height );
		}
	}

	// Same fade without the bounce, for reduced motion
	@keyframes combo-wiki-spinner-fade {
		0% {
			opacity: 1;
		}

		24% {
			opacity: 0.8;
		}

		45% {
			opacity: 0.55;
		}

		72% {
			opacity: 0.32;
		}

		100% {
			opacity: 0.2;
		}
	}
}
</style>
