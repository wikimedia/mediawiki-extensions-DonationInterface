function validateForm(form) {
    var error = true;

    // Get amount selection
    var amount = null;
    for (var i = 0; i < form.amount.length; i++) {
        if (form.amount[i].checked) {
            amount = form.amount[i].value;
        }
    }
    if (form.amountGiven.value != '') {
        var otherAmount = form.amountGiven.value;
        otherAmount = otherAmount.replace(/[,.](\d)$/, '\:$10');
        otherAmount = otherAmount.replace(/[,.](\d)(\d)$/, '\:$1$2');
        otherAmount = otherAmount.replace(/[\$,.]/g, '');
        otherAmount = otherAmount.replace(/:/, '.');
        form.amountGiven.value = otherAmount;
        amount = otherAmount;
    }
    // Check amount is a real number
    error = ( amount == null || isNaN(amount) || amount.value <= 0 );
    // Check amount is at least the minimum
    var currency = form.currency_code.value;
    if (amount < getMinimum(currency) || error) {
        alert('{{{validation-error-minimum|{{int:fr2013-dropdown-smallamount-error}}}}}'.replace('$1', getMinimum(currency) + ' ' + currency));
        error = true;
    }
    return !error;
}

function redirectPayment(paymentMethod, paymentSubMethod) {
    if (typeof paymentSubMethod == 'undefined'){
        paymentSubMethod = '';
    }
    var form = document.paypalcontribution; // we should really change this some day
    var language = $("input[name='language']").val();

    var paymentsURL = 'https://payments.wikimedia.org/index.php/Special:GatewayFormChooser';
    var paypalURL = 'https://wikimediafoundation.org/wiki/Special:ContributionTracking/' + language;

    var params = {
        'uselang' : language,
        'language' : language,
        'currency' : $("input[name='currency_code']").val(),
        'country' : $("input[name='country']").val(),
        'paymentmethod' : paymentMethod
    };
    if( paymentSubMethod != '' ){
        params['submethod'] = paymentSubMethod;
    }

    var frequency = $("input[name='frequency']:checked").val();
    if( frequency !== 'monthly' ){
        frequency = 'onetime';
    } else {
        params['recurring'] = 'true';
        // the following is only for contribution_tracking, do not submit 'r' to payments
        paymentMethod = 'r' + paymentMethod;
    }

    form.action = paymentsURL + '?' + $.param(params);
    form.utm_source.value = '{{{banner}}}.no-LP' + '.' + paymentMethod;
    form.payment_method.value = paymentMethod;
    if( paymentSubMethod != '' ){
        form.payment_method.value = form.payment_method.value + + '.' + paymentSubMethod;
    }

    if (validateForm(document.paypalcontribution)) {
        form.submit();
    }
}

function toggleMonthly( monthly ){
    if( monthly.type === 'checkbox' ){
        monthly = monthly.checked;
    }

    var onetimeonly = $(".no-monthly");

    if( monthly ){
        onetimeonly.css("display", "none");
    } else {
        onetimeonly.css("display", "");
    }
}

$(document).ready( function () {
    if ( wgCanonicalSpecialPageName != "CentralNotice" && wgCanonicalSpecialPageName != "NoticeTemplate" ){
        // append the banner count in utm-key
        var cookieName = 'centralnotice_bannercount_fr12';
        var count = $.cookie(cookieName);
        $('[name="paypalcontribution"]').append(
            $('<input type="hidden" name="utm_key" />').attr('value', count));

        var currency = getCurrency(Geo.country);
        var language = mw.config.get('wgUserLanguage');

        // hide CC or PP buttons anywhere we need to
        var noCC = [];
        if ($.inArray(Geo.country, noCC) != -1) {
            $(".paymentmethod-cc").remove();
        }
        var noPP = ['RU'];
        if ($.inArray(Geo.country, noPP) != -1){
            $(".paymentmethod-pp").remove();
        }

        // can't do monthly credit card in India
        if (Geo.country === 'IN') {
            $(".paymentmethod-cc").addClass("no-monthly");
        }

        // show any extra local payment methods, or remove them if not needed
        var extrapaymentmethods = {
            'amazon' : ['US'],
            'bpay' : ['AU'],
            'ideal' : ['NL'],
            'yandex' : ['RU'],
            'webmoney' : ['RU'],
            'sofort' : ['AT', 'BE', 'CH', 'DE'],
            'dd' : ['AT', 'DE', 'ES', 'NL'],
            'boletos' : ['BR']
        };

        for (var method in extrapaymentmethods) {
            var $methodbutton = $('.paymentmethod-' + method);

            if ($.inArray(Geo.country, extrapaymentmethods[method]) != -1) { // country is in the list
                $methodbutton.show();
            } else {
                $methodbutton.remove();
            }
        }

        // set the form fields
        $("input[name='country']").val(Geo.country);
        $("input[name='currency_code']").val(currency);
        $("input[name='language']").val(mw.config.get('wgUserLanguage'));
        $("input[name='return_to']").val("Thank_You/" + mw.config.get('wgUserLanguage'));

        // do fun things to localize currency in the banner and form
        $("input[name='amount']").each(function(index){
            var id = $(this).attr("id");
            var label = $("label[for='" + id + "']");
            if(id.indexOf("other") == -1){
                var amount = convertAsk($(this).val(), currency, Geo.country);
                $(this).val(amount);
                label.text(currencyLocalize(currency, amount, language));
            } else {
                // simply replace the currency symbol
                label.text(label.text().replace(/\$/,  currencyLocalize(currency, "", language)));
            }
        });

        // handle pressing Enter on "Other" field
        $('input[name="amountGiven"]').keydown(function(e){
            if (e.keyCode == 13) {
                e.preventDefault();
                redirectPayment('cc'); // use credit card by default. Might be nice to have different defaults for some countries, but this will do for now.
                return false;
            }
        });

        // if there are no recurring payment methods available, hide the "monthly" radio button.
        if ( !$('form[name="paypalcontribution"] button[class^="paymentmethod-"]:not(.no-monthly)').length ) {
            $('#frequency_monthly').prop('disabled', 'disabled');
        }
    }
});
