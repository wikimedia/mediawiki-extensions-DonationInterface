<template>
	<header class="header">
		<div class="header__inner">
			<nav class="nav">
				<a
					v-if="isEndowment"
					href="https://wikimediaendowment.org/"
					target="_blank"
				>
					<img
						:src="`${ assets_path }/logos/wikimedia-endowment-logo.png`"
						alt="Wikimedia Endowment"
						class="nav__logo"
					>
				</a>
				<a
					v-else
					href="https://wikimediafoundation.org/"
					target="_blank"
				>
					<img
						:src="`${ assets_path }/logos/wikimedia-foundation-logo-landscape.png`"
						alt="Wikimedia Foundation"
						class="nav__logo"
					>
				</a>
			</nav>
			<section v-if="!isEndowment" class="nav-global__aside">
				<a
					href="https://www.wikipedia.org/"
					target="_blank"
					class="mw-logo"
				>
					<img
						:src="`${ assets_path }/logos/wikipedia-logo.png`"
						alt="Wikimedia Globe"
						class="mw-logo-icon"
					>
					<span class="mw-logo-container skin-invert">
						<img
							:src="`${ assets_path }/logos/wikipedia-wordmark-en.svg`"
							alt="Wikipedia"
							class="mw-logo-wordmark"
						>
						<img
							:src="`${ assets_path }/logos/wikipedia-tagline-en.svg`"
							alt="The Free Encyclopedia"
							class="mw-logo-tagline"
						>
					</span>
				</a>
			</section>
		</div>
	</header>
</template>

<script>
const { defineComponent } = require( 'vue' );
module.exports = exports = defineComponent( {
	name: 'HeaderComponent',
	setup() {
		const { inject } = require( 'vue' );
		const params = inject( 'params' );
		const assets_path = params.assets_path;
		const isEndowment = params.wmfParams.utm_medium === 'endowment';

		return {
			assets_path,
			isEndowment
		};
	}
} );
</script>
