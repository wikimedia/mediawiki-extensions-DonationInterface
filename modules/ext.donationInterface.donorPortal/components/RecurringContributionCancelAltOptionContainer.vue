<template>
	<div id="donorportal-cancel-alt-option">
		<div>
			<h3>{{ header }}</h3>
			<p>{{ text }}</p>
		</div>
		<div>
			<slot name="content"></slot>
		</div>
		<div>
			<button
				id="option-action"
				type="submit"
				name="submit"
				class="renew-donation"
				@click="action">
				{{ buttonText }}
			</button>
		</div>
	</div>
</template>

<script>
const { defineComponent } = require( 'vue' );
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
		buttonText: {
			type: String,
			required: true
		},
		action: {
			type: Function,
			required: true
		}
	},
	computed: {

	}
} );
</script>
