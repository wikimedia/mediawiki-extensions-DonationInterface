<template>
	<aside class="combo-wiki__appeal combo-wiki__appeal--callout">
		<h2 class="heading heading--3">
			What does my donation do?
		</h2>
		<ul class="combo-wiki__appeal-list">
			<li>
				<img
					src="https://upload.wikimedia.org/wikipedia/donate/3/30/Icon-wiki.png"
					alt=""
					width="64"
					height="64"
				>
				<p class="text text--base">
					<strong>Sustains</strong> and improves Wikipedia and the other Wikimedia knowledge projects.
				</p>
			</li>
			<li>
				<img
					src="https://upload.wikimedia.org/wikipedia/donate/b/b5/Icon-editors.png"
					alt=""
					width="64"
					height="64"
				>
				<p class="text text--base">
					<strong>Empowers</strong> the 265,000+ volunteers, organizations, and groups who share their knowledge with the world.
				</p>
			</li>
			<li>
				<img
					src="https://upload.wikimedia.org/wikipedia/donate/2/20/Icon-earth.png"
					alt=""
					width="64"
					height="64"
				>
				<p class="text text--base">
					<strong>Advances</strong> our global mission of sharing the sum of all knowledge with everyone, everywhere.
				</p>
			</li>
		</ul>
	</aside>
</template>

<script>
const { defineComponent } = require( 'vue' );

module.exports = exports = defineComponent( {
	name: 'WydgAppeal'
} );
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';
@import (reference) '../../../css/wmf-frds-tokens.less';

@header-height: 61px;

.combo-wiki__appeal--callout {
	display: flex;
	flex-direction: column;
	flex: 1 1 0;
	min-width: 0;
	align-self: center;
	box-sizing: border-box;
	width: 100%;
	max-width: 720px;
	border: 1px solid transparent;
	padding: @size-5;

	> * {
		max-width: 560px;
	}

	> :first-child {
		margin-top: auto;
	}

	> :last-child {
		margin-bottom: auto;
	}

	.text + .text {
		margin-top: @size-6;
	}
}

.combo-wiki__appeal-list {
	display: flex;
	flex-direction: column;
	gap: @size-6;
	margin: @size-6 0 0;
	padding: 0;
	list-style: none;

	li {
		display: flex;
		align-items: center;
		gap: @size-5;
		margin: 0;
	}

	img {
		flex-shrink: 0;
	}

	.text {
		margin: 0;
	}
}

@media screen and ( min-width: 875px ) {
	.combo-wiki__appeal--callout {
		padding: @size-8;
	}
}

@media screen and ( min-width: 1120px ) {
	.combo-wiki__appeal--callout {
		align-self: flex-start;
		min-height: calc( 100vh - @header-height - 2 * @size-4 );
	}
}
</style>
