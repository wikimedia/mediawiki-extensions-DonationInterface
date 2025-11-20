<template>
	<main id="recurring-update-form" class="container">
		<section class="column--base">
			<h1 class="heading heading--h1">
				{{ forDowngradeForm ? $i18n( 'donorportal-downgrade-recurring-heading' ).text() :
					$i18n( 'donorportal-update-recurring-heading' ).text() }}
			</h1>
			<p class="text text--body">
				{{ $i18n( "donorportal-update-recurring-text" ).text() }}
			</p>
		</section>
		<section class="column--full speedbump">
			<recurring-contribution-summary
				:recurring-contribution="recurringContribution"
				extra-classes="is-recurring height-206"
				:extra-title="$i18n( 'donorportal-update-recurring-current-donation' ).text()"
				last-donation-date
			></recurring-contribution-summary>
			<section v-show="!forDowngradeForm" class="column--gap">
				<svg
					class="cdx-icon"
					xmlns="http://www.w3.org/2000/svg"
					xmlns:xlink="http://www.w3.org/1999/xlink"
					width="20"
					height="20"
					viewBox="0 0 20 20"
					aria-hidden="true"><g>
						<path d="M8.59 3.42 14.17 9H2v2h12.17l-5.58 5.59L10 18l8-8-8-8z" /></g>
				</svg>
			</section>
			<form
				id="form-upgrade"
				method="get"
				class="box is-recurring is-active height-206">
				<div class="box__inner">
					<strong class="text text--body">
						{{ $i18n( "donorportal-update-recurring-new-donation" ).text() }}
					</strong>
					<div class="dp-input-group">
						<h2 class="heading heading--h1">
							{{ recurringContribution.currency_symbol }}
						</h2>
						<div class="cdx-text-input">
							<input
								id="new-recurring-amount"
								v-model="updateAmount"
								class="cdx-text-input__input"
								type="text"
								inputmode="decimal"
								:min="minAmount"
								:max="maxAmount"
								@input="onInput"
							>
						</div>
						<h2 class="heading heading--h1">
							{{ recurringCurrencyFrequency }}
						</h2>
					</div>
					<p class="text text--body">
						{{ recurringContribution.payment_method }}<br>
						{{ confirmActiveDate }}
					</p>
				</div>
			</form>
		</section>
		<section class="column--full column--items-center is-recurring">
			<button
				id="submit-update-action"
				type="button"
				name="submit"
				class="cdx-button cdx-button--weight-primary cdx-button--size-large cdx-button--action-progressive"
				@click="amountChangeAction"
			>
				{{ $i18n( 'donorportal-update-recurring-confirm', recurringContribution.currency_symbol, updateAmount, recurringContribution.currency ).text() }}
			</button>
		</section>
		<section class="column--full">
			<p class="text text--body text--align-center">
				<router-link
					id="buttonBackToAccount"
					to="/"
					class="link">
					{{ $i18n( "donorportal-update-recurring-change-mind" ).text() }}
				</router-link>
			</p>
		</section>
	</main>
</template>

<script>
const { defineComponent, ref } = require( 'vue' );
const { RouterLink } = require( 'vue-router' );
const normalizeInput = require( '../normalizeInput.js' );

const RecurringContributionSummary = require( './RecurringContributionSummary.vue' );

module.exports = exports = defineComponent( {
	name: 'RecurringContributionUpdateForm',
	components: {
		'recurring-contribution-summary': RecurringContributionSummary,
		'router-link': RouterLink
	},
	props: {
		recurringContribution: {
			type: Object,
			required: true
		},
		submitUpdateRecurring: {
			type: Function,
			required: true
		},
		max: {
			type: Number,
			required: true
		},
		currencyRateArray: {
			type: Array,
			required: true
		},
		forDowngradeForm: {
			type: Boolean,
			default: false
		}
	},
	emits: [ 'update:modelValue' ],
	setup( props, { emit } ) {
		const updateAmount = ref( '' );
		const priceRange = normalizeInput.getRecurringPriceRange(
			props.recurringContribution, props.currencyRateArray, props.max
		);
		const minAmount = priceRange[ 0 ];
		let maxAmount = priceRange[ 1 ];
		if ( props.forDowngradeForm ) {
			maxAmount = props.recurringContribution.amount;
		}
		const onInput = ( e ) => {
			const cleaned = normalizeInput.sanitize( e.target.value );
			updateAmount.value = cleaned;
			emit( 'update:modelValue', cleaned );
		};
		const amountChangeAction = ( $event ) => {
			const n = parseFloat( updateAmount.value );
			if ( !n || n < minAmount || n > maxAmount ) {
				alert( 'Please enter a valid amount between ' + minAmount + ' and ' + maxAmount + '.' );
			} else if ( n === props.recurringContribution.amount ) {
				alert( 'Please enter an amount different from your current donation.' );
			} else {
				$event.preventDefault();
				props.submitUpdateRecurring( updateAmount.value );
			}
		};

		return {
			updateAmount,
			onInput,
			amountChangeAction,
			minAmount,
			maxAmount
		};
	},
	computed: {
		confirmActiveDate() {
			let nextDate = 'N/A';
			if ( this.recurringContribution && this.recurringContribution.next_sched_contribution_date_formatted ) {
				nextDate = this.recurringContribution.next_sched_contribution_date_formatted;
			}
			return this.$i18n( 'donorportal-update-recurring-new-donation-effective-date', nextDate ).text();
		},
		recurringCurrencyFrequency() {
			if ( this.recurringContribution.frequency_unit === 'month' ) {
				return this.$i18n( 'donorportal-recurring-currency-monthly', this.recurringContribution.currency ).text();
			} else {
				return this.$i18n( 'donorportal-recurring-currency-annual', this.recurringContribution.currency ).text();
			}
		}
	}
} );
</script>
