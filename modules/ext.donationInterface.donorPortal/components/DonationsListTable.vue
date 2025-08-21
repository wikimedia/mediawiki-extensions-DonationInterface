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
			<tbody v-if="donationsList.length > 0">
				<tr
					v-for="donation in currentItems"
					:key="donation.id"
					class="donorportal-donations-table-row">
					<td>{{ donation.receive_date_formatted }}</td>
					<td>{{ translateApiStrings( donation.donation_type_key ) }}</td>
					<td>{{ donation.amount_formatted }} {{ donation.currency }}</td>
					<td>{{ donation.payment_method }}</td>
				</tr>
				<tr
					v-if="numPages > 1">
					<td colspan="4" class="donorportal-donations-table-pagination">
						<a
							v-if="currentPage > 1"
							@click="setPage( currentPage - 1 )"
						>&lt;&lt;</a>
						<a
							v-for="num in numPages"
							:key="num"
							@click="setPage( num )"
							:class="( num === currentPage ) ? 'donorportal-donations-table-current-page' : ''"
						>{{ num }}</a>
						<a
							v-if="currentPage < numPages"
							@click="setPage( currentPage + 1 )"
						>&gt;&gt;</a>
					</td>
				</tr>
			</tbody>
		</table>
	</div>
</template>

<script>
const { ref, defineComponent } = require( 'vue' );
const pageSize = 10;
module.exports = exports = defineComponent( {
	props: {
		donationsList: {
			type: Array,
			required: true
		}
	},
	setup( props ) {
		return {
			currentPage: ref( 1 ),
			numPages: Math.ceil( props.donationsList.length / pageSize ),
			setPage: function ( num ) {
				this.currentPage = num;
			},
			translateApiStrings: function ( string ) {
				if ( !string ) {
					return 'N/A';
				}
				// Frequency keys that can be used here
				// * donorportal-donation-type-monthly
				// * donorportal-donation-type-annual
				// * donorportal-donation-type-one-time
				return this.$i18n( string ).text();
			}
		};
	},
	computed: {
		currentItems: function () {
			return this.donationsList.slice( ( this.currentPage - 1 ) * pageSize, this.currentPage * pageSize );
		}
	}
} );
</script>
