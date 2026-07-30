<template>
  <main class="combo-wiki__home">
    <h2>How often would you like to give</h2>

    <frequency-selector v-model="donation.frequency"></frequency-selector>

    <div>
      <!--      Currency selection-->
      <cdx-select
          v-model:selected="donation.currency"
          :menu-items="currencyOptions"
          default-label="Choose a currency">
      </cdx-select>

      <!--      Amount-->
      <cdx-button
          v-for="amount in presetAmounts"
          :key="amount"
          :weight="donation.amount === amount ? 'primary' : 'normal'"
          :class="{ 'combo-wiki__option--selected': Number( donation.amount ) === amount }"
          @click="selectAmount( amount )">
        ${{ amount }}
      </cdx-button>
      <!--      Custom Amount -->
      <cdx-text-input
          v-model="donation.amount"
          input-type="number"
          placeholder="Other amount">
      </cdx-text-input>

      <!--      Pay the fee-->
      <cdx-checkbox v-model="donation.payFee">
        I'll generously cover the transaction fees
      </cdx-checkbox>
    </div>

    <!--    Email opt-in-->
    <div>
      <h2> Can we stay in touch</h2>

      <cdx-radio
          v-model="donation.optIn"
          input-value="yes"
          name="email-optin">
        Yes. Send me emails with the ways I can support Wikipedia.
      </cdx-radio>

      <cdx-radio
          v-model="donation.optIn"
          input-value="no"
          name="email-optin">
        No. Don't send me an occasional email with opportunities to support Wikipedia.
      </cdx-radio>
    </div>
    <payment-method-form
      @donation-success="handleDonateResult"
      @donation-error="handleDonateError"
      @on-payment-method-change="( method ) => {
        this.donation.paymentMethod = method
      }"
      :donation="this.donation"
      :disabled="!giftComplete"
    ></payment-method-form>
    <br/>

    <p> Debug - Frequency: {{ donation.frequency || "nothing yet" }} / {{ donation.amount || "no amount" }} / Fee:
      {{ donation.currency }} {{ feeAmount }} / Email Opt-in:{{ donation.optIn }} / Payment Method:
      {{ donation.paymentMethod }} / Gateway: {{ selectedGateway }}</p>
    <p v-if="donateError" class="combo-wiki__error">{{ donateError }}</p>
  </main>
</template>

<script>
const { defineComponent } = require( "vue" );
const {
  CdxButton,
  CdxTextInput,
  CdxSelect,
  CdxCheckbox,
  CdxRadio
} = require( "@wikimedia/codex" );
const FrequencySelector = require( "../components/FrequencySelector.vue" );
const PaymentMethodForm = require( "../components/PaymentMethodForm.vue" );

const api = require( "../api.js" );

module.exports = exports = defineComponent( {
  name: "Home",

  components: {
    "cdx-button": CdxButton,
    "cdx-text-input": CdxTextInput,
    "cdx-select": CdxSelect,
    "cdx-checkbox": CdxCheckbox,
    "cdx-radio": CdxRadio,
    "frequency-selector": FrequencySelector,
    "payment-method-form": PaymentMethodForm
  },

  data() {
    return {
      presetAmounts: [ 2.75, 5, 10, 20, 30, 50, 100 ],
      currencyOptions: [
        { label: "USD (United States)", value: "USD" },
        { label: "EUR (Euro)", value: "EUR" },
        { label: "GBP (United Kingdom)", value: "GBP" }
      ],
      donation: {
        firstName: null,
        lastName: null,
        email: null,
        frequency: "once",
        amount: null,
        currency: "USD",
        payFee: false,
        country: "US",
        paymentMethod: null,
        optIn: null
      },
      selectedGateway: ( mw.config.get( "comboWiki" ) ).gateway || null,
      donateError: null,
      paymentMethodForm: {}
    };
  },

  methods: {
    selectAmount( value ) {
      this.donation.amount = value;
    },

    handleDonateResult( result ) {
      const response = result.result;
      if ( response.isFailed ) {
        this.donateError = "Payment failed. Please try again";
        return;
      }
      if ( response.errors ) {
        this.donateError = "Payment could not be completed.";
        return;
      }
      console.log( "Donation submitted successfully:", response );
      if ( response.redirect ) {
        window.location.assign( response.redirect );
      } else {
        window.location.assign( mw.config.get( "DonationInterfaceThankYouPage" ) );
      }
    },
    handleDonateError( code, failure ) {
      this.donateError = "Payment failed, Please try again.";
      mw.log.error( "di_donate_gravy failed", code, failure );
    }
  },

  computed: {
    feeAmount() {
      if ( !this.donation.payFee || !this.donation.amount ) {
        return 0;
      }

      // dummy data for PTF now
      return Math.round( this.donation.amount * 0.035 * 100 ) / 100;
    },
    giftComplete() {
      return this.donation.frequency !== null && this.donation.amount !== null;
    },
    availableMethods() {
      return this.paymentMethods.filter(
          ( method ) => method.countries.includes( this.donation.country )
      );
    },
  }
} );

</script>
