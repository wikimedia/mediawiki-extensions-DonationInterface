<template>
    <div class="tabcontent" id="donorportal-donations-table">
        <table class="donation-list">
            <thead>
                <tr>
                    <td>{{ $i18n("donorportal-donation-date").text() }}</td>
                    <td>{{ $i18n("donorportal-donation-type").text() }}</td>
                    <td>{{ $i18n("donorportal-donation-amount").text() }}</td>
                    <td>{{ $i18n("donorportal-payment-method").text() }}</td>
                </tr>
            </thead>
            <tbody>
                <tr class="donorportal-donations-table-row" v-for="donation in donations_list" :key="donation.id">
                    <td>{{ donation.receive_date_formatted }}</td>
                    <td>{{ $i18n( donation.donation_type_key).text() }}</td>
                    <td>{{ donation.amount_formatted }} {{ donation.currency }}</td>
                    <td>{{ donation.payment_method }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
<script>
module.exports = exports = {
    props: {
        donations_list: {
            type: Array,
            required: true
        }
    },

}
</script>