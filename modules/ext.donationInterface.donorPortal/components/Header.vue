<template>
	<header class="header-default minimal--short featured-photo--photo-centered">
		<div class="site-header">
			<div class="site-header__inner">
				<a href="https://wikimediafoundation.org" class="nav-logo">
					<img class="nav-logo__image" :src="`${template_path}/logo-horizontal.svg`" >
				</a>
			</div>
		</div>
		<div class="primary-nav__drawer">
			<ul id="menu-header-menu" class="primary-nav__items">
				<li id="menu-item-123" class="menu-item menu-item-type-post_type menu-item-object-page menu-item-123"><a
						href="https://wikimediafoundation.org/our-work/"><span>{{ $i18n( "emailpreferences-header-link-our-work" ).text() }}</span></a></li>
				<li id="menu-item-115" class="menu-item menu-item-type-post_type menu-item-object-page menu-item-115"><a
						href="https://wikimediafoundation.org/support/"><span>{{ $i18n( "emailpreferences-header-link-support-wikipedia" ).text() }}</span></a></li>
				<li id="menu-item-119" class="menu-item menu-item-type-post_type menu-item-object-page menu-item-119"><a
						href="https://wikimediafoundation.org/research/"><span>{{ $i18n( "emailpreferences-header-link-research" ).text() }}</span></a></li>
				<li id="menu-item-118" class="menu-item menu-item-type-post_type menu-item-object-page menu-item-118"><a
						href="https://wikimediafoundation.org/advocacy/"><span>{{ $i18n( "emailpreferences-header-link-advocacy" ).text() }}</span></a></li>
				<li id="menu-item-117" class="menu-item menu-item-type-post_type menu-item-object-page menu-item-117"><a
						href="https://wikimediafoundation.org/technology/"><span>{{ $i18n( "emailpreferences-header-link-technology" ).text() }}</span></a></li>
				<li id="menu-item-28" class="menu-item menu-item-type-post_type menu-item-object-page menu-item-28"><a
						href="https://wikimediafoundation.org/news/"><span>{{ $i18n( "emailpreferences-header-link-news" ).text()
							}}</span></a>
				</li>
				<li id="menu-item-116" class="menu-item menu-item-type-post_type menu-item-object-page menu-item-116"><a
						href="https://wikimediafoundation.org/participate/"><span>{{ $i18n( "emailpreferences-header-link-participate" ).text() }}</span></a></li>
				<li id="menu-item-120" class="menu-item menu-item-type-post_type menu-item-object-page menu-item-103"><a
						href="https://wikimediafoundation.org/about/"><span>{{ $i18n( "emailpreferences-header-link-about" ).text() }}</span></a></li>
			</ul>
		</div>
	</header>
</template>

<script>
module.exports = exports = {
	name: 'HeaderComponent',

	data() {
		return {
			template_path: mw.config.get('template_path')
		}
	},
	components: {
	},

	computed: {
	}
};
</script>
