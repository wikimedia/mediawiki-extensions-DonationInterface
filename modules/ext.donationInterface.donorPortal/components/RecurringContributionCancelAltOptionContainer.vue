<template>
	<section
		id="donorportal-cancel-alt-option"
		:class="boxClass"
		@click="handleClick"
	>
		<div class="box__inner">
			<h2 class="heading heading--h2">
				{{ header }}
			</h2>
			<p class="text text--body">
				{{ text }}
			</p>
		</div>
		<slot name="content"></slot>
		<span v-if="isClickable" class="cdx-button cdx-button--fake-button cdx-button--fake-button--enabled cdx-button--weight-quiet cdx-button--size-large">
			{{ buttonText }}
		</span>
	</section>
</template>

<script>
const { defineComponent, computed } = require( 'vue' );
module.exports = exports = defineComponent( {
	props: {
		header: {
			type: String,
			required: true
		},
		text: {
			type: String,
			required: true
		},
		isClickable: {
			type: Boolean
		},
		buttonText: {
			type: String,
			required: true
		},
		extraClasses: {
			type: String,
			default: ''
		},
		action: {
			type: Function,
			default: () => {}
		}
	},
	setup( props ) {
		return {
			boxClass: computed( () => {
				let boxClass = 'box';
				if ( props.isClickable ) {
					boxClass = `${ boxClass } box--clickable`;
				}
				if ( props.extraClasses !== '' ) {
					boxClass = `${ boxClass } ${ props.extraClasses }`;
				}
				return boxClass;
			} ),
			handleClick: () => {
				if ( props.isClickable ) {
					props.action();
				}
			}
		};
	}
} );
</script>
