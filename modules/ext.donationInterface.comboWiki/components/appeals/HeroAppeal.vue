<template>
	<div class="combo-wiki__hero-media">
		<img
			class="combo-wiki__hero-image"
			src="https://upload.wikimedia.org/wikipedia/commons/4/49/Lago_Federa.jpg"
			alt=""
		>
	</div>
	<div class="combo-wiki__hero-spacer"></div>
	<p class="combo-wiki__hero-credit">
		<a href="https://commons.wikimedia.org/wiki/File:Lago_Federa.jpg">Federa lake in the natural regional park of Dolomiti d’Ampezzo</a> by Scosse
	</p>
</template>

<script>
const { defineComponent } = require( 'vue' );

module.exports = exports = defineComponent( {
	name: 'HeroAppeal'
} );
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';
@import (reference) '../../../css/wmf-frds-tokens.less';

.combo-wiki__wrapper {
	position: relative;
}

.combo-wiki__hero-media {
	position: absolute;
	inset: 0;
	max-height: 250px;
	overflow: hidden;
	z-index: -1;
}

.combo-wiki__hero-image {
	width: 100%;
	height: 100%;
	object-fit: cover;
}

.combo-wiki__hero-spacer {
	order: -1;
	height: calc( 150px - @size-2 );
}

.combo-wiki__hero-credit {
	align-self: center;
	box-sizing: border-box;
	width: 100%;
	max-width: 720px;
	margin: 0 !important;
	border: 1px solid transparent;
	border-width: 0 1px;
	padding: @size-5;
	color: @color-black;
	font-size: 0.75rem;

	a,
	a:visited {
		color: inherit;
		text-decoration: underline;
	}
}

@media screen and ( min-width: 875px ) {
	.combo-wiki__hero-credit {
		padding-right: @size-8;
		padding-left: @size-8;
	}
}

@media screen and ( min-width: 1120px ) {
	.combo-wiki__hero-media {
		max-height: none;
	}

	.combo-wiki__hero-spacer {
		order: 1;
		flex: 1 1 0;
		height: auto;
	}

	.combo-wiki__hero-credit {
		position: absolute;
		z-index: 1;
		right: @size-4;
		bottom: @size-4;
		width: auto;
		max-width: none;
		border: 0;
		padding: @size-1 @size-2;
		background-color: @background-color-backdrop-dark;
		color: @color-white;
	}
}
</style>
