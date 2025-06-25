<template>
    <div class="donorportal-inactive-recurring">
        <div>
            <h2>{{ $i18n("donorportal-renew-support").text() }}</h2>
            <p>{{ contribution.payment_method }}</p>
            <p v-if="contribution.hasLastContribution">{{ $i18n("donorportal-last-amount-and-date",
                contribution.amount_formatted, contribution.currency, contribution.last_contribution_date_formatted
                ).text() }}</p>
        </div>
        <div>
            <button class="renew-donation">{{ $i18n(contribution.restart_key).text() }}</button>
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
    computed: {}
}
</script>