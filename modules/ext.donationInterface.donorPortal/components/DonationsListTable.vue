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
                <tr v-if="donations_list.length > 0" class="donorportal-donations-table-row" v-for="donation in donations_list" :key="donation.id">
                    <td>{{ donation.receive_date_formatted }}</td>
                    <td>{{ this.translateApiStrings( donation.donation_type_key ) }}</td>
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
    methods: {
        translateApiStrings: function(string){
            if(!string) return 'N/A'
            return this.$i18n(string).text();
        }
    }

}
</script>