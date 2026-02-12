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
						:src="`${ assets_path }/logos/wikimedia-foundation-logo-landscape.png`"
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
					<template v-if="isLoggedIn">
						<li class="nav__links-divider"></li>
						<li>
							<a href="#" @click.prevent="handleLogout">{{ $i18n( "donorportal-header-logout" ).text() }}</a>
						</li>
					</template>
				</ul>
			</nav>
			<section class="nav-global__aside">
				<a
					href="https://www.wikipedia.org/"
					target="_blank"
					class="mw-logo">
					<img
						class="mw-logo-icon"
						:src="`${ assets_path }/logos/wikipedia-logo.png`"
						alt="Wikimedia Globe">
					<span class="mw-logo-container skin-invert">
						<img
							class="mw-logo-wordmark"
							alt="Wikipedia"
							:src="`${ assets_path }/logos/wikipedia-wordmark-en.svg`">
						<img
							class="mw-logo-tagline"
							alt="The Free Encyclopedia"
							:src="`${ assets_path }/logos/wikipedia-tagline-en.svg`">
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
		const assets_path = mw.config.get( 'assets_path' );

		return {
			assets_path
		};
	},
	computed: {
		isLoggedIn() {
			const donorData = mw.config.get( 'donorData' );
			return donorData && !donorData.showLogin;
		}
	},
	methods: {
		handleNavToggleClick() {
			this.$refs.nav__links.classList.toggle( 'active' );
			this.$refs.nav__toggle.classList.toggle( 'active' );
		},
		handleLogout() {
			const donorData = mw.config.get( 'donorData' );
			const api = new mw.Api();
			api.post( {
				action: 'requestLogout',
				contact_id: Number( donorData.contact_id ),
				checksum: donorData.checksum
			} ).always( () => {
				window.location.href = mw.util.getUrl( 'Special:DonorPortal' ) + '#/login';
			} );
		}
	}
} );
</script>
