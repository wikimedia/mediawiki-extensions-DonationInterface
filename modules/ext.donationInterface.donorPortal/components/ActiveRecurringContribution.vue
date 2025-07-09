<template>
    <div class="donorportal-recurring-contribution">
        <div>
            <h2>{{ $i18n(contribution.amount_frequency_key, contribution.amount_formatted,
                contribution.currency).text() }}</h2>
            <p>{{ contribution.payment_method }}</p>
            <p>{{ $i18n("donorportal-recurring-next-amount-and-date", contribution.amount_formatted,
                contribution.currency, contribution.next_sched_contribution_date_formatted).text() }}</p>
        </div>
        <div>
            <button class="update-donation">{{ $i18n("donorportal-update-donation-button").text() }}</button>
            <p v-html="recurringLink"></p>
        </div>
    </div>
</template>
<script>
module.exports = exports = {
    props: {
        contribution: {
            type: Object,
            required: true
        }
    },
    computed: {
        recurringLink: function () {
            const pause_link = `<a href="#/pause/${this.contribution.id}"> ${this.$i18n("donorportal-recurring-pause").text()} </a>`;
            const cancel_link = `<a href="#/cancel/${this.contribution.id}"> ${this.$i18n("donorportal-recurring-cancel").text()} </a>`;
            return this.$i18n('donorportal-recurring-pause-or-cancel', pause_link, cancel_link).text()
        }
    },
}
</script>