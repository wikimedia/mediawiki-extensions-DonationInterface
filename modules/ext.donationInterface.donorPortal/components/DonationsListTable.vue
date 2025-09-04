<template>
	<table id="donorportal-donations-table" class="dp-table">
		<thead>
			<tr>
				<th>{{ $i18n( "donorportal-donation-date" ).text() }}</th>
				<th>{{ $i18n( "donorportal-donation-type" ).text() }}</th>
				<th class="amount">
					{{ $i18n( "donorportal-donation-amount" ).text() }}
				</th>
				<th></th>
			</tr>
		</thead>
		<tbody v-if="donationsList.length > 0">
			<tr
				v-for="donation in currentItems"
				:key="donation.id"
				class="donorportal-donations-table-row">
				<td class="date">
					{{ donation.receive_date_formatted }}
				</td>
				<td class="type" v-html="translateApiStrings( donation.donation_type_key )">
				</td>
				<td class="amount">
					{{ donation.amount_formatted }} {{ donation.currency }}
				</td>
				<td class="method">
					{{ donation.payment_method }}
				</td>
			</tr>
		</tbody>
	</table>
	<div class="dp-table__footer table-pagination">
		<div class="pagination__description">
			<p class="text text--body-small" v-html="paginationText">
			</p>
		</div>
		<div class="pagination__nav">
			<button
				:disabled="currentPage === 1"
				class="cdx-button cdx-button--weight-quiet cdx-button--size-medium pagination-button-first"
				@click="setPage( 1 )">
				<svg
					class="cdx-icon"
					xmlns="http://www.w3.org/2000/svg"
					xmlns:xlink="http://www.w3.org/1999/xlink"
					width="20"
					height="20"
					viewBox="0 0 20 20"
					aria-hidden="true"><g><path d="M3 1h2v18H3zm13.5 1.5L15 1l-9 9 9 9 1.5-1.5L9 10z" /></g></svg>
			</button>
			<button
				:disabled="currentPage === 1"
				class="cdx-button cdx-button--weight-quiet cdx-button--size-medium pagination-button-prev"
				@click="setPage( currentPage - 1 )">
				<svg
					class="cdx-icon"
					xmlns="http://www.w3.org/2000/svg"
					xmlns:xlink="http://www.w3.org/1999/xlink"
					width="20"
					height="20"
					viewBox="0 0 20 20"
					aria-hidden="true"><g><path d="m4 10 9 9 1.4-1.5L7 10l7.4-7.5L13 1z" /></g></svg>
			</button>
			<div class="pagination__nav-pages">
				<div class="text text--body-small">
					Page
				</div>
				<select
					class="cdx-select page-select"
					:value="currentPage"
					@change="$e => setPage( $e.target.value )">
					<option
						v-for="num in numPages"
						:key="`paginationID-${num}`"
						class="page-select-option"
						:value="num">
						{{ num }}
					</option>
				</select>
				<div class="text text--body-small">
					of&nbsp; {{ numPages }}
				</div>
			</div>
			<button
				:disabled="isLastPage"
				class="cdx-button cdx-button--weight-quiet cdx-button--size-medium pagination-button-next"
				@click="setPage( currentPage + 1 )">
				<svg
					class="cdx-icon"
					xmlns="http://www.w3.org/2000/svg"
					xmlns:xlink="http://www.w3.org/1999/xlink"
					width="20"
					height="20"
					viewBox="0 0 20 20"
					aria-hidden="true"><g><path d="M7 1 5.6 2.5 13 10l-7.4 7.5L7 19l9-9z" /></g></svg>
			</button>
			<button
				:disabled="isLastPage"
				class="cdx-button cdx-button--weight-quiet cdx-button--size-medium pagination-button-last"
				@click="setPage( numPages )">
				<svg
					class="cdx-icon"
					xmlns="http://www.w3.org/2000/svg"
					xmlns:xlink="http://www.w3.org/1999/xlink"
					width="20"
					height="20"
					viewBox="0 0 20 20"
					aria-hidden="true"><g><path d="M15 1h2v18h-2zM3.5 2.5 11 10l-7.5 7.5L5 19l9-9-9-9z" /></g></svg>
			</button>
		</div>
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
		const currentPage = ref( 1 );
		return {
			currentPage,
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
				let localeString = this.$i18n( string ).text();
				if ( string !== 'donorportal-donation-type-one-time' ) {
					localeString = this.$i18n( 'donorportal-donation-type-recurring-template', `<span class=\"tag is-recurring\">${ localeString }</span>` ).text();
				}
				return localeString;
			}
		};
	},
	computed: {
		currentItems: function () {
			return this.donationsList.slice( ( this.currentPage - 1 ) * pageSize, this.currentPage * pageSize );
		},
		paginationText: function () {
			const start = ( this.currentPage - 1 ) * pageSize + 1;
			let end = start + ( pageSize - 1 );
			if ( this.isLastPage ) {
				end = start + ( this.donationsList.length - ( ( this.currentPage - 1 ) *  pageSize ) ) - 1;
			}
			return this.$i18n( 'donorportal-donationtable-pagination-text',`<strong>${ start }</strong>`, `<strong>${ end }</strong>`, `<strong>${ this.donationsList.length }</strong>` ).text();
		},
		isLastPage: function () {
			return Number( this.currentPage ) === Number( this.numPages );
		}
	}
} );
</script>
