<template>
	<header class="header">
		<div class="header__inner">
			<nav class="nav">
				<button
					id="nav__toggle"
					ref="nav__toggle"
					class="nav__toggle"
					@click="handleNavToggleClick">
					<svg
						class="cdx-icon"
						xmlns="http://www.w3.org/2000/svg"
						xmlns:xlink="http://www.w3.org/1999/xlink"
						width="20"
						height="20"
						viewBox="0 0 20 20"
						aria-hidden="true"><g><path d="M1 3v2h18V3zm0 8h18V9H1zm0 6h18v-2H1z" /></g></svg>
				</button>
				<a href="https://wikimediafoundation.org/" target="_blank">
					<img
						src="https://upload.wikimedia.org/wikipedia/donate/e/e2/Logo-foundation-landscape-wkfrds.png"
						alt="Wikimedia Foundation"
						class="nav__logo">
				</a>
				<ul ref="nav__links" class="nav__links">
					<li>
						<a href="https://wikimediafoundation.org/about/" target="_blank">{{ $i18n( "donorportal-header-about" ).text() }}</a>
					</li>
					<li>
						<a href="https://wikimediafoundation.org/our-work/" target="_blank">{{ $i18n( "donorportal-header-our-work" ).text() }}</a>
					</li>
					<li>
						<a href="https://wikimediafoundation.org/participate/" target="_blank">{{ $i18n( "donorportal-header-participate" ).text() }}</a>
					</li>
					<li>
						<a href="https://wikimediafoundation.org/news/" target="_blank">{{ $i18n( "donorportal-header-news" ).text() }}</a>
					</li>
				</ul>
			</nav>
			<section class="nav-global__aside">
				<a
					href="https://www.wikipedia.org/"
					target="_blank"
					class="mw-logo">
					<img
						class="mw-logo-icon"
						src="https://www.wikipedia.org/static/images/icons/wikipedia.png"
						alt="Wikimedia Globe">
					<span class="mw-logo-container skin-invert">
						<img
							class="mw-logo-wordmark"
							alt="Wikipedia"
							src="https://www.wikipedia.org/static/images/mobile/copyright/wikipedia-wordmark-en.svg">
						<img
							class="mw-logo-tagline"
							alt="The Free Encyclopedia"
							src="https://www.wikipedia.org/static/images/mobile/copyright/wikipedia-tagline-en.svg">
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

	methods: {
		handleNavToggleClick() {
			this.$refs.nav__links.classList.toggle( 'active' );
			this.$refs.nav__toggle.classList.toggle( 'active' );
		}
	}
} );
</script>
