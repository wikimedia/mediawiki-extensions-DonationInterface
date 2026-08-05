<template>
    <!--    Payment method form component-->
    <div>
      <h2>{{ $i18n( 'combowiki-payment-method-heading' ).text() }}</h2>

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
          @validate="paymentMethodConfig[paymentMethod].validate"
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
const PayPalComponent = require( "./PayPalComponent.vue" );
const ApplePayComponent = require( "./ApplePayComponent.vue" );

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
    "paypal-form": PayPalComponent,
    "applepay-form": ApplePayComponent
  },
  emits: [ 'donationSuccess', 'donationError', 'onPaymentMethodChange' ],

  setup ( props, ctx ) {
    const submitDonation = ( payload = {}, successCallback = null, errorCallback = null ) => {
      api.submitDonation( props.donation, payload )
          .then( ( result ) => {
            if ( successCallback ) {
              successCallback( result )
            }
            ctx.emit( 'donationSuccess', result )
          } )
          .catch( ( code, failure ) =>  {
            ctx.emit( 'donationError', code, failure )
            if ( errorCallback ) {
              errorCallback( failure )
              this.handleDonateError( code, failure )
            }
          } );
    }

    const validateApplePay = ( event, successCallback, failureCallback ) => {
      const payload = Object.assign( {}, props.donation );
      payload.validationURL = event.validationURL;
      api.validateApplePayPaymentSession( payload )
        .then( ( data ) => {
          if ( data.result && data.result.errors ) {
            failureCallback( data.result.errors );
            mw.log( 'Apple Pay validation failure: ' + JSON.stringify(data.result.errors) );
          } else {
            successCallback( data );
          }
        } ).catch( ( e ) => {
          failureCallback( e )
          console.log("Error", e)
          mw.log( 'Apple Pay validation failure: ' + e );
        } )
    }

    const onError = ( reason ) => {
      ctx.emit( 'donationError', reason );
    }
    return {
        selectPaymentMethod ( method ) {
            this.paymentMethod = method;
            ctx.emit( 'onPaymentMethodChange', method );
        },
        availablePaymentMethods: ['card', 'paypal', 'applepay'],
        paymentMethod: props.donation.paymentMethod || '',
        paymentMethodForm: {},
        paymentMethodConfig: {
            card: {
                label: mw.message( 'combowiki-method-card' ).text(),
                component: 'card-form',
                submit: submitDonation,
                error: onError
            },
            paypal: {
                label: mw.message( 'combowiki-method-paypal' ).text(),
                component: 'paypal-form',
                submit: submitDonation
            },
            applepay: {
                label: mw.message( 'combowiki-method-applepay' ).text(),
                component: 'applepay-form',
                submit: submitDonation,
                validate: validateApplePay
            },
      }
    }
  }
} );
</script>
