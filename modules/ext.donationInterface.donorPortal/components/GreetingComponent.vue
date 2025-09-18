<template>
	<section class="container__inner dp-dashboard__intro">
		<h1 class="heading heading--subtle heading--h2">
			{{ $i18n( "donorportal-greeting", name ).text() }}
		</h1>
		<p class="text text--body">
			{{ $i18n( "donorportal-greetingtext" ).text() }}
		</p>
	</section>
</template>

<script>
const { defineComponent } = require( 'vue' );
module.exports = exports = defineComponent( {
	props: {
		name: {
			type: String,
			required: false,
			default() {
				return '';
			}
		}
	}
} );
</script>
