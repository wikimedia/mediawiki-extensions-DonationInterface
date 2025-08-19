<template>
	<div id="donorportal-donation-history">
		<h2>{{ $i18n( "donorportal-your-donation-history" ).text() }}</h2>
		<button class="print-donation-history">
			{{ $i18n( "donorportal-print-donations" ).text() }}
		</button>
		<div
			id="donorportal-tab-annual-fund"
			ref="annual-funds-tab-header"
			class="tab tab-active"
			@click="handleAnnualFundTabClick">
			{{ $i18n( "donorportal-annual-fund" ).text() }}
		</div>
		<div
			id="donorportal-tab-endowment"
			ref="endowment-tab-header"
			class="tab"
			@click="handleEndowmentTabClick">
			{{
				$i18n( "donorportal-endowment" ).text() }}
		</div>
		<div
			id="donorportal-tabcontent-annual-fund"
			ref="annual-funds-tab-content"
			class="tabcontent">
			<donations-table :donations-list="annualFundDonations"></donations-table>
		</div>
		<div
			id="donorportal-tabcontent-endowment"
			ref="endowment-tab-content"
			class="tabcontent">
			<donations-table
				v-if="endowmentDonations.length !== 0"
				:donations-list="endowmentDonations"></donations-table>
			<table v-else class="donation-list">
				<tbody>
					<tr>
						<td colspan="4">
							<p>{{ $i18n( "donorportal-endowment-short" ).text() }}</p>
							<h2>{{ $i18n( "donorportal-endowment-what-is" ).text() }}</h2>
							<p>{{ $i18n( "donorportal-endowment-explanation" ).text() }}</p>
							<a href="#/">{{ $i18n( "donorportal-endowment-learn-more" ).text()
							}}</a>
							|
							<a href="#/">{{ $i18n( "donorportal-endowment-donate-now" ).text()
							}}</a>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
	</div>
</template>

<script>
const { defineComponent } = require( 'vue' );
const DonationsTable = require( './DonationsListTable.vue' );

module.exports = exports = defineComponent( {
	components: {
		'donations-table': DonationsTable
	},
	props: {
		annualFundDonations: {
			type: Array,
			required: true,
			default() {
				return [];
			}
		},
		endowmentDonations: {
			type: Array,
			required: true,
			default() {
				return [];
			}
		}
	},
	methods: {
		handleAnnualFundTabClick: function ( event ) {
			this.$refs[ 'annual-funds-tab-header' ].classList.add( 'tab-active' );
			this.$refs[ 'annual-funds-tab-content' ].style.display = 'block';
			this.$refs[ 'endowment-tab-header' ].classList.remove( 'tab-active' );
			this.$refs[ 'endowment-tab-content' ].style.display = 'none';
		},
		handleEndowmentTabClick: function ( event ) {
			this.$refs[ 'annual-funds-tab-header' ].classList.remove( 'tab-active' );
			this.$refs[ 'annual-funds-tab-content' ].style.display = 'none';
			this.$refs[ 'endowment-tab-header' ].classList.add( 'tab-active' );
			this.$refs[ 'endowment-tab-content' ].style.display = 'block';
		}
	},
	mounted() {
			this.$refs[ 'endowment-tab-content' ].style.display = 'none';
	}
} );
</script>
