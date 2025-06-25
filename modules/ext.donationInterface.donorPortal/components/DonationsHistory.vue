<template>
    <div id="donorportal-donation-history">
        <h2>{{ $i18n("donorportal-your-donation-history").text() }}</h2>
        <button class="print-donation-history">{{ $i18n("donorportal-print-donations").text() }}</button>
        <div class="tab tab-active" ref="annual-funds-tab-header" id="donorportal-tab-annual-fund"
            @click="handleAnnualFundTabClick">{{ $i18n("donorportal-annual-fund").text() }}</div>
        <div class="tab" ref="endowment-tab-header" @click="handleEndowmentTabClick" id="donorportal-tab-endowment">{{
            $i18n("donorportal-endowment").text() }}</div>
        <div class="tabcontent" ref="annual-funds-tab-content" id="donorportal-tabcontent-annual-fund">
            <donations-table :donations_list="annual_fund_donations"></donations-table>
        </div>
        <div class="tabcontent" ref="endowment-tab-content" id="donorportal-tabcontent-endowment" style="display:none">
            <donations-table :donations_list="endowment_donations"
                v-if="endowment_donations.length != 0"></donations-table>
            <table class="donation-list" v-else>
                <tbody>
                    <tr>
                        <td colspan="4">
                            <p>{{ $i18n("donorportal-endowment-short").text() }}</p>
                            <h2>{{ $i18n("donorportal-endowment-what-is").text() }}</h2>
                            <p>{{ $i18n("donorportal-endowment-explanation").text() }}</p>
                            <a href="{{ endowmentLearnMoreUrl }}">{{ $i18n("donorportal-endowment-learn-more").text()
                                }}</a>
                            |
                            <a href="{{ endowmentDonationUrl }}">{{ $i18n("donorportal-endowment-donate-now").text()
                                }}</a>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
<script>
const DonationsTable = require('./DonationsListTable.vue');

module.exports = exports = {
    props: {
        annual_fund_donations: {
            type: Array,
            required: true
        },
        endowment_donations: {
            type: Array,
            required: true
        }
    },
    components: {
        'donations-table': DonationsTable
    },
    data() {
        return {
            endowmentLearnMoreUrl: '',
            endowmentDonationUrl: ''
        }
    },
    methods: {
        handleAnnualFundTabClick: function (event) {
            this.$refs['annual-funds-tab-header'].classList.add('tab-active');
            this.$refs['annual-funds-tab-content'].style.display = 'block';
            this.$refs['endowment-tab-header'].classList.remove('tab-active');
            this.$refs['endowment-tab-content'].style.display = 'none';
        },
        handleEndowmentTabClick: function (event) {
            this.$refs['annual-funds-tab-header'].classList.remove('tab-active');
            this.$refs['annual-funds-tab-content'].style.display = 'none';
            this.$refs['endowment-tab-header'].classList.add('tab-active');
            this.$refs['endowment-tab-content'].style.display = 'block';
        }
    }
}
</script>