<template>
  <div class="combo-wiki__card" :class="{ 'combo-wiki__card--loading': !fieldsReady}">
    <div>
      <h2>Your details</h2>

      <cdx-text-input
          v-model="donation.firstName"
          placeholder="First name">
      </cdx-text-input>

      <cdx-text-input
          v-model="donation.lastName"
          placeholder="Last name">
      </cdx-text-input>

      <cdx-text-input
          v-model="donation.email"
          placeholder="Email address">
      </cdx-text-input>
    </div>

    <label for="combo-cc-number">Card number</label>
    <input id="combo-cc-number" />

    <div class="combo-wiki__card-row">
      <div>
        <label for="combo-cc-expiry">Expiry</label>
        <input id="combo-cc-expiry" />
      </div>
      <div>
        <label for="combo-cc-cvv">Security code</label>
        <input id="combo-cc-cvv" />
      </div>
    </div>

    <cdx-button
      action="progressive"
      weight="primary"
      :disabled="!canSubmit"
      @click="submit">
      Donate
    </cdx-button>


  </div>
</template>

<script>
/* global SecureFields */
const { defineComponent } = require( 'vue' );
const { CdxButton, CdxTextInput } = require( '@wikimedia/codex' );
const api = require( '../api.js' );

module.exports = exports = defineComponent({
  name: 'GravyCardForm',

  components: {
    'cdx-button': CdxButton,
    'cdx-text-input': CdxTextInput
  },

  props: {
    donation: {
      type: Object,
      required: true
    }
  },

  emits: [ 'tokenized', 'error' ],

  data() {
    return {
      secureFields: null,
      fieldsReady: false,
      formValid: false
    };
  },

  mounted() {
    const config = mw.config.get( 'gravyConfiguration' );
    Promise.all( [
      this.loadScript( config.secureFieldsJsScript ),
      api.createCheckoutSession( this.donation )
    ] ).then( ( [ , sessionId ] ) => {
      this.setupSecureFields( config, sessionId );
    } ).catch( () => {
      this.$emit( 'error', 'card-session-setup-failed' );
    } );
  },

  beforeUnmount() {
    // Drop the SDK instance so it doesn't stay in browser memory when not needed
    this.secureFields = null;
  },

  computed: {
    canSubmit() {
      return this.formValid && this.detailsComplete;
    },
    detailsComplete() {
      const hasFirstName = Boolean( this.donation.firstName?.trim() );
      const hasLastName = Boolean( this.donation.lastName?.trim() );
      const hasEmail = this.isValidEmail( this.donation.email );

      return hasFirstName && hasLastName && hasEmail;
    }
  },

  methods: {
    loadScript( src ) {
      return new Promise( ( resolve , reject )  => {
        const node = document.createElement( 'script' );
        node.src = src;
        node.onload = resolve;
        node.onerror = reject;
        document.body.append( node );
      });
    },
    isValidEmail( email ) {
      return typeof email === 'string' && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test( email.trim() );
    },
    setupSecureFields( config, sessionId ) {
      this.secureFields = new SecureFields( {
        gr4vyId: config.gravyID,
        environment: config.environment,
        sessionId: sessionId
      });

      this.secureFields.addCardNumberField( '#combo-cc-number' );
      this.secureFields.addSecurityCodeField( '#combo-cc-cvv' );
      this.secureFields.addExpiryDateField( '#combo-cc-expiry' );
      this.fieldsReady = true;

      this.secureFields.addEventListener( SecureFields.Events.FORM_CHANGE, ( data ) => {
        if ( data ) {
          this.formValid = data.complete;
        }
      } );

      this.secureFields.addEventListener( SecureFields.Events.CARD_VAULT_SUCCESS, ( data ) => {
        this.$emit( 'tokenized', {
          gateway_session_id: sessionId
        } );
      } );

      this.secureFields.addEventListener( SecureFields.Events.CARD_VAULT_FAILURE, ( data ) => {
        this.$emit( 'error', 'card-vault-failure');
      } );
    },
    submit() {
      this.secureFields.submit();
    }

  }





});

</script>
