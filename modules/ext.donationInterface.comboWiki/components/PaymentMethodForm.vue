<template>
    <!--    Payment method form component-->
    <div>
      <h2>Donate with your preferred payment method</h2>

      <cdx-button
          v-for="method in availablePaymentMethods"
          :key="method"
          :class="{ 'combo-wiki__option--selected': donation.paymentMethod === method }"
          :disabled="disabled"
          @click="selectPaymentMethod(method)">
        {{ paymentMethodConfig[method].label }}
      </cdx-button>
      <br />
      <component
          v-if="paymentMethodConfig[paymentMethod]"
          :is="paymentMethodConfig[paymentMethod].component"
          :donation="donation"
          @submit="paymentMethodConfig[paymentMethod].submit"
          @error="paymentMethodConfig[paymentMethod].error"
      ></component>
    </div>
</template>

<script>
const { defineComponent } = require( 'vue' );
const {
  CdxButton
} = require( "@wikimedia/codex" );
const api = require( "../api.js" );
const GravyCardForm = require( "./GravyCardForm.vue" );
const PayPalComponent = require( "./PayPalComponent.vue" )

module.exports = exports = defineComponent( {
  name: 'PaymentMethodSelector',
  props: {
    donation: {
      type: Object,
      required: true
    },
    disabled: {
        type: Boolean,
        default: false
    }
  },
  components: {
    "cdx-button": CdxButton,
    "card-form": GravyCardForm,
    "paypal-form": PayPalComponent
  },
  emits: [ 'donationSuccess', 'donationError', 'onPaymentMethodChange' ],

  setup ( props, ctx ) {
    const submitDonation = ( payload = {} ) => {
      api.submitDonation( props.donation, payload )
          .then( ( result ) => ctx.emit( 'donationSuccess', result ) )
          .catch( ( code, failure ) =>  ctx.emit( 'donationError', code, failure ) );
    }

    const onCardError = ( reason ) => {
      ctx.emit( 'donationError', reason );
    }
    return {
        selectPaymentMethod ( method ) {
            this.paymentMethod = method;
            ctx.emit( 'onPaymentMethodChange', method );
        },
        availablePaymentMethods: ['card', 'paypal'],
        paymentMethod: props.donation.paymentMethod || '',
        paymentMethodForm: {},
        paymentMethodConfig: {
            card: {
                label: "Card",
                component: 'card-form',
                submit: submitDonation,
                error: onCardError
            },
            paypal: {
                label: "PayPal",
                component: 'paypal-form',
                submit: submitDonation
            },
      }
    }
  }
} );
</script>
