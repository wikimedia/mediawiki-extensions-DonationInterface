<template>
    <div class="donorportal-recent-donation">
        <div>
            <h2>{{ contribution.last_amount_formatted }} {{ contribution.last_currency }}</h2>
            <p>{{ contribution.last_payment_method }}</p>
            <p>{{ $i18n("donorportal-last-amount-and-date", contribution.last_amount_formatted,
                contribution.last_currency, contribution.last_receive_date_formatted).text() }}</p>
        </div>
        <div>
            <button class="new-donation">{{ $i18n("donorportal-new-donation").text() }}</button>
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