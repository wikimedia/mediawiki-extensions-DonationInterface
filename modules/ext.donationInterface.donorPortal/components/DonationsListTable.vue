<template>
	<div id="donorportal-donations-table" class="tabcontent">
		<table class="donation-list">
			<thead>
				<tr>
					<td>{{ $i18n( "donorportal-donation-date" ).text() }}</td>
					<td>{{ $i18n( "donorportal-donation-type" ).text() }}</td>
					<td>{{ $i18n( "donorportal-donation-amount" ).text() }}</td>
					<td>{{ $i18n( "donorportal-payment-method" ).text() }}</td>
				</tr>
			</thead>
			<tbody v-if="donations_list.length > 0">
				<tr
					v-for="donation in donations_list"
					:key="donation.id"
					class="donorportal-donations-table-row">
					<td>{{ donation.receive_date_formatted }}</td>
					<td>{{ translateApiStrings( donation.donation_type_key ) }}</td>
					<td>{{ donation.amount_formatted }} {{ donation.currency }}</td>
					<td>{{ donation.payment_method }}</td>
				</tr>
			</tbody>
		</table>
	</div>
</template>

<script>
const { defineComponent } = require( 'vue' );
module.exports = exports = defineComponent( {
	props: {
		donations_list: {
			type: Array,
			required: true
		}
	},
	methods: {
		translateApiStrings: function ( string ) {
			if ( !string ) {
 return 'N/A';
}
			return this.$i18n( string ).text();
		}
	}

} );
</script>
