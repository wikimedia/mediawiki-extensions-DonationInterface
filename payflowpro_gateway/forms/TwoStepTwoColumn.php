<?php

class PayflowProGateway_Form_TwoStepTwoColumn extends PayflowProGateway_Form {

        public function __construct( &$form_data, &$form_errors ) {
                global $wgOut, $wgScriptPath;

                parent::__construct( $form_data, $form_errors );

                // we only want to load this JS if the form is being rendered
                $this->loadValidateJs(); // validation JS
				$this->loadApiJs(); // API/Ajax JS

				$first = wfMsg( 'payflowpro_gateway-first' );
                $last = wfMsg( 'payflowpro_gateway-last' );
                $js = <<<EOT
<script type="text/javascript">
function loadPlaceholders() {
        var fname = document.getElementById('fname');
        var lname = document.getElementById('lname');
        var amountOther = document.getElementById('amountOther');
        if (fname.value == '') {
                fname.style.color = '#999999';
                fname.value = '$first';
        }
        if (lname.value == '') {
                lname.style.color = '#999999';
                lname.value = '$last';
        }
}
addEvent( window, 'load', loadPlaceholders );
</script>
EOT;
                $wgOut->addHeadItem( 'placeholders', $js );
        }

        /**
         * Required method for constructing the entire form
         * 
         * This can of course be overloaded by a child class.
         * @return string The entire form HTML
         */
        public function getForm() {
                $form = $this->generateFormStart();
                $form .= $this->getCaptchaHTML();
                $form .= $this->generateFormSubmit();
                $form .= $this->generateFormEnd();
                return $form;
        }

        public function generateFormStart() {
                global $wgPayflowGatewayHeader, $wgPayflwGatewayTest, $wgOut;
                $form = $this->generateBannerHeader();

                $form .= Xml::openElement( 'div', array( 'id' => 'mw-creditcard' ) ); 

                // provide a place at the top of the form for displaying general messages
                if ( $this->form_errors['general'] ) {
                        $form .= Xml::openElement( 'div', array( 'id' => 'mw-payflow-general-error' ));
                        if ( is_array( $this->form_errors['general'] )) {
                                foreach ( $this->form_errors['general'] as $this->form_errors_msg ) {
                                        $form .= Xml::tags( 'p', array( 'class' => 'creditcard-error-msg' ), $this->form_errors_msg );
                                }
                        } else {
                                $form .= Xml::tags( 'p', array( 'class' => 'creditcard-error-msg' ), $this->form_errors_msg );
                        }
                        $form .= Xml::closeElement( 'div' );
                }

                // add noscript tags for javascript disabled browsers
				$form .= $this->getNoScript();
                
                // open form
                $form .= Xml::openElement( 'div', array( 'id' => 'mw-creditcard-form' ) );

                // Xml::element seems to convert html to htmlentities
                $form .= "<p class='creditcard-error-msg'>" . $this->form_errors['retryMsg'] . "</p>";
                $form .= Xml::openElement( 'form', array( 'name' => 'payment', 'method' => 'post', 'action' => $this->getNoCacheAction(), 'onsubmit' => 'return validate_form(this)', 'autocomplete' => 'off' ) );

                $form .= Xml::openElement( 'div', array( 'id' => 'left-column', 'class' => 'payflow-cc-form-section'));
                $form .= $this->generatePersonalContainer();
                $form .= Xml::closeElement( 'div' ); // close div#left-column

                $form .= Xml::openElement( 'div', array( 'id' => 'right-column', 'class' => 'payflow-cc-form-section' ));
                $form .= $this->generatePaymentContainer();

                return $form;
        }

        public function generateFormSubmit() {
                // submit button
                $form = Xml::openElement( 'div', array( 'id' => 'payflowpro_gateway-form-submit'));
                $form .= Xml::openElement( 'div', array( 'id' => 'mw-donate-submit-button' ));  
                //$form .= Xml::submitButton( wfMsg( 'payflowpro_gateway-submit-button' ));
                $form .= Xml::element( 'input', array( 'class' => 'button-plain', 'value' => wfMsg( 'payflowpro_gateway-cc-button'), 'onclick' => 'submit_form( this )', 'type' => 'submit'));
                $form .= Xml::closeElement( 'div' ); // close div#mw-donate-submit-button
                $form .= Xml::openElement( 'div', array( 'class' => 'mw-donate-submessage', 'id' => 'payflowpro_gateway-donate-submessage' ) ) .
                        wfMsg( 'payflowpro_gateway-donate-click' ); 
                $form .= Xml::closeElement( 'div' ); // close div#payflowpro_gateway-donate-submessage
                $form .= Xml::closeElement( 'div' ); // close div#payflowpro_gateway-form-submit
                return $form;
        }

        public function generateFormEnd() {
                $form = '';
                // add hidden fields
                $hidden_fields = $this->getHiddenFields();
                foreach ( $hidden_fields as $field => $value ) {
                        $form .= Xml::hidden( $field, $value );
                }
                $form .= Xml::closeElement( 'div' ); // close div#right-column
                $form .= Xml::closeElement( 'form' );
                $form .= Xml::closeElement( 'div' ); // close div#mw-creditcard-form
                $form .= $this->generateDonationFooter();
                $form .= Xml::closeElement( 'div' ); // div#close mw-creditcard
                return $form;
        }

        protected function generatePersonalContainer() {
                $form = '';
                $form .= Xml::openElement( 'div', array( 'id' => 'payflowpro_gateway-personal-info' ));                 ;
                $form .= Xml::tags( 'h3', array( 'class' => 'payflow-cc-form-header','id' => 'payflow-cc-form-header-personal' ), wfMsg( 'payflowpro_gateway-cc-form-header-personal' ));
                $form .= Xml::openElement( 'table', array( 'id' => 'payflow-table-donor' ) );

                $form .= $this->generatePersonalFields();

                $form .= Xml::closeElement( 'table' ); // close table#payflow-table-donor
                $form .= Xml::closeElement( 'div' ); // close div#payflowpro_gateway-personal-info

                return $form;
        }

        protected function generatePersonalFields() {
                // first name
                $form = $this->getNameField();

                // country
                $form .= $this->getCountryField();

                // street
                $form .= $this->getStreetField();


                // city
                $form .= $this->getCityField(); 

                // state
                $form .= $this->getStateField();

                // zip
                $form .= $this->getZipField();

                // email
                $form .= $this->getEmailField();

                return $form;
        }

        protected function generatePaymentContainer() {
                $form = '';
                // credit card info
                $form .= Xml::openElement( 'div', array( 'id' => 'payflowpro_gateway-payment-info' ));
                $form .= Xml::tags( 'h3', array( 'class' => 'payflow-cc-form-header', 'id' => 'payflow-cc-form-header-payment' ), wfMsg( 'payflowpro_gateway-cc-form-header-payment' ));
                $form .= Xml::openElement( 'table', array( 'id' => 'payflow-table-cc' ) );

                $form .= $this->generatePaymentFields();

                $form .= Xml::closeElement( 'table' ); // close table#payflow-table-cc
                $form .= Xml::closeElement( 'div' ); // close div#payflowpro_gateway-payment-info

                return $form;
        }

        protected function generatePaymentFields() {
                global $wgScriptPath, $wgPayflowGatewayTest;

                $form = '';

                // amount
                $form = '<tr>';
                $form .= '<td colspan="2"><span class="creditcard-error-msg">' . $this->form_errors['invalidamount'] . '</span></td>';
                $form .= '</tr>';
                $form .= '<tr>';
                $form .= '<td class="label">' . Xml::label(wfMsg( 'payflowpro_gateway-donor-amount' ), 'amount') . '</td>';
                $form .= '<td>' . Xml::input( 'amount', '7', $this->form_data['amount'], array( 'type' => 'text', 'maxlength' => '10', 'id' => 'amount' ) ) . 
                        ' ' . $this->generateCurrencyDropdown() . '</td>';
                $form .= '</tr>';

                // card logos
                $form .= '<tr>';
                $form .= '<td />';
                $form .= '<td>' . Xml::element( 'img', array( 'src' => $wgScriptPath . "/extensions/DonationInterface/payflowpro_gateway/includes/credit_card_logos.gif" )) . '</td>';
                $form .= '</tr>';

                // credit card type
                $form .= $this->getCreditCardTypeField();

                // card number
                $form .= $this->getCardnumberField();

                // expiry
                $form .= $this->getExpiryField();

                // cvv
                $form .= $this->getCvvField();

                return $form;
        }
}
