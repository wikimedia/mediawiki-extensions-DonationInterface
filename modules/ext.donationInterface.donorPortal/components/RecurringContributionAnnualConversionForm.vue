<template>
	<main id="form-convert-yearly" class="container">
		<section class="column--base">
			<h1 class="heading heading--h1">
				{{ $i18n( "donorportal-update-recurring-annual-convert-head" ).text() }}
			</h1>
			<p class="text text--body">
				{{ $i18n( "donorportal-update-recurring-annual-convert-description" ).text() }}
			</p>
		</section>
		<form
			class="form"
			@submit.prevent>
			<section class="column--base">
				<p class="text text--body">
					{{ $i18n( "donorportal-update-recurring-annual-convert-select-below" ).text() }}
				</p>
			</section>
			<section class="column--full speedbump">
				<label for="conversion-yearly-upgrade" class="box box--clickable is-recurring">
					<div class="box__inner">
						<div class="cdx-radio">
							<div class="cdx-radio__wrapper">
								<input
									id="conversion-yearly-upgrade"
									class="cdx-radio__input"
									type="radio"
									name="conversion-yearly"
									:value="yearlyAmount"
									@click="selectYearlyAmountOption"
								>
								<span class="cdx-radio__icon"></span>
							</div>
						</div>
						<span class="text text--body" v-html="convertMonthlyToYearly">
						</span>
					</div>
				</label>

				<label for="conversion-yearly-current" class="box box--clickable is-recurring">
					<div class="box__inner">
						<div class="cdx-radio">
							<div class="cdx-radio__wrapper">
								<input
									id="conversion-yearly-current"
									class="cdx-radio__input"
									type="radio"
									name="conversion-yearly"
									:value="recurringContribution.amount"
									@click="selectYearlyAmountOption"
								>
								<span class="cdx-radio__icon"></span>
							</div>
						</div>
						<span class="text text--body" v-html="convertMonthlySameAmountToYearly">
						</span>
					</div>
				</label>

				<label for="conversion-yearly-other" class="box box--clickable is-recurring">
					<div class="box__inner">
						<div class="cdx-radio">
							<div class="cdx-radio__wrapper">
								<input
									id="conversion-yearly-other"
									class="cdx-radio__input"
									type="radio"
									name="conversion-yearly"
									:value="updateAmount"
								>
								<span class="cdx-radio__icon"></span>
							</div>
						</div>
						<span class="text text--body">
							{{ $i18n( "donorportal-update-recurring-annual-convert-yearly-other-amount" ).text() }}
						</span>
						<div class="dp-input-group">
							<div class="cdx-text-input">
								<input
									id="new-annual-recurring-amount"
									v-model="updateAmount"
									class="cdx-text-input__input"
									type="text"
									:min="minAmount"
									:max="maxAmount"
									@input="onInput"
									@keyup.enter="annualConversionAction"
								>
							</div>
						</div>
					</div>
				</label>
			</section>
			<section class="column--full">
				<ul class="checklist">
					<li class="text text--body">
						{{ recurringContribution.payment_method }}
					</li>
					<li class="text text--body" v-html="yearlyConversionDate">
					</li>
				</ul>
			</section>
		</form>

		<section class="column--full column--items-center is-recurring">
			<button
				id="submit-annual-conversion"
				type="button"
				name="submit"
				class="cdx-button cdx-button--weight-primary cdx-button--size-large cdx-button--action-progressive"
				@click="annualConversionAction"
			>
				{{ confirmContributionUpdate }}
			</button>
		</section>

		<section class="column--full">
			<p class="text text--body text--align-center">
				{{ $i18n( "donorportal-cancel-recurring-quit-header" ).text() }} <router-link
					id="buttonBackToAccount"
					to="/"
					class="link">
					{{ $i18n( "donorportal-return-to-account-button" ).text() }}
				</router-link>
			</p>
		</section>
	</main>
</template>

<script>
const { defineComponent, ref } = require( 'vue' );
const { RouterLink } = require( 'vue-router' );
const normalizeInput = require( '../normalizeInput.js' );

module.exports = exports = defineComponent( {
	name: 'RecurringAnnualConversionForm',
	components: {
		'router-link': RouterLink
	},
	props: {
		recurringContribution: {
			type: Object,
			required: true,
			defaultValue() {
				return {};
			}
		},
		submitAnnualConversion: {
			type: Function,
			required: true
		},
		currencyRateArray: {
			type: Array,
			required: true
		},
		max: {
			type: Number,
			required: true
		}
	},
	emits: [ 'update:modelValue' ],
	setup( props, { emit } ) {
		const updateAmount = ref( '' );
		const priceRange = normalizeInput.getRecurringPriceRange(
			props.recurringContribution, props.currencyRateArray, props.max
		);
		const minAmount = priceRange[ 0 ];
		const maxAmount = priceRange[ 1 ];
		const onInput = ( e ) => {
			const cleaned = normalizeInput.sanitize( e.target.value );
			updateAmount.value = cleaned;
			// Auto-select the "other amount" radio button
			const otherRadio = document.getElementById( 'conversion-yearly-other' );
			if ( otherRadio ) {
				otherRadio.checked = true;
			}
			emit( 'update:modelValue', cleaned );
		};
		const selectYearlyAmountOption = ( e ) => {
			updateAmount.value = e.target.value;
		};
		const annualConversionAction = ( e ) => {
			const n = parseFloat( updateAmount.value );
			if ( !n || n < minAmount || n > maxAmount ) {
				alert( 'Please enter a valid amount between ' + minAmount + ' and ' + maxAmount + '.' );
			} else {
				e.preventDefault();
				props.submitAnnualConversion( updateAmount.value );
			}
		};
		return {
			updateAmount,
			onInput,
			annualConversionAction,
			selectYearlyAmountOption,
			minAmount,
			maxAmount
		};
	},
	computed: {
		confirmContributionUpdate() {
			const updatedAmount = this.recurringContribution.currency_symbol + this.updateAmount + ' ' + this.recurringContribution.currency;
			return this.$i18n( 'donorportal-update-recurring-annual-confirm', updatedAmount ).text();
		},
		yearlyAmount() {
			return ( this.recurringContribution.amount * 12 ).toFixed( 2 );
		},
		convertMonthlyToYearly() {
			const annualAmount = this.yearlyAmount;
			const formatedAmount = this.recurringContribution.currency_symbol + annualAmount + ' ' + this.recurringContribution.currency;
			return this.$i18n( 'donorportal-update-recurring-monthly-to-annual-amount',
				`<b>${ formatedAmount }</b>` ).text();
		},
		convertMonthlySameAmountToYearly() {
			const amount = this.recurringContribution.amount_formatted + ' ' + this.recurringContribution.currency;
			return this.$i18n( 'donorportal-update-recurring-annual-convert-yearly', `<b>${ amount }</b>` ).text();
		},
		yearlyConversionDate() {
			return this.$i18n( 'donorportal-update-recurring-annual-convert-yearly-confirm-next-schedule', `<b>${ this.recurringContribution.next_contribution_date_yearly_formatted }</b>` ).text();
		}
	}
} );
</script>
