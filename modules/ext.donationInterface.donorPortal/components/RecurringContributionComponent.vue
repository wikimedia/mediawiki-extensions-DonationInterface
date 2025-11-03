<template>
	<div :class="cardClass">
		<div class="dp-card__section dp-card__summary">
			<span v-if="isActive && !isPaused" class="tag is-recurring">{{ statusWord }}</span>
			<span v-else class="tag">{{ statusWord }}</span>
			<p class="text heading--h2">
				<strong v-if="isActive || !contribution.hasLastContribution">{{ contributionAmount }}</strong>
				<strong v-if="!isActive">{{ $i18n( "donorportal-renew-support" ).text() }}</strong>
			</p>
			<p v-if="isActive" class="text text--body">
				{{ contribution.payment_method }} <!--a
					href="#"
					target="_blank"
					class="link">[ Edit ]</a-->
			</p>
			<p v-if="isActive" class="text text--body">
				{{ recurringNextContributionAmountWithDate }}
			</p>
			<p v-else-if="contribution.hasLastContribution" class="text text--body">
				{{ recurringLastContributionAmountWithDate }}
			</p>
		</div>
		<div class="dp-card__section dp-card__cta">
			<a
				v-if="actionButtonText"
				:href="isActive && contribution.can_modify ? ( '#/update-donations/' + contribution.id ) : '#'"
				class="cdx-button cdx-button--fake-button cdx-button--fake-button--enabled cdx-button--action-progressive cdx-button--weight-primary cdx-button--size-large">
				{{ actionButtonText }}
			</a>
			<p v-if="!isRecurringModifiable" class="text text--body text--align-left">
				{{ $i18n( "donorportal-update-donation-paypal-disable-text" ).text() }}
			</p>
			<p
				v-if="isActive && isRecurringModifiable"
				class="text text--body"
				v-html="recurringAdditionalActionsLink">
			</p>
		</div>
	</div>
</template>

<script>
const { defineComponent } = require( 'vue' );
module.exports = exports = defineComponent( {
	props: {
		contribution: {
			type: Object,
			required: true
		},
		isActive: {
			type: Boolean,
			default: false
		}
	},
	computed: {
		isPaused: function () {
			return this.isActive && this.contribution.is_paused;
		},
		statusWord: function () {
			let keySuffix = 'active';
			if ( !this.isActive ) {
				keySuffix = 'lapsed';
			} else if ( this.isPaused ) {
				keySuffix = 'paused';
			}
			// Messages that can be used here:
			// * donorportal-recurring-status-active
			// * donorportal-recurring-status-lapsed
			// * donorportal-recurring-status-paused
			return mw.msg( 'donorportal-recurring-status-' + keySuffix );
		},
		cardClass: function () {
			const base = 'dp-card__appeal';
			if ( this.isActive && !this.isPaused ) {
				return `${ base } is-recurring`;
			}
			return  `${ base } is-lapsed`;
		},
		contributionAmount: function () {
			// Frequency keys that can be used here
			// * donorportal-recurring-amount-annual
			// * donorportal-recurring-amount-monthly
			return this.$i18n( this.contribution.amount_frequency_key, this.contribution.amount_formatted, this.contribution.currency ).text();
		},
		recurringNextContributionAmountWithDate: function () {
			return this.$i18n( 'donorportal-recurring-next-amount-and-date', this.contribution.amount_formatted,
				this.contribution.currency, this.contribution.next_sched_contribution_date_formatted ).text();
		},
		recurringLastContributionAmountWithDate: function () {
			return this.$i18n( 'donorportal-last-amount-and-date',
				this.contribution.amount_formatted, this.contribution.currency, this.contribution.last_contribution_date_formatted ).text();
		},
		isRecurringModifiable: function () {
			return this.contribution.can_modify;
		},
		actionButtonText: function () {
			if ( this.isActive ) {
				if ( !this.isRecurringModifiable ) {
					return false;
				}
				return this.$i18n( 'donorportal-update-donation-button' ).text();
			}
			// Amount frequency keys that can be used here
			// * donorportal-restart-annual
			// * donorportal-restart-monthly
			return this.$i18n( this.contribution.restart_key ).text();
		},
		recurringAdditionalActionsLink: function () {
			const cancel_link = `<a href="#/cancel-donations/${ this.contribution.id }" class="link"> ${ this.$i18n( 'donorportal-recurring-cancel' ).text() } </a>`;
			if ( !this.isPaused ) {
				const pause_link = `<a href="#/pause-donations/${ this.contribution.id }" class="link"> ${ this.$i18n( 'donorportal-recurring-pause' ).text() } </a>`;
				return this.$i18n( 'donorportal-recurring-pause-or-cancel', pause_link, cancel_link ).text();
			}
			return this.$i18n( 'donorportal-recurring-cancel-text', cancel_link ).text();
		}
	}
} );
</script>
