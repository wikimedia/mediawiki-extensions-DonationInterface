<template>
	<!-- Donation History -->
	<section id="donorportal-donation-history" class="dp-card">
		<div class="dp-card__head">
			{{ $i18n( "donorportal-your-donation-history" ).text() }}
		</div>
		<!-- Codex Component: Tabs -->
		<div class="cdx-tabs">
			<!-- Header with tab buttons -->
			<form class="cdx-tabs__header">
				<!-- List of tabs. -->
				<div
					class="cdx-tabs__list"
					tabindex="-1"
					role="tablist">
					<!-- Tab list item. -->
					<button
						id="form-tabs-1-label"
						class="cdx-tabs__list__item"
						role="tab"
						aria-selected="true"
						aria-controls="form-tabs-1"
						value="form-tabs-1"
						name="tab"
						@click="handleTabButtonClick">
						Foundation
					</button>
					<button
						id="form-tabs-2-label"
						class="cdx-tabs__list__item"
						role="tab"
						aria-selected="false"
						aria-controls="form-tabs-2"
						value="form-tabs-2"
						name="tab"
						@click="handleTabButtonClick">
						Endowment
					</button>
				</div>
				<div class="dp-table__actions">
					<!--button class="cdx-button cdx-button--weight-quiet cdx-button--size-medium">
						<svg
							class="cdx-icon"
							xmlns="http://www.w3.org/2000/svg"
							xmlns:xlink="http://www.w3.org/1999/xlink"
							width="20"
							height="20"
							viewBox="0 0 20 20"
							aria-hidden="true">
							<g>
								<path
									d="M5 1h10v4H5zM3 6a2 2 0 00-2 2v7h4v4h10v-4h4V8a2 2 0 00-2-2zm11 12H6v-6h8zm2-8a1 1 0 111-1 1 1 0 01-1 1" />
							</g>
						</svg>
						Print donation history
					</button-->
				</div>
			</form>
			<!-- Tabs -->
			<div class="cdx-tabs__content">
				<!-- <section> element for each tab, with any content inside. -->
				<section
					id="form-tabs-1"
					aria-hidden="false"
					aria-labelledby="form-tabs-1-label"
					class="cdx-tab"
					role="tabpanel"
					tabindex="-1">
					<donations-table :donations-list="annualFundDonations"></donations-table>
				</section>
				<section
					id="form-tabs-2"
					aria-hidden="true"
					aria-labelledby="form-tabs-2-label"
					class="cdx-tab"
					role="tabpanel"
					tabindex="-1">
					<donations-table
						v-if="endowmentDonations.length !== 0"
						:donations-list="endowmentDonations"></donations-table>
					<endowment-information v-else></endowment-information>
				</section>
			</div>
			<!-- End of Tabs -->
		</div>
	</section>
	<!-- End of Donation History -->
</template>

<script>
const { defineComponent } = require( 'vue' );
const DonationsTable = require( './DonationsListTable.vue' );
const EndowmentInformationComponent = require( './EndowmentInformationComponent.vue' );

module.exports = exports = defineComponent( {
	components: {
		'donations-table': DonationsTable,
		'endowment-information': EndowmentInformationComponent
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
		handleTabButtonClick: function ( event ) {
			const tabButtons = document.querySelectorAll( '.cdx-tabs__list__item' );
			const tabPanels = document.querySelectorAll( '.cdx-tab' );
			event.preventDefault();

			// Deselect all tabs
			tabButtons.forEach( ( btn ) => {
				btn.setAttribute( 'aria-selected', 'false' );
			} );

			// Hide all panels
			tabPanels.forEach( ( panel ) => {
				panel.setAttribute( 'aria-hidden', 'true' );
			} );

			// Select the clicked tab
			const selectedTab = event.currentTarget;
			selectedTab.setAttribute( 'aria-selected', 'true' );

			// Show the corresponding panel
			const panelId = selectedTab.getAttribute( 'aria-controls' );
			const panel = document.getElementById( panelId );
			panel.setAttribute( 'aria-hidden', 'false' );
		}
	}
} );
</script>
