<template>
	<section class="dp-card">
		<div class="dp-card__head">
			{{ $i18n( "donorportal-contact-details" ).text() }}
		</div>
		<div class="dp-card__body">
			<div class="dp-card__section">
				<p class="text text--body">
					{{ name }}
				</p>
				<p v-if="address.street_address" class="text text--body">
					{{ address.street_address }}
				</p>
				<p v-if="address.city" class="text text--body">
					{{ address.city }}
				</p>
				<p v-if="addressLine3" class="text text--body">
					{{ addressLine3 }}
				</p>
				<p v-if="address.postal_code" class="text text--body">
					{{ address.postal_code }}
				</p>
			</div>
			<div class="dp-card__section">
				<p class="text text--body">
					{{ $i18n( "donorportal-donorid", id ).text() }}<br>
					{{ email }}<br>
					<a :href="emailPreferencesUrl" class="link">{{ $i18n( "donorportal-update-preferences" ).text() }}</a>
				</p>
			</div>
		</div>
	</section>
</template>

<script>
const { defineComponent } = require( 'vue' );
module.exports = exports = defineComponent( {
	props: {
		name: {
			type: String,
			required: true,
			default() {
				return '';
			}
		},
		id: {
			type: String,
			required: true,
			default() {
				return '';
			}
		},
		email: {
			type: String,
			required: true,
			default() {
				return '';
			}
		},
		address: {
			type: Object,
			required: true,
			default() {
				return {};
			}
		},
		emailPreferencesUrl: {
			type: String,
			required: true
		}
	},
	computed: {
		addressLine3: function () {
			const state_province = this.address.state_province, country = this.address.country;
			if ( state_province && country ) {
				return `${ state_province }, ${ country }`;
			} else if ( state_province ) {
				return state_province;
			} else if ( country ) {
				return country;
			}
			return '';
		}
	}
} );
</script>
