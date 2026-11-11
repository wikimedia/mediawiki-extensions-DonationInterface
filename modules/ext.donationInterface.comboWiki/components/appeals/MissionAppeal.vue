<template>
	<aside class="combo-wiki__appeal combo-wiki__appeal--callout">
		<img
			class="combo-wiki__appeal-image"
			src="https://upload.wikimedia.org/wikipedia/commons/2/20/Multitude_in_Reef.jpg"
			alt=""
		>
		<p class="text text--base">
			Most readers donate because Wikipedia is useful to them, others because
			they believe in the value of human-made knowledge.
		</p>
		<p class="text text--base">
			We don’t know what the world will look like in 25 years, but we know we
			want Wikipedia to still be a part of it. If you feel the same, please donate
			$2.75 now—or whatever you can spare today—to keep the internet’s last
			big dream alive. Thank you.
		</p>
	</aside>
</template>

<script>
const { defineComponent } = require( 'vue' );

module.exports = exports = defineComponent( {
	name: 'MissionAppeal'
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

.combo-wiki__appeal-image {
	display: block;
	width: 100%;
	max-width: 320px;
	height: auto;
	margin-bottom: @size-6;
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
