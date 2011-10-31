<?php
/**
 * Internationalization file for the Donation Interface - GlobalCollect - extension
 *
 * @file
 * @ingroup Extensions
 */

$messages = array();

/** English */
$messages['en'] = array(
	'globalcollectgateway' => 'Make your donation now',
	'globalcollect_gateway-desc' => 'GlobalCollect payment processing',
	'globalcollect_gateway-response-9130' => 'Invalid country.',
	'globalcollect_gateway-response-9140' => 'Invalid currency.',
	'globalcollect_gateway-response-9150' => 'Invalid language.',
	'globalcollect_gateway-response-400530' => 'Invalid payment method.',
	'globalcollect_gateway-response-430306' => 'Your credit card has expired. Please try a different card or one of our other payment methods.',
	'globalcollect_gateway-response-430330' => 'Invalid card number.',
	'globalcollect_gateway-response-430421' => 'Your credit card could not be validated. Please verify that all information matches your credit card profile, or try a different card.', // suspected fraud
	'globalcollect_gateway-response-430360' => 'The transaction could not be authorized. Please try a different card or one of our other payment methods.', // low funds
	'globalcollect_gateway-response-430285' => 'The transaction could not be authorized. Please try a different card or one of our other payment methods.', // do not honor
	'globalcollect_gateway-response-21000150' => 'Invalid bank account number.',
	'globalcollect_gateway-response-21000155' => 'Invalid bank code.',
	'globalcollect_gateway-response-21000160' => 'Invalid giro account number.',
	'globalcollect_gateway-response-default' => 'There was an error processing your transaction.
Please try again later.',
);

/** Message documentation (Message documentation)
 * @author Kaldari
 */
$messages['qqq'] = array(
	'globalcollectgateway' => '{{Identical|Support Wikimedia}}',
	'globalcollect_gateway-desc' => '{{desc}}',
	'globalcollect_gateway-response-9130' => 'Error message for invalid country.',
	'globalcollect_gateway-response-9140' => 'Error message for invalid currency.',
	'globalcollect_gateway-response-9150' => 'Error message for invalid language.',
	'globalcollect_gateway-response-400530' => 'Error message for invalid payment method, for example, not a valid credit card type.',
	'globalcollect_gateway-response-430306' => 'Error message for expired credit card.',
	'globalcollect_gateway-response-430330' => 'Error message for invalid card number.',
	'globalcollect_gateway-response-430421' => 'Error message for declined credit card transaction. This error may be due to incorrect information being entered into the form.',
	'globalcollect_gateway-response-430360' => 'Error message for declined credit card transaction due to insuffient funds.',
	'globalcollect_gateway-response-430285' => 'Error message for declined credit card transaction due to "do not honor" message from payment provider.',
	'globalcollect_gateway-response-21000150' => 'Error message for invalid bank account number.',
	'globalcollect_gateway-response-21000155' => 'Error message for invalid bank code.',
	'globalcollect_gateway-response-21000160' => 'Error message for invalid giro account number.',
	'globalcollect_gateway-response-default' => 'Error message if something went wrong on our side.',
);

/** Welsh (Cymraeg)
 * @author Lloffiwr
 */
$messages['cy'] = array(
	'globalcollectgateway' => 'Rhoddwch nawr',
	'globalcollect_gateway-response-9130' => 'Gwlad annilys.',
	'globalcollect_gateway-response-9150' => 'Iaith annilys.',
	'globalcollect_gateway-response-400530' => 'Modd talu annilys.',
	'globalcollect_gateway-response-430330' => 'Rhif annilys i gerdyn.',
	'globalcollect_gateway-response-21000155' => 'Côd banc annilys.',
);

/** German (Deutsch)
 * @author Kghbln
 */
$messages['de'] = array(
	'globalcollectgateway' => 'Jetzt spenden',
	'globalcollect_gateway-desc' => 'Ermöglicht die Zahlungsabwicklung durch GlobalCollect',
	'globalcollect_gateway-response-9130' => 'Ungültiger Staat.',
	'globalcollect_gateway-response-9140' => 'Ungültige Währung.',
	'globalcollect_gateway-response-9150' => 'Ungültige Sprache.',
	'globalcollect_gateway-response-400530' => 'Ungültige Zahlungsmethode.',
	'globalcollect_gateway-response-430306' => 'Deine Kreditkarte ist nicht mehr gültig. Bitte verwende eine andere Karte oder nutze eine andere Zahlungsmethode.',
	'globalcollect_gateway-response-430330' => 'Die Kreditkartennummer ist ungültig.',
	'globalcollect_gateway-response-430421' => 'Deine Kreditkarte konnte nicht geprüft werden. Bitte stelle sicher, dass alle Informationen denen deiner Kreditkarte entsprechend oder verwende eine andere Karte.',
	'globalcollect_gateway-response-430360' => 'Die Transaktion wurde nicht bestätigt. Bitte verwende eine andere Karte oder nutze eine andere Zahlungsmethode.',
	'globalcollect_gateway-response-430285' => 'Die Transaktion wurde nicht bestätigt. Bitte verwende eine andere Karte oder nutze eine andere Zahlungsmethode.',
	'globalcollect_gateway-response-21000150' => 'Die Kontonummer ist ungültig.',
	'globalcollect_gateway-response-21000155' => 'Die Bankleitzahl ist ungültig.',
	'globalcollect_gateway-response-21000160' => 'Die Girokontonummer ist ungültig.',
	'globalcollect_gateway-response-default' => 'Während des Ausführens der Transaktion ist ein Verarbeitungsfehler aufgetreten.
Bitte versuche es später noch einmal.',
);

/** German (formal address) (‪Deutsch (Sie-Form)‬)
 * @author Kghbln
 */
$messages['de-formal'] = array(
	'globalcollect_gateway-response-430306' => 'Ihre Kreditkarte ist nicht mehr gültig. Bitte verwenden Sie eine andere Karte oder nutzen Sie eine andere Zahlungsmethode.',
	'globalcollect_gateway-response-430421' => 'Ihre Kreditkarte konnte nicht geprüft werden. Bitte stellen Sie sicher, dass alle Informationen denen Ihrer Kreditkarte entsprechend oder verwenden Sie eine andere Karte.',
	'globalcollect_gateway-response-430360' => 'Die Transaktion wurde nicht bestätigt. Bitte verwenden Sie eine andere Karte oder nutzen Sie eine andere Zahlungsmethode.',
	'globalcollect_gateway-response-430285' => 'Die Transaktion wurde nicht bestätigt. Bitte verwenden Sie eine andere Karte oder nutzen Sie eine andere Zahlungsmethode.',
	'globalcollect_gateway-response-default' => 'Während des Ausführens der Transaktion ist ein Verarbeitungsfehler aufgetreten.
Bitte versuchen Sie es später noch einmal.',
);

/** French (Français)
 * @author Gomoko
 * @author IAlex
 */
$messages['fr'] = array(
	'globalcollectgateway' => 'Faire un don maintenant',
	'globalcollect_gateway-desc' => 'Traitement des paiements GlobalCollect',
	'globalcollect_gateway-response-9130' => 'Pays invalide.',
	'globalcollect_gateway-response-9140' => 'Monnaie invalide.',
	'globalcollect_gateway-response-9150' => 'Langue invalide.',
	'globalcollect_gateway-response-400530' => 'Méthode de paiement invalide.',
	'globalcollect_gateway-response-430306' => "Votre carte de crédit a expiré. Veuillez essayer avec une autre carte ou l'une de nos autres méthodes de paiement.",
	'globalcollect_gateway-response-430330' => 'Numéro de carte non valide.',
	'globalcollect_gateway-response-430421' => 'Votre carte de crédit ne peut pas être validée. Veuillez vérifier que toutes les informations correspondent à votre carte de crédit, ou essayez avec une autre carte.',
	'globalcollect_gateway-response-430360' => "La transaction ne peut pas être autorisée. Veuillez essayer avec une autre carte ou l'une de nos autres méthodes de paiement.",
	'globalcollect_gateway-response-430285' => "La transaction ne peut pas être autorisée. Veuillez essayer avec une autre carte ou l'une de nos autres méthodes de paiement.",
	'globalcollect_gateway-response-21000150' => 'Numéro de compte bancaire non valide.',
	'globalcollect_gateway-response-21000155' => 'Code bancaire non valide.',
	'globalcollect_gateway-response-21000160' => 'Numéro de compte du virement invalide.',
	'globalcollect_gateway-response-default' => 'Une erreur est survenue lors du traitement de votre transaction.
Veuillez réessayer plus tard.',
);

/** Galician (Galego)
 * @author Toliño
 */
$messages['gl'] = array(
	'globalcollectgateway' => 'Fai a túa doazón agora',
	'globalcollect_gateway-desc' => 'Procesamento de pagamentos GlobalCollect',
	'globalcollect_gateway-response-9130' => 'O país non é válido.',
	'globalcollect_gateway-response-9140' => 'A moeda non é válida.',
	'globalcollect_gateway-response-9150' => 'A lingua non é válida.',
	'globalcollect_gateway-response-400530' => 'O método de pagamento non é válido.',
	'globalcollect_gateway-response-430306' => 'A túa tarxeta de crédito caducou. Proba cunha tarxeta diferente ou con algún dos outros métodos de pagamento.',
	'globalcollect_gateway-response-430330' => 'O número de tarxeta non é válido.',
	'globalcollect_gateway-response-430421' => 'Non se puido validar a túa tarxeta de crédito. Comproba que toda a información coincide coa do perfil da tarxeta ou inténtao con outra tarxeta.',
	'globalcollect_gateway-response-430360' => 'Non se puido autorizar a transacción. Proba cunha tarxeta diferente ou con algún dos outros métodos de pagamento.',
	'globalcollect_gateway-response-430285' => 'Non se puido autorizar a transacción. Proba cunha tarxeta diferente ou con algún dos outros métodos de pagamento.',
	'globalcollect_gateway-response-21000150' => 'O número da conta bancaria non é válido.',
	'globalcollect_gateway-response-21000155' => 'O código bancario non é válido.',
	'globalcollect_gateway-response-21000160' => 'O número de conta da transferencia non é válido.',
	'globalcollect_gateway-response-default' => 'Houbo un erro ao procesar a túa transacción.
Por favor, inténtao de novo máis tarde.',
);

/** Swiss German (Alemannisch)
 * @author Als-Chlämens
 */
$messages['gsw'] = array(
	'globalcollectgateway' => 'Jetz spände',
	'globalcollect_gateway-desc' => 'Ermöglicht d Zaaligsabwicklig dur GlobalCollect',
	'globalcollect_gateway-response-9130' => 'Nit giltige Staat.',
	'globalcollect_gateway-response-9140' => 'Wäärig nit gültig.',
	'globalcollect_gateway-response-9150' => 'Sprooch nit gültig.',
	'globalcollect_gateway-response-400530' => 'Zaaligsmethod isch nit gültig.',
	'globalcollect_gateway-response-430306' => 'Dyni Kreditcharte isch abgloffe. Bitte probier e andri Charte oder e andri Zaaligsmethod uss.',
	'globalcollect_gateway-response-430330' => 'D Kreditchartenummer isch nit gültig.',
	'globalcollect_gateway-response-430421' => "Dyni Kreditcharte het nit chönne validiert werde. Due bitte überpriefe, ob alli Informatione mit dyner Charte überyystimme, oder probier's mit ere andre Charte.",
	'globalcollect_gateway-response-430360' => 'D Transaktion het nit chönne bstätigt werde. Nimm bitte e andri Charte oder probier e andri Zaaligsmethod uss.',
	'globalcollect_gateway-response-430285' => 'D Transaktion het nit chönne bstätigt werde. Nimm bitte e andri Charte oder probier e andri Zaaligsmethod uss.',
	'globalcollect_gateway-response-21000150' => 'D Kontonummer isch nit gültig.',
	'globalcollect_gateway-response-21000155' => 'D Bankleitzaal isch nit gültig.',
	'globalcollect_gateway-response-21000160' => 'D Girokontonummer isch nit gültig.',
	'globalcollect_gateway-response-default' => 'S het e Verarbeitigsfähler gee bi dr Uusfierig vu Dyyre Transaktion.
Bitte versuech s speter nonemol.',
);

/** Interlingua (Interlingua)
 * @author McDutchie
 */
$messages['ia'] = array(
	'globalcollectgateway' => 'Face un donation ora',
	'globalcollect_gateway-desc' => 'Processamento de pagamentos GlobalCollect',
	'globalcollect_gateway-response-9130' => 'Pais invalide.',
	'globalcollect_gateway-response-9140' => 'Moneta invalide.',
	'globalcollect_gateway-response-9150' => 'Lingua invalide.',
	'globalcollect_gateway-response-400530' => 'Methodo de pagamento invalide.',
	'globalcollect_gateway-response-430306' => 'Vostre carta de credito ha expirate. Per favor essaya un altere carta o un de nostre altere methodos de pagamento.',
	'globalcollect_gateway-response-430330' => 'Numero de carta invalide.',
	'globalcollect_gateway-response-430421' => 'Vostre carta de credito non poteva esser validate. Per favor verifica que tote le informationes corresponde al profilo de vostre carta, o usa un altere carta.',
	'globalcollect_gateway-response-430360' => 'Le transaction non poteva esser autorisate. Per favor usa un altere carta o un de nostre altere methodos de pagamento.',
	'globalcollect_gateway-response-430285' => 'Le transaction non poteva esser autorisate. Per favor usa un altere carta o un de nostre altere methodos de pagamento.',
	'globalcollect_gateway-response-21000150' => 'Numero de conto bancari invalide.',
	'globalcollect_gateway-response-21000155' => 'Codice bancari invalide.',
	'globalcollect_gateway-response-21000160' => 'Numero de conto de giro invalide.',
	'globalcollect_gateway-response-default' => 'Un error occurreva durante le tractamento de tu transaction.
Per favor reproba plus tarde.',
);

/** Luxembourgish (Lëtzebuergesch)
 * @author Robby
 */
$messages['lb'] = array(
	'globalcollectgateway' => 'Maacht Ären Don elo',
	'globalcollect_gateway-desc' => 'Ofwécklung vum Bezuelen duerch GlobalCollect',
	'globalcollect_gateway-response-9130' => 'Net valabelt Land.',
	'globalcollect_gateway-response-9140' => 'Net valabel Währung.',
	'globalcollect_gateway-response-9150' => 'Net valabel Sprooch.',
	'globalcollect_gateway-response-430306' => 'Är Kreditkaart ass ofgelaf. Probéiert w.e.g. en aner Kaart oder eng vun eisen anere Méiglechkeete fir ze bezuelen.',
	'globalcollect_gateway-response-21000150' => "D'Kontonummer ass net valabel.",
	'globalcollect_gateway-response-21000155' => "De Code fir d'Bank ass net valabel.",
	'globalcollect_gateway-response-21000160' => "D'Giro-Kontonummer ass net valabel.",
);

/** Macedonian (Македонски)
 * @author Bjankuloski06
 */
$messages['mk'] = array(
	'globalcollectgateway' => 'Дарувајте сега',
	'globalcollect_gateway-desc' => 'Платежна обработка GlobalCollect',
	'globalcollect_gateway-response-9130' => 'Неважечка земја.',
	'globalcollect_gateway-response-9140' => 'Неважечка валута.',
	'globalcollect_gateway-response-9150' => 'Неважечки јазик.',
	'globalcollect_gateway-response-400530' => 'Неважечки начин на плаќање',
	'globalcollect_gateway-response-430306' => 'Картичката ви е истечена. Обидете се со друга картичка или поинаков начин на плаќање.',
	'globalcollect_gateway-response-430330' => 'Неважечки број на картичка.',
	'globalcollect_gateway-response-430421' => 'Не можв да ја потврдам картичката. Проверете дали сите наведени информации се совпаѓаат со оние во профилот на картичката, или пак обидете се со друга картичка.',
	'globalcollect_gateway-response-430360' => 'Не можев да ја овластам трансакцијата. Обидете се со друга картичка или поинаков начин на плаќање.',
	'globalcollect_gateway-response-430285' => 'Не можев да ја овластам трансакцијата. Обидете се со друга картичка или поинаков начин на плаќање.',
	'globalcollect_gateway-response-21000150' => 'Неважечка сметка.',
	'globalcollect_gateway-response-21000155' => 'Неважечки банковски код.',
	'globalcollect_gateway-response-21000160' => 'Неважечка жиро сметка.',
	'globalcollect_gateway-response-default' => 'Настана грешка при обработката на плаќањето.
Обидете се повторно.',
);

/** Malay (Bahasa Melayu)
 * @author Anakmalaysia
 * @author Diagramma Della Verita
 */
$messages['ms'] = array(
	'globalcollectgateway' => 'Derma sekarang',
	'globalcollect_gateway-desc' => 'Pemprosesan pembayaran GlobalCollect',
	'globalcollect_gateway-response-9130' => 'Negara tidak sah.',
	'globalcollect_gateway-response-9140' => 'Mata wang tidak sah.',
	'globalcollect_gateway-response-9150' => 'Bahasa tidak sah.',
	'globalcollect_gateway-response-400530' => 'Jenis pembayaran yang dipilih tidak sah.',
	'globalcollect_gateway-response-430306' => 'Kad kredit anda telah luput. Sila cuba dengan kad yang lain atau pilih satu daripada empat cara pembayaran yang lain.',
	'globalcollect_gateway-response-430330' => 'Kad kredit tidak sah.',
	'globalcollect_gateway-response-430421' => 'Kad kredit anda tidak dapat disahkan. Sila pastikan kesemua maklumat yang diisi sama dengan profil kad kredit anda atau sila cuba semula dengan kad yang lain.',
	'globalcollect_gateway-response-430360' => 'Transaksi tidak dapat disahkan. Sila cuba dengan kad yang lain atau pilih satu daripada empat cara pembayaran yang lain.',
	'globalcollect_gateway-response-430285' => 'Transaksi tidak dapat disahkan. Sila cuba dengan kad yang lain atau pilih satu daripada empat cara pembayaran yang lain.',
	'globalcollect_gateway-response-21000150' => 'Nombor akaun bank tidah sah.',
	'globalcollect_gateway-response-21000155' => 'Kod bank tidak sah.',
	'globalcollect_gateway-response-21000160' => 'Nombor akaun giro tidak sah.',
	'globalcollect_gateway-response-default' => 'Terdapat masalah dalam memproses transaksi anda. 
Sila cuba sebentar lagi.',
);

/** Dutch (Nederlands)
 * @author SPQRobin
 * @author Siebrand
 */
$messages['nl'] = array(
	'globalcollectgateway' => 'Doneer nu',
	'globalcollect_gateway-desc' => 'Betalingsverwerking via GlobalCollect',
	'globalcollect_gateway-response-9130' => 'Ongeldig land.',
	'globalcollect_gateway-response-9140' => 'Ongeldige valuta.',
	'globalcollect_gateway-response-9150' => 'Ongeldige taal.',
	'globalcollect_gateway-response-400530' => 'Ongeldige betalingsmethode.',
	'globalcollect_gateway-response-430306' => 'Uw creditcard is verlopen. Probeer een andere kaart of een van onze andere betalingsmethoden.',
	'globalcollect_gateway-response-430330' => 'Ongeldig kaartnummer.',
	'globalcollect_gateway-response-430421' => 'Uw creditcard kan niet worden gevalideerd. Controleer alstublieft of alle informatie overeenkomt met uw creditcardgegevens, of gebruik een andere kaart.',
	'globalcollect_gateway-response-430360' => 'De transactie kan niet worden geautoriseerd. Gebruik een andere kaart of een van onze andere betalingsmethoden.',
	'globalcollect_gateway-response-430285' => 'De transactie kan niet worden geautoriseerd. Gebruik een andere kaart of een van onze andere betalingsmethoden.',
	'globalcollect_gateway-response-21000150' => 'Ongeldig rekeningnummer.',
	'globalcollect_gateway-response-21000155' => 'Ongeldige bankcode.',
	'globalcollect_gateway-response-21000160' => 'Ongeldig girorekeningnummer.',
	'globalcollect_gateway-response-default' => 'Er is een fout opgetreden bij het verwerken van uw transactie.
Probeer het later alstublieft nog een keer.',
);

/** Norwegian (bokmål)‬ (‪Norsk (bokmål)‬)
 * @author Jsoby
 */
$messages['no'] = array(
	'globalcollectgateway' => 'Doner nå',
	'globalcollect_gateway-desc' => 'Betalingsprosessering med GlobalCollect',
	'globalcollect_gateway-response-9130' => 'Ugyldig land.',
	'globalcollect_gateway-response-9140' => 'Ugyldig valuta.',
	'globalcollect_gateway-response-9150' => 'Ugyldig språk.',
	'globalcollect_gateway-response-400530' => 'Ugylding betalingsmetode.',
	'globalcollect_gateway-response-430306' => 'Kredittkortet ditt er utgått. Prøv et annet kort eller en av de andre betalingsmetodene våre.',
	'globalcollect_gateway-response-430330' => 'Ugyldig kortnummer.',
	'globalcollect_gateway-response-430421' => 'Kredittkortet ditt kunne ikke valideres. Sjekk at informasjonen du har oppgitt stemmer overens med det som står på kortet, eller prøv et annet kort.',
	'globalcollect_gateway-response-430360' => 'Overføringen kunne ikke autoriseres. Prøv et annet kort eller en av de andre betalingsmåtene våre.',
	'globalcollect_gateway-response-430285' => 'Overføringen kunne ikke autoriseres. Prøv et annet kort eller en av de andre betalingsmetodene våre.',
	'globalcollect_gateway-response-21000150' => 'Ugyldig kontonummer.',
	'globalcollect_gateway-response-21000155' => 'Ugyldig bankkode.',
	'globalcollect_gateway-response-21000160' => 'Ugyldig girokontonummer.',
	'globalcollect_gateway-response-default' => 'Det oppsto en feil under behandlingen av overføringen din.
Prøv igjen senere.',
);

/** Swahili (Kiswahili)
 * @author Lloffiwr
 */
$messages['sw'] = array(
	'globalcollectgateway' => 'Changia sasa',
	'globalcollect_gateway-response-9130' => 'Jina batili la nchi.',
	'globalcollect_gateway-response-9150' => 'Lugha batili.',
	'globalcollect_gateway-response-430330' => 'Namba batili ya kadi.',
	'globalcollect_gateway-response-21000155' => 'Kodi batili ya benki.',
);

