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
	'globalcollect_gateway-fakesucceed' => 'Fake a successful payment',
	'globalcollect_gateway-fakefail' => 'Fake a failed payment',
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
 * @author Shirayuki
 */
$messages['qqq'] = array(
	'globalcollectgateway' => '{{Identical|Make your donation now}}',
	'globalcollect_gateway-desc' => '{{desc}}',
	'globalcollect_gateway-fakesucceed' => 'This message is shown in a test environment. It labels a button that will signal a successful payment to the software.',
	'globalcollect_gateway-fakefail' => 'This message is shown in a test environment. It labels a button that will signal a failed payment to the software.',
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

/** Arabic (العربية)
 * @author AwamerT
 * @author Peadara
 * @author زكريا
 */
$messages['ar'] = array(
	'globalcollectgateway' => 'تبرع الآن',
	'globalcollect_gateway-desc' => 'تجهيز المدفوعات جلوبالكوليكت',
	'globalcollect_gateway-response-9130' => 'اسم بلد غير صالح',
	'globalcollect_gateway-response-9140' => 'العملة غير صالح',
	'globalcollect_gateway-response-9150' => 'لغة غير صحيحة.',
	'globalcollect_gateway-response-400530' => 'طريقة الدفع غير صحيحة.',
	'globalcollect_gateway-response-430306' => 'انتهت مدة صلاحية بطاقة الائتمان الخاصة بك. الرجاء استخدام بطاقة أخرى أو أحد من وسائل الدفع الأخرى.',
	'globalcollect_gateway-response-430330' => 'رقم بطاقة غير صالح.',
	'globalcollect_gateway-response-430421' => 'تعذرالتحقق من صحة بطاقة الائتمان الخاصة بك. الرجاء التحقق من أن جميع المعلومات يطابق تعريف بطاقة الائتمان الخاصة بك، أو حاول ببطاقة مختلفة.',
	'globalcollect_gateway-response-430360' => 'لا يمكن السماح بالحركة. الرجاء استخدام بطاقة أخرى أو أحد من وسائلنا للدفع الأخرى.',
	'globalcollect_gateway-response-430285' => 'لا يمكن السماح بالحركة. الرجاء استخدام بطاقة أخرى أو أحد من وسائلنا للدفع الأخرى.',
	'globalcollect_gateway-response-21000150' => 'رقم حساب مصرفي غير صالح.',
	'globalcollect_gateway-response-21000155' => 'رمز البنك غير صالح.',
	'globalcollect_gateway-response-21000160' => 'رقم حساب جيرو غير صالح.',
	'globalcollect_gateway-response-default' => 'حدث خطأ أثناء معالجة الحركة الخاصة بك.
الرجاء المحاولة مرة أخرى لاحقاً.',
);

/** Asturian (asturianu)
 * @author Xuacu
 */
$messages['ast'] = array(
	'globalcollectgateway' => 'Fai la to donación agora',
	'globalcollect_gateway-desc' => 'Procesamientu de pagos de GlobalCollect',
	'globalcollect_gateway-fakesucceed' => 'Finxir un pagu correutu',
	'globalcollect_gateway-fakefail' => 'Finxir un pagu fallíu',
	'globalcollect_gateway-response-9130' => 'País inválidu.',
	'globalcollect_gateway-response-9140' => 'Moneda inválida.',
	'globalcollect_gateway-response-9150' => 'Llingua inválida.',
	'globalcollect_gateway-response-400530' => 'Métodu de pagu inválidu.',
	'globalcollect_gateway-response-430306' => 'La tarxeta de créditu caducó. Por favor, prueba con otra tarxeta o con otru de los nuesos métodos de pagu.',
	'globalcollect_gateway-response-430330' => 'Númberu de tarxeta inválidu.',
	'globalcollect_gateway-response-430421' => 'Non se pudo validar la to tarxeta de créditu. Comprueba que tola información case cola del perfil de la tarxeta o prueba con otra tarxeta.',
	'globalcollect_gateway-response-430360' => 'Nun pudo autorizase la trensaición. Pruebe con una tarxeta diferente o con dalgún de los otros métodos de pagu.',
	'globalcollect_gateway-response-430285' => 'Nun pudo autorizase a trensaición. Pruebe con una tarxeta diferente o con dalgún de los otros métodos de pagu.',
	'globalcollect_gateway-response-21000150' => 'Númberu de cuenta bancaria inválidu.',
	'globalcollect_gateway-response-21000155' => 'Códigu de bancu inválidu.',
	'globalcollect_gateway-response-21000160' => 'Númberu de cuenta de xiru inválidu.',
	'globalcollect_gateway-response-default' => 'Hebo un fallu al procesar la so operación.
Por favor, vuelva a intentalo más sero.',
);

/** Belarusian (Taraškievica orthography) (беларуская (тарашкевіца)‎)
 * @author EugeneZelenko
 * @author Wizardist
 */
$messages['be-tarask'] = array(
	'globalcollectgateway' => 'Зрабіце ахвяраваньне зараз',
	'globalcollect_gateway-desc' => 'Шлюз апрацоўкі плацяжоў GlobalCollect',
	'globalcollect_gateway-fakesucceed' => 'Імітаваць пасьпяховы плацёж',
	'globalcollect_gateway-fakefail' => 'Імітаваць няўдалы плацёж',
	'globalcollect_gateway-response-9130' => 'Няслушная краіна.',
	'globalcollect_gateway-response-9140' => 'Няслушная валюта.',
	'globalcollect_gateway-response-9150' => 'Няслушная мова.',
	'globalcollect_gateway-response-400530' => 'Няслушны мэтад плацяжу.',
	'globalcollect_gateway-response-430306' => 'Вашая крэдытная картка пратэрмінаваная. Калі ласка, выкарыстайце іншую картку ці выберыце іншыя мэтады плацяжу.',
	'globalcollect_gateway-response-430330' => 'Няслушны нумар карткі.',
	'globalcollect_gateway-response-430421' => 'Вашая картка не прайшла праверку. Калі ласка, упэўніцеся, што ўсе зьвесткі па картцы слушныя, ці скарыстайцеся іншай карткай.',
	'globalcollect_gateway-response-430360' => 'Немагчыма аўтарызаваць транзакцыю. Калі ласка, скарыстайцеся іншай карткай ці выберыце іншы мэтад плацяжу.',
	'globalcollect_gateway-response-430285' => 'Немагчыма аўтарызаваць транзакцыю. Калі ласка, скарыстайцеся іншай карткай ці выберыце іншы мэтад плацяжу.',
	'globalcollect_gateway-response-21000150' => 'Няслушны нумар банкаўскага рахунку.',
	'globalcollect_gateway-response-21000155' => 'Няслушны код банку.',
	'globalcollect_gateway-response-21000160' => 'Няслушны нумар рахунку giro.',
	'globalcollect_gateway-response-default' => 'У час апрацоўкі Вашай транзакцыі ўзьнікла памылка.
Калі ласка, паспрабуйце зноў пазьней.',
);

/** Breton (brezhoneg)
 * @author Fohanno
 * @author Fulup
 */
$messages['br'] = array(
	'globalcollectgateway' => 'Grit ho tonezon bremañ',
	'globalcollect_gateway-response-9130' => 'Bro direizh.',
	'globalcollect_gateway-response-9140' => 'Moneiz direizh.',
	'globalcollect_gateway-response-9150' => 'Yezh direizh.',
	'globalcollect_gateway-response-400530' => 'Doare paeañ direizh.',
	'globalcollect_gateway-response-430306' => 'Diamzeret eo ho kartenn-vank. Klaskit ober gant ur gartenn all pe gant unan eus hon doareoù all da baeañ.',
	'globalcollect_gateway-response-430330' => 'Niverenn gartenn direizh.',
	'globalcollect_gateway-response-21000150' => 'Niverenn gont-vank direizh.',
	'globalcollect_gateway-response-21000155' => 'Kod-bank direizh.',
	'globalcollect_gateway-response-21000160' => 'Niverenn gont an dreuzvankadenn direizh.',
	'globalcollect_gateway-response-default' => 'Ur fazi zo bet e-ser plediñ gant ho treuzgread.
Klaskit en-dro a-benn ur pennadig.',
);

/** Catalan (català)
 * @author Arnaugir
 * @author Pitort
 */
$messages['ca'] = array(
	'globalcollectgateway' => 'Feu ara un donatiu',
	'globalcollect_gateway-response-9130' => 'País incorrecte.',
	'globalcollect_gateway-response-9140' => 'Moneda invàlida.',
	'globalcollect_gateway-response-9150' => 'Idioma invàlid.',
	'globalcollect_gateway-response-400530' => 'Mètode de pagament invàlid.',
	'globalcollect_gateway-response-430330' => 'Número de targeta invàlid.',
);

/** Chechen (нохчийн)
 * @author Умар
 */
$messages['ce'] = array(
	'globalcollectgateway' => 'ГӀо де хӀинца',
);

/** Czech (čeština)
 * @author Mormegil
 */
$messages['cs'] = array(
	'globalcollectgateway' => 'Poskytnout příspěvek',
	'globalcollect_gateway-desc' => 'Zpracování plateb přes GlobalCollect',
	'globalcollect_gateway-fakesucceed' => 'Předstírat úspěšnou platbu',
	'globalcollect_gateway-fakefail' => 'Předstírat neúspěšnou platbu',
	'globalcollect_gateway-response-9130' => 'Neplatná země.',
	'globalcollect_gateway-response-9140' => 'Neplatná měna.',
	'globalcollect_gateway-response-9150' => 'Neplatný jazyk.',
	'globalcollect_gateway-response-400530' => 'Neplatná platební metoda.',
	'globalcollect_gateway-response-430306' => 'Vaší platební kartě vypršela platnost. Zkuste jinou kartu nebo některý jiný způsob platby.',
	'globalcollect_gateway-response-430330' => 'Neplatné číslo karty.',
	'globalcollect_gateway-response-430421' => 'Vaši kreditní kartu se nepodařilo ověřit. Zkontrolujte, zda všechny informace odpovídají, nebo zkuste jinou kartu.',
	'globalcollect_gateway-response-430360' => 'Transakci se nepodařilo autorizovat. Zkuste jinou kartu nebo některý jiný způsob platby.',
	'globalcollect_gateway-response-430285' => 'Transakci se nepodařilo autorizovat. Zkuste jinou kartu nebo některý jiný způsob platby.',
	'globalcollect_gateway-response-21000150' => 'Neplatné číslo bankovního účtu.',
	'globalcollect_gateway-response-21000155' => 'Neplatný kód banky.',
	'globalcollect_gateway-response-21000160' => 'Neplatné číslo žirového účtu.',
	'globalcollect_gateway-response-default' => 'Při zpracovávání vaší transakce došlo k chybě.
Zkuste to znovu o něco později.',
);

/** Welsh (Cymraeg)
 * @author Lloffiwr
 */
$messages['cy'] = array(
	'globalcollectgateway' => 'Rhoddwch nawr',
	'globalcollect_gateway-desc' => 'Prosesu taliadau trwy GlobalCollect',
	'globalcollect_gateway-fakesucceed' => 'Esgus bod taliad wedi llwyddo',
	'globalcollect_gateway-fakefail' => 'Esgus bod taliad wedi methu',
	'globalcollect_gateway-response-9130' => 'Gwlad annilys.',
	'globalcollect_gateway-response-9140' => 'Arian breiniol annilys.',
	'globalcollect_gateway-response-9150' => 'Iaith annilys.',
	'globalcollect_gateway-response-400530' => 'Modd talu annilys.',
	'globalcollect_gateway-response-430306' => 'Mae eich cerdyn credyd wedi dod i ben. Defnyddiwch gerdyn arall neu fodd gwahanol o dalu.',
	'globalcollect_gateway-response-430330' => 'Rhif annilys i gerdyn.',
	'globalcollect_gateway-response-430421' => 'Ni ddilyswyd eich cerdyn credyd. Sicrhewch bod yr holl fanylion yn gywir fel ag y maent ar eich cyfrif cerdyn credyd, neu rhowch gynnig ar gerdyn arall.',
	'globalcollect_gateway-response-430360' => "Ni ellid awdurdodi'r gweithrediad hwn. Rhowch gynnig ar gerdyn arall neu defnyddiwch modd arall o dalu.",
	'globalcollect_gateway-response-430285' => "Ni ellid awdurdodi'r gweithrediad hwn. Rhowch gynnig ar gerdyn arall neu defnyddiwch modd arall o dalu.",
	'globalcollect_gateway-response-21000150' => 'Rhif annilys i gyfrif banc.',
	'globalcollect_gateway-response-21000155' => 'Côd banc annilys.',
	'globalcollect_gateway-response-21000160' => 'Rhif annilys i gyfrif giro.',
	'globalcollect_gateway-response-default' => 'Cafwyd gwall wrth drin eich gweithrediad.
Ceisiwch eto ymhen tipyn.',
);

/** Danish (dansk)
 * @author Peter Alberti
 */
$messages['da'] = array(
	'globalcollectgateway' => 'Doner nu',
	'globalcollect_gateway-desc' => 'Håndtering af betaling via GlobalCollect',
	'globalcollect_gateway-response-9130' => 'Ugyldigt land.',
	'globalcollect_gateway-response-9140' => 'Ugyldig valuta.',
	'globalcollect_gateway-response-9150' => 'Ugyldigt sprog.',
	'globalcollect_gateway-response-400530' => 'Ugyldig betalingsmetode.',
	'globalcollect_gateway-response-430306' => 'Dit kreditkort er udløbet. Vær så venlig at prøve et andet kort eller en af vores andre betalingsmetoder.',
	'globalcollect_gateway-response-430330' => 'Ugyldigt kortnummer.',
	'globalcollect_gateway-response-430421' => 'Dit kreditkort kunne ikke valideres. Vær så venlig at kontrollere, at al information stemmer overens med dit kort, eller prøv med et andet kort.',
	'globalcollect_gateway-response-430360' => 'Transaktionen kunne ikke godkendes. Vær så venlig at prøve et andet kort eller en af vores andre betalingsmetoder.',
	'globalcollect_gateway-response-430285' => 'Transaktionen kunne ikke godkendes. Vær så venlig at prøve et andet kort eller en af vores andre betalingsmetoder.',
	'globalcollect_gateway-response-21000150' => 'Ugyldigt kontonummer.',
	'globalcollect_gateway-response-21000155' => 'Ugyldig bankkode.',
	'globalcollect_gateway-response-21000160' => 'Ugyldigt girokontonummer.',
	'globalcollect_gateway-response-default' => 'Der opstod en fejl under behandlingen af din transaktion.
Prøv venligst igen senere.',
);

/** German (Deutsch)
 * @author Kghbln
 * @author Metalhead64
 */
$messages['de'] = array(
	'globalcollectgateway' => 'Jetzt spenden',
	'globalcollect_gateway-desc' => 'Ermöglicht die Zahlungsabwicklung durch GlobalCollect',
	'globalcollect_gateway-fakesucceed' => 'Eine erfolgreiche Zahlung vortäuschen',
	'globalcollect_gateway-fakefail' => 'Eine fehlgeschlagene Zahlung vortäuschen',
	'globalcollect_gateway-response-9130' => 'Ungültiger Staat.',
	'globalcollect_gateway-response-9140' => 'Ungültige Währung.',
	'globalcollect_gateway-response-9150' => 'Ungültige Sprache.',
	'globalcollect_gateway-response-400530' => 'Ungültige Zahlungsmethode.',
	'globalcollect_gateway-response-430306' => 'Ihre Kreditkarte ist nicht mehr gültig. Bitte verwenden Sie eine andere Karte oder nutzen Sie eine andere Zahlungsmethode.',
	'globalcollect_gateway-response-430330' => 'Die Kreditkartennummer ist ungültig.',
	'globalcollect_gateway-response-430421' => 'Ihre Kreditkarte konnte nicht geprüft werden. Bitte stellen Sie sicher, dass alle Informationen denen Ihrer Kreditkarte entsprechend oder verwenden Sie eine andere Karte.',
	'globalcollect_gateway-response-430360' => 'Die Transaktion wurde nicht bestätigt. Bitte verwenden Sie eine andere Karte oder nutzen Sie eine andere Zahlungsmethode.',
	'globalcollect_gateway-response-430285' => 'Die Transaktion wurde nicht bestätigt. Bitte verwenden Sie eine andere Karte oder nutzen Sie eine andere Zahlungsmethode.',
	'globalcollect_gateway-response-21000150' => 'Die Kontonummer ist ungültig.',
	'globalcollect_gateway-response-21000155' => 'Die Bankleitzahl ist ungültig.',
	'globalcollect_gateway-response-21000160' => 'Die Girokontonummer ist ungültig.',
	'globalcollect_gateway-response-default' => 'Während des Ausführens der Transaktion ist ein Verarbeitungsfehler aufgetreten.
Bitte versuchen Sie es später noch einmal.',
);

/** German (formal address) (Deutsch (Sie-Form)‎)
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

/** Zazaki (Zazaki)
 * @author Erdemaslancan
 */
$messages['diq'] = array(
	'globalcollectgateway' => 'Bêca xo newke bıkerê',
);

/** Lower Sorbian (dolnoserbski)
 * @author Michawiki
 */
$messages['dsb'] = array(
	'globalcollectgateway' => 'Něnto pósćiś',
	'globalcollect_gateway-desc' => 'Zmóžnja pśewjeźenje płaśenjow pśez GlobalCollect',
	'globalcollect_gateway-response-9130' => 'Njepłaśiwy kraj.',
	'globalcollect_gateway-response-9140' => 'Njepłaśiwe pjenjeze.',
	'globalcollect_gateway-response-9150' => 'Njepłaśiwa rěc.',
	'globalcollect_gateway-response-400530' => 'Njepłaśiwa metoda płaśenja.',
	'globalcollect_gateway-response-430330' => 'Njepłaśiwy kórtowy numer.',
	'globalcollect_gateway-response-21000150' => 'Njepłaśiwy kontowy numer.',
	'globalcollect_gateway-response-21000155' => 'Njepłaśiwy numer banki.',
	'globalcollect_gateway-response-21000160' => 'Njepłaśiwy girokontowy numer.',
);

/** Greek (Ελληνικά)
 * @author ZaDiak
 */
$messages['el'] = array(
	'globalcollectgateway' => 'Κάντε τη δωρεά σας τώρα',
	'globalcollect_gateway-response-9130' => 'Μη έγκυρη χώρα.',
	'globalcollect_gateway-response-9140' => 'Μη έγκυρο νόμισμα.',
	'globalcollect_gateway-response-9150' => 'Μη έγκυρη γλώσσα.',
	'globalcollect_gateway-response-400530' => 'Μη έγκυρη μέθοδος πληρωμής.',
	'globalcollect_gateway-response-430330' => 'Μη έγκυρος αριθμός κάρτας.',
	'globalcollect_gateway-response-21000150' => 'Μη έγκυρος αριθμός λογαριασμού τράπεζας.',
	'globalcollect_gateway-response-21000155' => 'Μη έγκυρος κωδικός τράπεζας.',
);

/** British English (British English)
 * @author Shirayuki
 */
$messages['en-gb'] = array(
	'globalcollect_gateway-response-430360' => 'The transaction could not be authorised. Please try a different card or one of our other payment methods.',
	'globalcollect_gateway-response-430285' => 'The transaction could not be authorised. Please try a different card or one of our other payment methods.',
);

/** Esperanto (Esperanto)
 * @author Yekrats
 */
$messages['eo'] = array(
	'globalcollectgateway' => 'Fari vian donacon nun',
	'globalcollect_gateway-response-9130' => 'Malvalida lando.',
	'globalcollect_gateway-response-9140' => 'Malvalida valuto.',
	'globalcollect_gateway-response-9150' => 'Malvalida lingvo.',
);

/** Spanish (español)
 * @author Armando-Martin
 * @author Bea.miau
 * @author Larjona
 */
$messages['es'] = array(
	'globalcollectgateway' => 'Haga su donación ahora',
	'globalcollect_gateway-desc' => 'Procesamiento de pagos de GlobalCollect',
	'globalcollect_gateway-fakesucceed' => 'Simular un pago correcto',
	'globalcollect_gateway-fakefail' => 'Simular un pago erróneo',
	'globalcollect_gateway-response-9130' => 'País no válido',
	'globalcollect_gateway-response-9140' => 'Moneda no válida',
	'globalcollect_gateway-response-9150' => 'Idioma no válido.',
	'globalcollect_gateway-response-400530' => 'Método de pago no válido',
	'globalcollect_gateway-response-430306' => 'Su tarjeta de crédito ha caducado. Porfavor, use otra tarjeta u otro de nuestros métodos de pago.',
	'globalcollect_gateway-response-430330' => 'Número de tarjeta no válido',
	'globalcollect_gateway-response-430421' => 'Su tarjeta de crédito no puede ser validada. Porfavor, verifique que toda la información concuerda con el perfil de su tarjeta de crédito, o pruebe otra tarjeta diferente.',
	'globalcollect_gateway-response-430360' => 'La transacción no puede ser autorizada. Porfavor, pruebe otra tarjeta de crédito u otro de nuestros métodos de pago.',
	'globalcollect_gateway-response-430285' => 'La transacción no puede ser autorizada. Porfavor, pruebe otra tarjeta de crédito u otro de nuestros métodos de pago.',
	'globalcollect_gateway-response-21000150' => 'Cuenta bancaria no válida',
	'globalcollect_gateway-response-21000155' => 'Codigo bancario no válido',
	'globalcollect_gateway-response-21000160' => 'Número de cuenta de giro inválida.',
	'globalcollect_gateway-response-default' => 'Hubo un error procesando su transacción.
Por favor intentelo de nuevo mas tarde.',
);

/** Estonian (eesti)
 * @author Pikne
 */
$messages['et'] = array(
	'globalcollectgateway' => 'Anneta kohe',
	'globalcollect_gateway-desc' => 'Makse käitlemine GlobalCollecti kaudu',
	'globalcollect_gateway-response-9130' => 'Vigane maa.',
	'globalcollect_gateway-response-9140' => 'Vigane vääring.',
	'globalcollect_gateway-response-9150' => 'Vigane keel.',
	'globalcollect_gateway-response-400530' => 'Vigane makseviis.',
	'globalcollect_gateway-response-430306' => 'Sinu krediitkaart on aegunud. Palun proovi teise kaardiga või muud makseviisi.',
	'globalcollect_gateway-response-430330' => 'Vigane kaardi number.',
	'globalcollect_gateway-response-430421' => 'Sinu krediitkaarti ei saanud valideerida. Palun kontrolli, et kõik andmed vastaksid sinu kaardi profiilile või proovi teist kaarti.',
	'globalcollect_gateway-response-430360' => 'Ülekannet ei kinnitatud. Palun proovi teist kaarti või muud makseviisi.',
	'globalcollect_gateway-response-430285' => 'Ülekannet ei kinnitatud. Palun proovi teist kaarti või muud makseviisi.',
	'globalcollect_gateway-response-21000150' => 'Vigane pangakonto number.',
	'globalcollect_gateway-response-21000155' => 'Vigane pangakood.',
	'globalcollect_gateway-response-21000160' => 'Vigane žiirokonto number.',
	'globalcollect_gateway-response-default' => 'Ülekande käitlemisel ilmnes tõrge.
Palun proovi hiljem uuesti.',
);

/** Persian (فارسی)
 * @author Mjbmr
 */
$messages['fa'] = array(
	'globalcollect_gateway-response-9130' => 'کشور نامعتبر.',
	'globalcollect_gateway-response-9140' => 'ارز نامعتبر.',
	'globalcollect_gateway-response-9150' => 'زبان نامعتبر.',
	'globalcollect_gateway-response-430330' => 'شماره کارت نامعتبر.',
);

/** Finnish (suomi)
 * @author Alluk.
 * @author Nedergard
 * @author VezonThunder
 */
$messages['fi'] = array(
	'globalcollectgateway' => 'Tee lahjoitus nyt',
	'globalcollect_gateway-desc' => 'GlobalCollect-maksujen käsittely',
	'globalcollect_gateway-response-9130' => 'Virheellinen maa.',
	'globalcollect_gateway-response-9140' => 'Virheellinen valuutta.',
	'globalcollect_gateway-response-9150' => 'Virheellinen kieli.',
	'globalcollect_gateway-response-400530' => 'Virheellinen maksutapa.',
	'globalcollect_gateway-response-430306' => 'Luottokorttisi on erääntynyt. Kokeile toista korttia tai jotakin muista maksutavoista.',
	'globalcollect_gateway-response-430330' => 'Virheellinen kortin numero.',
	'globalcollect_gateway-response-430421' => 'Luottokorttiasi ei voitu todentaa. Varmenna, että kaikki tiedot täsmäävät luottokorttisi tietoihin tai yritä toista korttia.',
	'globalcollect_gateway-response-430360' => 'Maksua ei voitu valtuuttaa. Kokeile toista korttia tai jotakin muista maksutavoista.',
	'globalcollect_gateway-response-430285' => 'Maksua ei voitu valtuuttaa. Kokeile toista korttia tai jotakin muista maksutavoista.',
	'globalcollect_gateway-response-21000150' => 'Virheellinen pankkitilinumero.',
	'globalcollect_gateway-response-21000155' => 'Virheellinen pankin koodi.',
	'globalcollect_gateway-response-default' => 'Maksusi käsittelyssä tapahtui virhe.
Yritä myöhemmin uudelleen.',
);

/** French (français)
 * @author Gomoko
 * @author IAlex
 * @author Sherbrooke
 */
$messages['fr'] = array(
	'globalcollectgateway' => 'Faire un don maintenant',
	'globalcollect_gateway-desc' => 'Traitement des paiements GlobalCollect',
	'globalcollect_gateway-fakesucceed' => 'Simuler un paiement fructueux',
	'globalcollect_gateway-fakefail' => 'Simuler un paiement raté',
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

/** Franco-Provençal (arpetan)
 * @author ChrisPtDe
 */
$messages['frp'] = array(
	'globalcollectgateway' => 'Balyéd orendrêt',
	'globalcollect_gateway-response-9130' => 'Payis envalido.',
	'globalcollect_gateway-response-9140' => 'Monéya envalida.',
	'globalcollect_gateway-response-9150' => 'Lengoua envalida.',
	'globalcollect_gateway-response-400530' => 'Moyen de payement envalido.',
	'globalcollect_gateway-response-430330' => 'Numerô de cârta envalido.',
);

/** Galician (galego)
 * @author Toliño
 */
$messages['gl'] = array(
	'globalcollectgateway' => 'Fai a túa doazón agora',
	'globalcollect_gateway-desc' => 'Procesamento de pagamentos de GlobalCollect',
	'globalcollect_gateway-fakesucceed' => 'Finxir un pagamento correcto',
	'globalcollect_gateway-fakefail' => 'Finxir un pagamento erróneo',
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

/** Hebrew (עברית)
 * @author Amire80
 */
$messages['he'] = array(
	'globalcollectgateway' => 'תִרמו עכשיו',
	'globalcollect_gateway-desc' => 'עיבוד תשלומים של GlobalCollect',
	'globalcollect_gateway-response-9130' => 'מדינה בלתי־תקינה.',
	'globalcollect_gateway-response-9140' => 'מטבע בלתי־תקין.',
	'globalcollect_gateway-response-9150' => 'שפה בלתי־תקינה.',
	'globalcollect_gateway-response-400530' => 'שיטת תשלום בלתי־תקינה.',
	'globalcollect_gateway-response-430306' => 'כרטיס האשראי שלך פג. נא לנסות כרטיס אחר או שיטת תשלום אחרת.',
	'globalcollect_gateway-response-430330' => 'מספר כרטיס בלתי־תקין.',
	'globalcollect_gateway-response-430421' => 'אישור כרטיס האשראי שלך נכשל. נא לוודא שכל המידע על כרטיס האשראי תקין או לנסות כרטיס אחר.',
	'globalcollect_gateway-response-430360' => 'העסקה לא אושרה. נא לנסות כרטיס אחר או שיטת תשלום אחרת.',
	'globalcollect_gateway-response-430285' => 'העסקה לא אושרה. נא לנסות כרטיס אחר או שיטת תשלום אחרת.',
	'globalcollect_gateway-response-21000150' => 'מספר חשבון בנק בלתי־תקין.',
	'globalcollect_gateway-response-21000155' => 'קוד בנק בלתי־תקין.',
	'globalcollect_gateway-response-21000160' => 'מספר חשבון giro בלתי־תקין.',
	'globalcollect_gateway-response-default' => 'אירעה שגיאה בעיבוד העסקה שלך. נא לנסות שוב מאוחר יותר.',
);

/** Hindi (हिन्दी)
 * @author Ansumang
 * @author Siddhartha Ghai
 */
$messages['hi'] = array(
	'globalcollect_gateway-response-9130' => 'अमान्य देश।',
	'globalcollect_gateway-response-9140' => 'अमान्य राशि।',
	'globalcollect_gateway-response-9150' => 'अमान्य भाषा।',
	'globalcollect_gateway-response-430330' => 'अमान्य कार्ड नंबर।',
);

/** Upper Sorbian (hornjoserbsce)
 * @author Michawiki
 */
$messages['hsb'] = array(
	'globalcollectgateway' => 'Nětko darić',
	'globalcollect_gateway-desc' => 'Zmóžnja přewjedźenje płaćenjow přez GlobalCollect',
	'globalcollect_gateway-response-9130' => 'Njepłaćiwy kraj.',
	'globalcollect_gateway-response-9140' => 'Njepłaćiwa měna.',
	'globalcollect_gateway-response-9150' => 'Njepłaćiwa rěč.',
	'globalcollect_gateway-response-400530' => 'Njepłaćiwa płaćenska metoda.',
	'globalcollect_gateway-response-430306' => 'Waša kreditna karta hižo płaćiwy njeje. Prošu spytajće druhu kartu abo druhu płaćensku metodu.',
	'globalcollect_gateway-response-430330' => 'Njepłaćiwe kartowe čisło.',
	'globalcollect_gateway-response-430421' => 'Waša kreditna karta njeda so přepruwować. Prošu zawěsćće, zo wšě informacije wašemu profilej kreditneje karty wotpowěduja abo wužijće druhu kartu.',
	'globalcollect_gateway-response-430360' => 'Transakcija njeda so awtorizować. Prošu wužijće druhu kartu abo druhu płaćensku metodu.',
	'globalcollect_gateway-response-430285' => 'Transakcija njeda so awtorizować. Prošu wužijće druhu kartu abo druhu płaćensku metodu.',
	'globalcollect_gateway-response-21000150' => 'Njepłaćiwe kontowe čisło.',
	'globalcollect_gateway-response-21000155' => 'Njepłaćiwe bankowe wodźenske čisło.',
	'globalcollect_gateway-response-21000160' => 'Njepłaćiwe girokontowe čisło.',
	'globalcollect_gateway-response-default' => 'Při předźěłowanju wašeje transakcije je zmylk wustupił.
Prošu spytajće pozdźišo hišće raz.',
);

/** Hungarian (magyar)
 * @author Dj
 * @author Misibacsi
 */
$messages['hu'] = array(
	'globalcollectgateway' => 'Adakozz most',
	'globalcollect_gateway-desc' => 'GlobalCollect fizetés feldolozása',
	'globalcollect_gateway-response-9130' => 'Érvénytelen ország.',
	'globalcollect_gateway-response-9140' => 'Érvénytelen pénznem.',
	'globalcollect_gateway-response-9150' => 'Érvénytelen nyelv.',
	'globalcollect_gateway-response-400530' => 'Érvénytelen fizetési mód.',
	'globalcollect_gateway-response-430306' => 'A hitelkártyád lejárt. Próbálkozz másik kártyával, vagy más fizetési móddal!',
	'globalcollect_gateway-response-430330' => 'Érvénytelen kártyaszám.',
	'globalcollect_gateway-response-430421' => 'A hitelkártyádat nem lehet ellenőrizni. Nézd meg, hogy a megadott adatok egyeznek-e a kártyán lévő adatokkal vagy próbálkozz másik kártyával!',
	'globalcollect_gateway-response-430360' => 'A tranzakciót nem lehet érvényesíteni. Próbálkozz másik kártyával, vagy másik fizetési móddal!',
	'globalcollect_gateway-response-430285' => 'A tranzakciót nem lehet érvényesíteni. Próbálkozz másik kártyával, vagy másik fizetési móddal!',
	'globalcollect_gateway-response-21000150' => 'Érvénytelen bankszámlaszám.',
	'globalcollect_gateway-response-21000155' => 'Érvénytelen bank kód.',
	'globalcollect_gateway-response-21000160' => 'Érvénytelen giro számlaszám.',
	'globalcollect_gateway-response-default' => 'Hiba történt a tranzakció feldolgozása során.
Próbáld meg később újra!',
);

/** Interlingua (interlingua)
 * @author McDutchie
 */
$messages['ia'] = array(
	'globalcollectgateway' => 'Face un donation ora',
	'globalcollect_gateway-desc' => 'Processamento de pagamentos GlobalCollect',
	'globalcollect_gateway-fakesucceed' => 'Simular un pagamento succedite',
	'globalcollect_gateway-fakefail' => 'Simular un pagamento fallite',
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

/** Indonesian (Bahasa Indonesia)
 * @author Kenrick95
 */
$messages['id'] = array(
	'globalcollectgateway' => 'Menyumbanglah sekarang',
	'globalcollect_gateway-desc' => 'Pemrosesan pembayaran GlobalCollect',
	'globalcollect_gateway-response-9130' => 'Negara tidak valid.',
	'globalcollect_gateway-response-9140' => 'Mata uang tidak valid.',
	'globalcollect_gateway-response-9150' => 'Bahasa tidak valid.',
	'globalcollect_gateway-response-400530' => 'Metode pembayaran tidak valid.',
	'globalcollect_gateway-response-430306' => 'Kartu kredit Anda telah kadaluarsa. Silakan coba dengan kartu kredit yang lain atau metode pembayaran lainnya.',
	'globalcollect_gateway-response-430330' => 'Nomor kartu tidak valid.',
	'globalcollect_gateway-response-430421' => 'Kartu kredit Anda tidak dapat divalidasi. Mohon verifikasi bahwa semua informasi cocok dengan profil kartu kredit Anda, atau coba dengan kartu yang lain.',
	'globalcollect_gateway-response-430360' => 'Transaksi tidak dapat diotorisasi. Silakan coba dengan kartu yang lain atau metode pembayaran lainnya.',
	'globalcollect_gateway-response-430285' => 'Transaksi tidak dapat diotorisasi. Silakan coba dengan kartu yang lain atau metode pembayaran lainnya.',
	'globalcollect_gateway-response-21000150' => 'Nomor rekening bank tidak valid.',
	'globalcollect_gateway-response-21000155' => 'Kode bank tidak valid.',
	'globalcollect_gateway-response-21000160' => 'Nomor rekening giro tidak valid.',
	'globalcollect_gateway-response-default' => 'Terjadi kesalahan dalam pemrosesan transaksi Anda.
Silakan coba lagi nanti.',
);

/** Italian (italiano)
 * @author Beta16
 */
$messages['it'] = array(
	'globalcollectgateway' => 'Fai ora la tua donazione',
	'globalcollect_gateway-desc' => 'Elaborazione dei pagamenti GlobalCollect',
	'globalcollect_gateway-fakesucceed' => 'Simula un pagamento riuscito',
	'globalcollect_gateway-fakefail' => 'Simula un pagamento non riuscito',
	'globalcollect_gateway-response-9130' => 'Nazione non valida.',
	'globalcollect_gateway-response-9140' => 'Valuta non valida.',
	'globalcollect_gateway-response-9150' => 'Lingua non valida.',
	'globalcollect_gateway-response-400530' => 'Metodo di pagamento non valido.',
	'globalcollect_gateway-response-430306' => "La tua carta di credito è scaduta. Prova con un'altra carta o un altro dei nostri metodi di pagamento.",
	'globalcollect_gateway-response-430330' => 'Numero di carta non valido.',
	'globalcollect_gateway-response-430421' => "La tua carta di credito non può essere convalidata. Verifica che tutte le informazioni corrispondano al tuo profilo, o prova con un'altra carta.",
	'globalcollect_gateway-response-430360' => "La transazione non può essere autorizzata. Prova con un'altra carta o un altro dei nostri metodi di pagamento.",
	'globalcollect_gateway-response-430285' => "La transazione non può essere autorizzata. Prova con un'altra carta o un altro dei nostri metodi di pagamento.",
	'globalcollect_gateway-response-21000150' => 'Numero del conto bancario non valido.',
	'globalcollect_gateway-response-21000155' => 'Codice bancario non valido.',
	'globalcollect_gateway-response-21000160' => 'Numero del conto corrente non valido.',
	'globalcollect_gateway-response-default' => "Si è verificato un errore durante l'elaborazione della transazione.
Si prega di riprovare più tardi.",
);

/** Japanese (日本語)
 * @author Shirayuki
 * @author Whym
 */
$messages['ja'] = array(
	'globalcollectgateway' => '今すぐ寄付を',
	'globalcollect_gateway-desc' => 'グローバルコレクト決済処理',
	'globalcollect_gateway-response-9130' => '国名が無効です。',
	'globalcollect_gateway-response-9140' => '通貨が無効です。',
	'globalcollect_gateway-response-9150' => '言語名が無効です。',
	'globalcollect_gateway-response-400530' => '支払い方法が無効です。',
	'globalcollect_gateway-response-430306' => 'あなたのクレジットカードは有効期限が切れています。他のカードか他の支払い方法をお試しください。',
	'globalcollect_gateway-response-430330' => 'カード番号が無効です。',
	'globalcollect_gateway-response-430421' => 'あなたのクレジットカードの妥当性が確かめられませんでした。すべての情報がクレジットカードの個人情報と一致しているかどうかお確かめください。もしくは他のカードをお試しください。',
	'globalcollect_gateway-response-430360' => '取引は許可されませんでした。他のカードか他の支払い方法をお試しください。',
	'globalcollect_gateway-response-430285' => '取引は許可されませんでした。他のカードか他の支払い方法をお試しください。',
	'globalcollect_gateway-response-21000150' => '銀行口座番号が無効です。',
	'globalcollect_gateway-response-21000155' => '銀行コードが無効です。',
	'globalcollect_gateway-response-21000160' => '振替口座番号が無効です。',
	'globalcollect_gateway-response-default' => 'お取引の処理中にエラーが発生しました。
後でもう一度お試しください。',
);

/** Georgian (ქართული)
 * @author David1010
 */
$messages['ka'] = array(
	'globalcollectgateway' => 'გააკეთეთ შემოწირულობა ახლავე',
	'globalcollect_gateway-response-9130' => 'არასწორი ქვეყანა.',
	'globalcollect_gateway-response-9140' => 'არასწორი ვალუტა.',
	'globalcollect_gateway-response-9150' => 'არასწორი ენა.',
	'globalcollect_gateway-response-400530' => 'არასწორი გადახდის მეთოდი.',
	'globalcollect_gateway-response-430330' => 'არასწორი ბარათის ნომერი.',
	'globalcollect_gateway-response-21000150' => 'არასწორი საბანკო ანგარიშის ნომერი.',
	'globalcollect_gateway-response-21000155' => 'არასწორი საბანკო კოდი.',
);

/** Korean (한국어)
 * @author Kwj2772
 * @author 아라
 */
$messages['ko'] = array(
	'globalcollectgateway' => '지금 기부해주세요',
	'globalcollect_gateway-desc' => '글로벌컬렉트 결제 처리',
	'globalcollect_gateway-fakesucceed' => '가짜 지불 성공',
	'globalcollect_gateway-fakefail' => '가짜 지불 실패',
	'globalcollect_gateway-response-9130' => '잘못된 국가입니다.',
	'globalcollect_gateway-response-9140' => '잘못된 통화입니다.',
	'globalcollect_gateway-response-9150' => '잘못된 언어입니다.',
	'globalcollect_gateway-response-400530' => '잘못된 지불 방법입니다.',
	'globalcollect_gateway-response-430306' => '신용 카드가 만료되었습니다. 다른 카드나 다른 지불 방법 중 하나를 시도하세요.',
	'globalcollect_gateway-response-430330' => '잘못된 카드 번호입니다.',
	'globalcollect_gateway-response-430421' => '신용 카드의 유효성을 검사할 수 없습니다. 모든 정보가 신용 카드 프로필과 일치하는지 확인하거나 다른 카드로 시도하세요.',
	'globalcollect_gateway-response-430360' => '거래를 허가할 수 없습니다. 다른 카드나 다른 지불 방법 중 하나를 시도하세요.',
	'globalcollect_gateway-response-430285' => '거래를 허가할 수 없습니다. 다른 카드나 다른 지불 방법 중 하나를 시도하세요.',
	'globalcollect_gateway-response-21000150' => '잘못된 은행 계좌 번호입니다.',
	'globalcollect_gateway-response-21000155' => '잘못된 은행 코드입니다.',
	'globalcollect_gateway-response-21000160' => '잘못된 지도 계좌 번호입니다.',
	'globalcollect_gateway-response-default' => '거래를 처리하는 동안 오류가 발생했습니다.
나중에 다시 시도하세요.',
);

/** Colognian (Ripoarisch)
 * @author Purodha
 */
$messages['ksh'] = array(
	'globalcollectgateway' => 'Maach Ding Schpände jäz',
	'globalcollect_gateway-desc' => 'Et Bezahle övver <i lang="en">GlobalCollect</i> möjjelesch maache un afhandelle.',
	'globalcollect_gateway-response-9130' => 'Dat Land es onjöltesch.',
	'globalcollect_gateway-response-9140' => 'Di Zoot Jäld es onbikannnt.',
	'globalcollect_gateway-response-9150' => 'De Schprooch es onjöltesch.',
	'globalcollect_gateway-response-400530' => 'De aat zem Bezahle es onjöltesch.',
	'globalcollect_gateway-response-430306' => 'Di Kredittkaad es afjeloufe. Bes esu jood un versöhg et med ene andere Kaat, udder versöhg, ob enem andere Wääsch ze bezahle.',
	'globalcollect_gateway-response-430330' => 'Dä Kaat ier Nommer es onjöltesch.',
	'globalcollect_gateway-response-430421' => 'Ding Kreditkaat udder Aanjaabe sin beim Prööve dorschjevalle.
Bes esu jood un looer, dat all Ding Aanjaabe op Ding Profil för di Kreditkaat paße donn, udder versöhg et med ene andere Kaat.',
	'globalcollect_gateway-response-430360' => 'Di Övverdraarong kunnt nit beschtäätesch wääde. Zohwinnesch Jäld em Pott.
Bes esu jood un versöhg et med ene andere Kaat udder med enem ander Wääsch ze bezahle.',
	'globalcollect_gateway-response-430285' => 'Di Övverdraarong kunnt nit beschtäätesch wääde. Afjlehnong vum Provaider.
Bes esu jood un versöhg et med ene andere Kaat udder med enem andere Wääsch ze bezahle',
	'globalcollect_gateway-response-21000150' => 'De Konto-Nommer es onjöltesch.',
	'globalcollect_gateway-response-21000155' => 'De Bank ier Nommer es onjöltesch.',
	'globalcollect_gateway-response-21000160' => 'De Konto-Nommer es onjöltesch.',
	'globalcollect_gateway-response-default' => 'Ene Fähler es opjetrodde beim Beärbeide vun dä Övverdraarong.
Bes esu jood un versöhg et schpääder norr_ens.',
);

/** Luxembourgish (Lëtzebuergesch)
 * @author Robby
 * @author Soued031
 */
$messages['lb'] = array(
	'globalcollectgateway' => 'Maacht Ären Don elo',
	'globalcollect_gateway-desc' => 'Ofwécklung vum Bezuelen duerch GlobalCollect',
	'globalcollect_gateway-fakesucceed' => 'Eng Bezuelaktioun déi funktionéiert huet virtäuschen',
	'globalcollect_gateway-response-9130' => 'Net valabelt Land.',
	'globalcollect_gateway-response-9140' => 'Net valabel Währung.',
	'globalcollect_gateway-response-9150' => 'Net valabel Sprooch.',
	'globalcollect_gateway-response-400530' => 'Bezuelmethod net valabel.',
	'globalcollect_gateway-response-430306' => 'Är Kreditkaart ass ofgelaf. Probéiert w.e.g. en aner Kaart oder eng vun eisen anere Méiglechkeete fir ze bezuelen.',
	'globalcollect_gateway-response-430330' => "D'Kaartennummer ass net valabel.",
	'globalcollect_gateway-response-430421' => 'Är Kreditkaart konnt net validéiert ginn. Kuckt w.e.g. no ob all Informatioune mat deene vun Ärer Kreditkaart iwwerenee stëmmen, oder probéiert eng aner Kaart.',
	'globalcollect_gateway-response-430360' => "D'Transaktioun konnt net autoriséiert ginn. Versicht et w.e.g. mat enger anerer Kaart oder enger anerer Method fir ze bezuelen.",
	'globalcollect_gateway-response-430285' => "D'Transaktioun konnt net autoriséiert ginn. Versicht et w.e.g. mat enger anerer Kaart oder enger anerer Method fir ze bezuelen.",
	'globalcollect_gateway-response-21000150' => "D'Kontonummer ass net valabel.",
	'globalcollect_gateway-response-21000155' => "De Code fir d'Bank ass net valabel.",
	'globalcollect_gateway-response-21000160' => "D'Giro-Kontonummer ass net valabel.",
	'globalcollect_gateway-response-default' => 'Et gouf e Feeler beim Verschaffe vun Ärer Transaktioun.
Probéiert et w.e.g. spéider nach eng Kéier.',
);

/** Lithuanian (lietuvių)
 * @author Eitvys200
 */
$messages['lt'] = array(
	'globalcollectgateway' => 'Paaukokite dabar',
	'globalcollect_gateway-response-9130' => 'Neleistina šalis.',
	'globalcollect_gateway-response-9140' => 'Negaliojanti valiuta.',
	'globalcollect_gateway-response-9150' => 'Neleistina kalba.',
	'globalcollect_gateway-response-400530' => 'Neleistinas mokėjimo būdas.',
	'globalcollect_gateway-response-430330' => 'Negaliojantis kortelės numeris.',
	'globalcollect_gateway-response-21000150' => 'Negaliojantis banko sąskaitos numeris.',
	'globalcollect_gateway-response-21000155' => 'Neteisingas banko kodas.',
);

/** Macedonian (македонски)
 * @author Bjankuloski06
 */
$messages['mk'] = array(
	'globalcollectgateway' => 'Дарувајте сега',
	'globalcollect_gateway-desc' => 'Платежна обработка GlobalCollect',
	'globalcollect_gateway-fakesucceed' => 'Направи божемна успешна уплата',
	'globalcollect_gateway-fakefail' => 'Направи божемна неуспешна уплата',
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

/** Malayalam (മലയാളം)
 * @author Praveenp
 * @author Vssun
 */
$messages['ml'] = array(
	'globalcollectgateway' => 'ഉടൻ സംഭാവന ചെയ്യുക',
	'globalcollect_gateway-desc' => 'ഗ്ലോബൽ കളക്റ്റ് പണമിടപാട്',
	'globalcollect_gateway-response-9130' => 'രാജ്യം അസാധുവാണ്.',
	'globalcollect_gateway-response-9140' => 'നാണയം അസാധുവാണ്.',
	'globalcollect_gateway-response-9150' => 'ഭാഷ അസാധുവാണ്.',
	'globalcollect_gateway-response-400530' => 'അസാധുവായ പണമടക്കൽ രീതി',
	'globalcollect_gateway-response-430306' => 'നിങ്ങളുടെ ക്രെഡിറ്റ്കാർഡിന്റെ കാലാവധി തീർന്നിരിക്കുന്നു. ദയവായി മറ്റൊരു കാർഡോ മറ്റേതെങ്കിലും പണമടക്കൽ രീതിയോ ഉപയോഗിക്കുക.',
	'globalcollect_gateway-response-430330' => 'അസാധുവായ കാർഡ് നമ്പർ',
	'globalcollect_gateway-response-430421' => 'നിങ്ങളുടെ ക്രെഡിറ്റ്കാർഡ് സാധൂകരിക്കാനാകുന്നില്ല. ദയവായി വിവരങ്ങളെല്ലാം നിങ്ങളുടെ ക്രെഡിറ്റ്കാർഡ് വിവരങ്ങളുമായി ഒത്തുനോക്കുക, അല്ലെങ്കിൽ മറ്റൊരു കാർഡുപയോഗിക്കുക.',
	'globalcollect_gateway-response-430360' => 'ഈ ഇടപാട് സാധൂകരിക്കാനാകുന്നില്ല. ദയവായി മറ്റൊരു കാർഡുപയോഗിക്കുകയോ മറ്റേതെങ്കിലും പണമടക്കൽ രീതി അവലംബിക്കുകയോ ചെയ്യുക.',
	'globalcollect_gateway-response-430285' => 'ഈ ഇടപാട് സാധൂകരിക്കാനാകുന്നില്ല. ദയവായി മറ്റൊരു കാർഡുപയോഗിക്കുകയോ മറ്റേതെങ്കിലും പണമടക്കൽ രീതി അവലംബിക്കുകയോ ചെയ്യുക.',
	'globalcollect_gateway-response-21000150' => 'അസാധുവായ ബാങ്ക് അക്കൗണ്ട് നമ്പർ.',
	'globalcollect_gateway-response-21000155' => 'അസാധുവായ ബാങ്ക് കോഡ്.',
	'globalcollect_gateway-response-21000160' => 'അസാധുവായ ഗിറോ അക്കൗണ്ട് നമ്പർ.',
	'globalcollect_gateway-response-default' => 'താങ്കളുടെ ഇടപാട് കൈകാര്യം ചെയ്തുകൊണ്ടിരിക്കെ ഒരു പിഴവുണ്ടായിരിക്കുന്നു.
ദയവായി അൽപ്പസമയത്തിനു ശേഷം ശ്രമിക്കുക.',
);

/** Malay (Bahasa Melayu)
 * @author Anakmalaysia
 * @author Diagramma Della Verita
 */
$messages['ms'] = array(
	'globalcollectgateway' => 'Derma sekarang',
	'globalcollect_gateway-desc' => 'Pemprosesan pembayaran GlobalCollect',
	'globalcollect_gateway-fakesucceed' => 'Memalsukan pembayaran yang berjaya',
	'globalcollect_gateway-fakefail' => 'Memalsukan pembayaran yang gagal',
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

/** Maltese (Malti)
 * @author Chrisportelli
 */
$messages['mt'] = array(
	'globalcollectgateway' => 'Agħmel id-donazzjoni tiegħek issa',
	'globalcollect_gateway-response-9130' => 'Pajjiż invalidu.',
	'globalcollect_gateway-response-9140' => 'Valuta invalida.',
	'globalcollect_gateway-response-9150' => 'Lingwa invalida.',
	'globalcollect_gateway-response-400530' => "Metodu ta' ħlas invalidu.",
	'globalcollect_gateway-response-430330' => 'Numru tal-karta invalidu.',
);

/** Norwegian Bokmål (norsk bokmål)
 * @author Jsoby
 */
$messages['nb'] = array(
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

/** Dutch (Nederlands)
 * @author SPQRobin
 * @author Siebrand
 */
$messages['nl'] = array(
	'globalcollectgateway' => 'Doneer nu',
	'globalcollect_gateway-desc' => 'Betalingsverwerking via GlobalCollect',
	'globalcollect_gateway-fakesucceed' => 'Simuleer een geslaagde betaling',
	'globalcollect_gateway-fakefail' => 'Simuleer een mislukte betaling',
	'globalcollect_gateway-response-9130' => 'Ongeldig land.',
	'globalcollect_gateway-response-9140' => 'Ongeldige valuta.',
	'globalcollect_gateway-response-9150' => 'Ongeldige taal.',
	'globalcollect_gateway-response-400530' => 'Ongeldige betalingsmethode.',
	'globalcollect_gateway-response-430306' => 'Uw creditcard is verlopen. Probeer een andere kaart of een van onze andere betalingsmethoden.',
	'globalcollect_gateway-response-430330' => 'Ongeldig kaartnummer.',
	'globalcollect_gateway-response-430421' => 'Uw creditcard kan niet worden gevalideerd. Controleer of alle informatie overeenkomt met uw creditcardgegevens, of gebruik een andere kaart.',
	'globalcollect_gateway-response-430360' => 'De transactie kan niet worden geautoriseerd. Gebruik een andere kaart of een van onze andere betalingsmethoden.',
	'globalcollect_gateway-response-430285' => 'De transactie kan niet worden geautoriseerd. Gebruik een andere kaart of een van onze andere betalingsmethoden.',
	'globalcollect_gateway-response-21000150' => 'Ongeldig rekeningnummer.',
	'globalcollect_gateway-response-21000155' => 'Ongeldige bankcode.',
	'globalcollect_gateway-response-21000160' => 'Ongeldig girorekeningnummer.',
	'globalcollect_gateway-response-default' => 'Er is een fout opgetreden tijdens het verwerken van uw transactie.
Probeer het later nog een keer.',
);

/** Nederlands (informeel)‎ (Nederlands (informeel)‎)
 * @author Siebrand
 */
$messages['nl-informal'] = array(
	'globalcollect_gateway-response-430306' => 'Je creditcard is verlopen. Probeer een andere kaart of een van onze andere betalingsmethoden.',
	'globalcollect_gateway-response-430421' => 'Je creditcard kan niet worden gevalideerd. Controleer of alle informatie overeenkomt met je creditcardgegevens, of gebruik een andere kaart.',
	'globalcollect_gateway-response-default' => 'Er is een fout opgetreden tijdens het verwerken van je transactie.
Probeer het later nog een keer.',
);

/** Occitan (occitan)
 * @author Cedric31
 */
$messages['oc'] = array(
	'globalcollect_gateway-response-21000155' => 'Còde bancari invalid.',
);

/** Pälzisch (Pälzisch)
 * @author Manuae
 */
$messages['pfl'] = array(
	'globalcollect_gateway-response-9150' => 'Ugildischi Schbrooch',
);

/** Polish (polski)
 * @author Mikołka
 * @author Sp5uhe
 * @author WTM
 */
$messages['pl'] = array(
	'globalcollectgateway' => 'Przekaż darowiznę',
	'globalcollect_gateway-desc' => 'Przetwarzanie płatności GlobalCollect',
	'globalcollect_gateway-fakesucceed' => 'Symulacja udanej wpłaty',
	'globalcollect_gateway-fakefail' => 'Symulacja nieudanej wpłaty',
	'globalcollect_gateway-response-9130' => 'Nieprawidłowy kraj.',
	'globalcollect_gateway-response-9140' => 'Nieprawidłowa waluta.',
	'globalcollect_gateway-response-9150' => 'Nieprawidłowy język.',
	'globalcollect_gateway-response-400530' => 'Nieprawidłowa metoda płatności.',
	'globalcollect_gateway-response-430306' => 'Twoja karta kredytowa jest przeterminowana. Spróbuj użyć innej karty lub zmień sposób dokonania wpłaty.',
	'globalcollect_gateway-response-430330' => 'Nieprawidłowy numer karty.',
	'globalcollect_gateway-response-430421' => 'Wystąpił problem z weryfikacją Twojej karty kredytowej. Sprawdź wprowadzone dane lub spróbuj użyć innej karty.',
	'globalcollect_gateway-response-430360' => 'Wpłata nie może zostać zrealizowana. Spróbuj użyć innej karty lub zmień sposób dokonania wpłaty.',
	'globalcollect_gateway-response-430285' => 'Wpłata nie może zostać zrealizowana. Spróbuj użyć innej karty lub zmień sposób dokonania wpłaty.',
	'globalcollect_gateway-response-21000150' => 'Nieprawidłowy numer konta bankowego.',
	'globalcollect_gateway-response-21000155' => 'Nieprawidłowy kod banku.',
	'globalcollect_gateway-response-21000160' => 'Nieprawidłowy numer konta.',
	'globalcollect_gateway-response-default' => 'Wystąpił błąd podczas przeprowadzania transakcji.
Spróbuj ponownie później.',
);

/** Piedmontese (Piemontèis)
 * @author Borichèt
 * @author Dragonòt
 */
$messages['pms'] = array(
	'globalcollectgateway' => 'Fà toa donassion adess',
	'globalcollect_gateway-desc' => 'Elaborassion dij pagament GlobalCollect',
	'globalcollect_gateway-response-9130' => 'Pais nen bon.',
	'globalcollect_gateway-response-9140' => 'Moneda pa bon-a.',
	'globalcollect_gateway-response-9150' => 'Lenga nen bon-a',
	'globalcollect_gateway-response-400530' => 'Métod ëd pagament pa bon.',
	'globalcollect_gateway-response-430306' => "Soa carta ëd crédit a l'é scadùa. Për piasì, ch'a preuva na carta diferenta o un dij nòstri àutri métod ëd pagament.",
	'globalcollect_gateway-response-430330' => 'Nùmer ëd carta pa bon.',
	'globalcollect_gateway-response-430421' => "Toa carta ëd crédit a peul pa esse validà. Për piasì verìfica che tute j'anformassion a corispondo al profil ëd toa carta ëd crédit, o preuva na carta diferenta.",
	'globalcollect_gateway-response-430360' => "La transassion a peul pa esse autorisà. Për piasì, ch'a preuva na carta diferenta o un dij nòstri àutri métod ëd pagament.",
	'globalcollect_gateway-response-430285' => "La transassion a l'é nen ëstàita autorisà. Për piasì, ch'a preuva na carta diferenta o un dij nòstri àutri métod ëd pagament.",
	'globalcollect_gateway-response-21000150' => 'Nùmer dël cont bancari pa bon.',
	'globalcollect_gateway-response-21000155' => 'Còdes bancari pa bon.',
	'globalcollect_gateway-response-21000160' => 'Nùmer dël cont ëd gir pa bon.',
	'globalcollect_gateway-response-default' => "A l'é staje n'eror an tratand soa transassion.
Për piasì, ch'a preuva torna pì tard.",
);

/** Pashto (پښتو)
 * @author Ahmed-Najib-Biabani-Ibrahimkhel
 */
$messages['ps'] = array(
	'globalcollect_gateway-response-9130' => 'ناسم هېواد.',
	'globalcollect_gateway-response-9140' => 'ناسم ارز.',
	'globalcollect_gateway-response-9150' => 'ناسمه ژبه.',
);

/** Portuguese (português)
 * @author Hamilton Abreu
 * @author João Sousa
 */
$messages['pt'] = array(
	'globalcollectgateway' => 'Faça o seu donativo agora',
	'globalcollect_gateway-desc' => 'Processamento de pagamentos GlobalCollect',
	'globalcollect_gateway-response-9130' => 'País inválido.',
	'globalcollect_gateway-response-9140' => 'Divisa inválida.',
	'globalcollect_gateway-response-9150' => 'Língua inválida.',
	'globalcollect_gateway-response-400530' => 'Método de pagamento inválido.',
	'globalcollect_gateway-response-430306' => 'O seu cartão de crédito caducou. Use um cartão diferente ou um outro método de pagamento, por favor.',
	'globalcollect_gateway-response-430330' => 'O número de cartão é inválido.',
	'globalcollect_gateway-response-430421' => 'Não foi possível validar o seu cartão de crédito. Verifique se toda a informação providenciada coincide com o seu cartão de crédito, ou tente usar outro cartão, por favor.',
	'globalcollect_gateway-response-430360' => 'Não foi possível autorizar a transação. Tente usar outro cartão ou outro método de pagamento, por favor.',
	'globalcollect_gateway-response-430285' => 'Não foi possível autorizar a transação. Tente usar outro cartão ou outro método de pagamento, por favor.',
	'globalcollect_gateway-response-21000150' => 'O número de conta bancária é inválido.',
	'globalcollect_gateway-response-21000155' => 'O código bancário é inválido.',
	'globalcollect_gateway-response-21000160' => 'O número de conta bancária é inválido.',
	'globalcollect_gateway-response-default' => 'Ocorreu um erro no processamento desta transacção.
Tente novamente mais tarde, por favor.',
);

/** Brazilian Portuguese (português do Brasil)
 * @author Ppena
 */
$messages['pt-br'] = array(
	'globalcollectgateway' => 'Faça a sua doação agora',
	'globalcollect_gateway-desc' => 'Processamento de pagamentos GlobalCollect',
	'globalcollect_gateway-response-9130' => 'País inválido',
	'globalcollect_gateway-response-9140' => 'Moeda inválida',
	'globalcollect_gateway-response-9150' => 'Língua inválida',
	'globalcollect_gateway-response-400530' => 'Método de pagamento inválido',
	'globalcollect_gateway-response-430306' => 'O seu cartão de crédito esta vencido. Por favor use um cartão diferente ou um outro método de pagamento.',
	'globalcollect_gateway-response-430330' => 'Número de cartão é inválido.',
	'globalcollect_gateway-response-430421' => 'Não foi possível validar o seu cartão de crédito. Por favor verifique se toda a informação corresponde ao seu perfil de cartão de crédito, ou tente usar outro cartão.',
	'globalcollect_gateway-response-430360' => 'Não foi possível autorizar a transação. Por favor tente com outro cartão ou outro método de pagamento.',
	'globalcollect_gateway-response-430285' => 'Não foi possível autorizar a transação. Por favor tente com outro cartão ou outro método de pagamento.',
	'globalcollect_gateway-response-21000150' => 'Número de conta bancária inválido.',
	'globalcollect_gateway-response-21000155' => 'Código bancário inválido.',
	'globalcollect_gateway-response-21000160' => 'Número de conta bancária inválido.',
	'globalcollect_gateway-response-default' => 'Ocorreu um erro no processamento desta transação.
Por favor tente novamente mais tarde.',
);

/** Romanian (română)
 * @author Minisarm
 */
$messages['ro'] = array(
	'globalcollectgateway' => 'Faceți o donație acum',
	'globalcollect_gateway-desc' => 'Procesarea plății prin GlobalCollect',
	'globalcollect_gateway-response-9130' => 'Țară incorectă.',
	'globalcollect_gateway-response-9140' => 'Monedă incorectă.',
	'globalcollect_gateway-response-9150' => 'Limbă incorectă.',
	'globalcollect_gateway-response-400530' => 'Metodă de plată incorectă.',
	'globalcollect_gateway-response-430306' => 'Cardul dumneavoastră de credit a expirat. Vă rugăm să încercați cu un alt card sau să alegeți o altă metodă de plată pusă la dispoziție de noi.',
	'globalcollect_gateway-response-430330' => 'Număr de card incorect.',
	'globalcollect_gateway-response-430421' => 'Cardul dumneavoastră de credit nu a putut fi validat. Vă rugăm fie să verificați dacă toate informațiile corespund cardului dumneavoastră de credit, fie să încercați cu un alt card.',
	'globalcollect_gateway-response-430360' => 'Tranzacția nu a putut fi autorizată. Vă rugăm să încercați cu un alt card sau să alegeți o altă metodă de plată pusă la dispoziție de noi.',
	'globalcollect_gateway-response-430285' => 'Tranzacția nu a putut fi autorizată. Vă rugăm să încercați cu un alt card sau să alegeți o altă metodă de plată pusă la dispoziție de noi.',
	'globalcollect_gateway-response-21000150' => 'Număr de cont incorect.',
	'globalcollect_gateway-response-21000155' => 'Cod de bancă incorect.',
	'globalcollect_gateway-response-21000160' => 'Număr de cont giro incorect.',
	'globalcollect_gateway-response-default' => 'S-a produs o eroare în timpul procesării tranzacției dumneavoastră.
Vă rugăm să reîncercați mai târziu.',
);

/** tarandíne (tarandíne)
 * @author Joetaras
 */
$messages['roa-tara'] = array(
	'globalcollectgateway' => "Fà 'a donazziona toje mò",
	'globalcollect_gateway-desc' => 'Processe de pajamende GlobalCollect',
	'globalcollect_gateway-fakesucceed' => "Fause 'nu pajamende fatte",
	'globalcollect_gateway-fakefail' => "Fause 'nu pajamende fallite",
	'globalcollect_gateway-response-9130' => 'Nazione invalide.',
	'globalcollect_gateway-response-9140' => 'monete invalide.',
	'globalcollect_gateway-response-9150' => 'Lénghe invalide.',
	'globalcollect_gateway-response-400530' => 'Meotde de pajamende invalide.',
	'globalcollect_gateway-response-430306' => "'A carta de credite toje ha scadute. Pe piacere pruève cu 'na carte diverse o 'n'otre metode de pajaminde nuèstre.",
	'globalcollect_gateway-response-430330' => "Numere d'a carte invalide.",
	'globalcollect_gateway-response-430421' => "'A carta de credite toje non g'è validate. Pe piacere verifiche ca tutte le 'mbormaziune soddisfane 'u profile d'a carta toje, o pruève cu 'n'otra carte.",
	'globalcollect_gateway-response-430360' => "'A transazione non ge pò essere autorizzate. Pe piacere pruève 'na carta diverse o une de le otre metode de pajaminde nuèstre.",
	'globalcollect_gateway-response-430285' => "'A transazione non ge pò essere autorizzate. Pe piacere pruève 'na carta diverse o une de le otre metode de pajaminde nuèstre.",
	'globalcollect_gateway-response-21000150' => "Numere d'u conde corrende bangarie invalide.",
	'globalcollect_gateway-response-21000155' => "Codece d'a banghe invalide.",
	'globalcollect_gateway-response-21000160' => "Numere d'u giro conde invalide.",
	'globalcollect_gateway-response-default' => "S'a verificate 'n'errore processanne 'a transaziona toje.
Pe piacere pruève arrete.",
);

/** Russian (русский)
 * @author Kaganer
 * @author Александр Сигачёв
 */
$messages['ru'] = array(
	'globalcollectgateway' => 'Сделайте пожертвование сейчас',
	'globalcollect_gateway-desc' => 'Шлюз обработки платежей GlobalCollect',
	'globalcollect_gateway-fakesucceed' => 'Поддельный успешный платёж',
	'globalcollect_gateway-fakefail' => 'Поддельный невыполненный платёж',
	'globalcollect_gateway-response-9130' => 'Указана неподдерживаемая страна.',
	'globalcollect_gateway-response-9140' => 'Указана неподдерживаемая валюта.',
	'globalcollect_gateway-response-9150' => 'Указан неподдерживаемый язык.',
	'globalcollect_gateway-response-400530' => 'Некорректный способ платежа.',
	'globalcollect_gateway-response-430306' => 'Истёк срок действия вашей кредитной карты. Пожалуйста, попробуйте использовать другую карту или выберите другой способ платежа.',
	'globalcollect_gateway-response-430330' => 'Некорректный номер карты.',
	'globalcollect_gateway-response-430421' => 'Ваша кредитная карта не прошла проверку. Пожалуйста, проверьте, что вся введённая вами информация соответствует данным вашей карты, или попробуйте использовать другую карту.',
	'globalcollect_gateway-response-430360' => 'Транзакция не может быть авторизована. Пожалуйста, попробуйте использовать другую карту или выберите другой способ платежа.',
	'globalcollect_gateway-response-430285' => 'Транзакция не может быть авторизована. Пожалуйста, попробуйте использовать другую карту или выберите другой способ платежа.',
	'globalcollect_gateway-response-21000150' => 'Неправильный номер банковского счёта.',
	'globalcollect_gateway-response-21000155' => 'Неправильный код банка.',
	'globalcollect_gateway-response-21000160' => 'Неправильный номер счёта giro.',
	'globalcollect_gateway-response-default' => 'При обработке вашей транзакции возникла ошибка.
Пожалуйста, повторите попытку позже.',
);

/** Sinhala (සිංහල)
 * @author පසිඳු කාවින්ද
 */
$messages['si'] = array(
	'globalcollectgateway' => 'ඔබගේ පරිත්‍යාගය දැන් සපයන්න',
	'globalcollect_gateway-desc' => 'GlobalCollect ගෙවීම ක්‍රියානනය වෙමින්',
	'globalcollect_gateway-response-9130' => 'වලංගු නොවන රටකි.',
	'globalcollect_gateway-response-9140' => 'වලංගු නොවන ව්‍යවහාරයකි.',
	'globalcollect_gateway-response-9150' => 'වලංගු නොවන භාෂාවකි.',
	'globalcollect_gateway-response-400530' => 'වලංගු නොවන ගෙවීම් ක්‍රමය.',
	'globalcollect_gateway-response-430306' => 'ඔබේ ණයපත ඉකුත් වී ඇත. කරුණාකර වෙනත් කාඩ් පතක් භාවිතා කිරීම හෝ වෙනත් ගෙවීම් ක්‍රමයක් අනුගමනය කිරීම සිදු කරන්න.',
	'globalcollect_gateway-response-430330' => 'වලංගු නොවන කාඩ්පත් අංකය.',
	'globalcollect_gateway-response-21000150' => 'වලංගු නොවන බැංකු ගිණුම් අංකය.',
	'globalcollect_gateway-response-21000155' => 'වලංගු නොවන බැංකු කේතය.',
	'globalcollect_gateway-response-21000160' => 'වලංගු නොවන ගයිරෝ ගිණුම් අංකය.',
);

/** Slovenian (slovenščina)
 * @author Artelind
 * @author Dbc334
 */
$messages['sl'] = array(
	'globalcollectgateway' => 'Oddajte svoj prispevek zdaj',
	'globalcollect_gateway-desc' => 'Plačilo GlobalCollect je v obdelavi',
	'globalcollect_gateway-fakesucceed' => 'Potvori uspešno plačilo',
	'globalcollect_gateway-fakefail' => 'Potvori spodletelo plačilo',
	'globalcollect_gateway-response-9130' => 'Neveljavna država.',
	'globalcollect_gateway-response-9140' => 'Neveljavna valuta.',
	'globalcollect_gateway-response-9150' => 'Neveljaven jezik.',
	'globalcollect_gateway-response-400530' => 'Neveljaven način plačila.',
	'globalcollect_gateway-response-430306' => 'Vaša kreditna kartica je potekla. Prosimo, poskusite z drugo kartico ali pa uporabite katerega od naših drugih načinov plačila.',
	'globalcollect_gateway-response-430330' => 'Številka kartice ni veljavna.',
	'globalcollect_gateway-response-430421' => 'Vaše kreditne kartice ni bilo mogoče potrditi. Prosimo, preverite, da so podatki o vaši kreditni kartici pravilni, ali pa poskusite z drugo kartico.',
	'globalcollect_gateway-response-430360' => 'Pri potrjevanju transakcije je prišlo do napake. Prosimo, poskusite z drugo kartico ali pa uporabite katerega od naših drugih načinov plačila.',
	'globalcollect_gateway-response-430285' => 'Pri potrjevanju transakcije je prišlo do napake. Prosimo, poskusite z drugo kartico ali pa uporabite katerega od naših drugih načinov plačila.',
	'globalcollect_gateway-response-21000150' => 'Številka bančnega računa je napačna.',
	'globalcollect_gateway-response-21000155' => 'Številka banke je napačna.',
	'globalcollect_gateway-response-21000160' => 'Številka žiroračuna je napačna.',
	'globalcollect_gateway-response-default' => 'Pri obdelavi vaše transakcije je prišlo do napake. Prosimo, poskusite pozneje.',
);

/** Serbian (Cyrillic script) (српски (ћирилица)‎)
 * @author Rancher
 */
$messages['sr-ec'] = array(
	'globalcollect_gateway-response-21000150' => 'Неисправан број рачуна.',
	'globalcollect_gateway-response-21000155' => 'Неисправан банковни кôд.',
);

/** Swedish (svenska)
 * @author Jopparn
 * @author Warrakkk
 */
$messages['sv'] = array(
	'globalcollectgateway' => 'Ge ditt bidrag nu',
	'globalcollect_gateway-desc' => 'GlobalCollect betalningshantering',
	'globalcollect_gateway-fakesucceed' => 'Fejka en genomförd betalning',
	'globalcollect_gateway-fakefail' => 'Fejka en misslyckad betalning',
	'globalcollect_gateway-response-9130' => 'Ogiltig land.',
	'globalcollect_gateway-response-9140' => 'Ogiltig valuta.',
	'globalcollect_gateway-response-9150' => 'Ogiltigt språk.',
	'globalcollect_gateway-response-400530' => 'Ogiltig betalningsmetod.',
	'globalcollect_gateway-response-430306' => 'Ditt kreditkort har slutat gälla. Prova ett annat kort eller något av våra andra betalningsalternativ.',
	'globalcollect_gateway-response-430330' => 'Ogiltig kortnummer.',
	'globalcollect_gateway-response-430421' => 'Ditt kreditkort kunde inte verifieras. Kontrollera att alla uppgifter stämmer överens med kreditkortsprofilen eller prova ett annat kort.',
	'globalcollect_gateway-response-430360' => 'Transaktionen kan inte godkännas. Prova ett annat kort eller något av våra andra betalningsalternativ.',
	'globalcollect_gateway-response-430285' => 'Transaktionen kan inte godkännas. Prova ett annat kort eller något av våra andra betalningsalternativ.',
	'globalcollect_gateway-response-21000150' => 'Ogiltig bankkontonummer.',
	'globalcollect_gateway-response-21000155' => 'Ogiltig bankkod.',
	'globalcollect_gateway-response-21000160' => 'Ogiltigt girokontonummer.',
	'globalcollect_gateway-response-default' => 'Ett fel uppstod när din transaktion behandlades.
Försök igen senare.',
);

/** Swahili (Kiswahili)
 * @author Lloffiwr
 */
$messages['sw'] = array(
	'globalcollectgateway' => 'Changia sasa',
	'globalcollect_gateway-response-9130' => 'Jina batili la nchi.',
	'globalcollect_gateway-response-9140' => 'Aina batili ya fedha', # Fuzzy
	'globalcollect_gateway-response-9150' => 'Lugha batili.',
	'globalcollect_gateway-response-400530' => 'Njia batili ya kulipa', # Fuzzy
	'globalcollect_gateway-response-430306' => 'Kadi yako ya mkopo imeisha. Tafadhali jaribu kadi nyingine ama njia nyingine ya kulipa inayowezekana.', # Fuzzy
	'globalcollect_gateway-response-430330' => 'Namba batili ya kadi.', # Fuzzy
	'globalcollect_gateway-response-21000150' => 'Namba batili ya akaunti ya benki', # Fuzzy
	'globalcollect_gateway-response-21000155' => 'Msimbo batili wa benki.',
	'globalcollect_gateway-response-default' => 'Ilitokea hitilafu wakati wa kufanya malipo yako.
Tafadhali jaribu tena baadaye.',
);

/** Tamil (தமிழ்)
 * @author Karthi.dr
 */
$messages['ta'] = array(
	'globalcollectgateway' => 'உங்கள் நன்கொடையை இப்போது அளிக்கவும்',
	'globalcollect_gateway-response-9130' => 'செல்லாத நாடு.',
	'globalcollect_gateway-response-9140' => 'செல்லாத நாணயமுறை',
	'globalcollect_gateway-response-9150' => 'செல்லாத மொழி.',
	'globalcollect_gateway-response-400530' => 'செல்லாத செலுத்துதல்  முறை.',
	'globalcollect_gateway-response-430306' => 'உங்கள் கடனட்டை காலாவதியாகி விட்டது. அருள்கூர்ந்து வேறு  அட்டையைப் பயன்படுத்தவும் அல்லது பணம் செலுத்தும் பிற வழிகளை முயற்சிக்கவும்.',
	'globalcollect_gateway-response-430330' => 'செல்லாத அட்டை எண்.',
	'globalcollect_gateway-response-21000150' => 'செல்லாத வங்கிக் கணக்கு எண்.',
	'globalcollect_gateway-response-21000155' => 'செல்லாத வங்கிக் குறியீடு.',
	'globalcollect_gateway-response-default' => 'தங்கள் பரிமாற்றத்தைச் செயல்படுத்துவதில் ஒரு பிழை ஏற்பட்டது.
அருள்கூர்ந்து மீண்டும் முயற்சிக்கவும்.',
);

/** Telugu (తెలుగు)
 * @author Veeven
 */
$messages['te'] = array(
	'globalcollect_gateway-response-9130' => 'చెల్లని దేశం.',
	'globalcollect_gateway-response-9150' => 'చెల్లని భాష.',
	'globalcollect_gateway-response-400530' => 'చెల్లింపు పద్ధతి చెల్లదు.',
	'globalcollect_gateway-response-21000150' => 'బ్యాంకు ఖాతా నెంబరు చెల్లదు.',
	'globalcollect_gateway-response-21000155' => 'బ్యాంకు సంకేతం చెల్లదు.',
);

/** Tagalog (Tagalog)
 * @author AnakngAraw
 * @author Sky Harbor
 */
$messages['tl'] = array(
	'globalcollectgateway' => 'Magkaloob ka na ngayon',
	'globalcollect_gateway-desc' => 'Pagsasagawa ng pagbabayad gamit ang GlobalCollect',
	'globalcollect_gateway-response-9130' => 'Hindi katanggap-tanggap na bansa.',
	'globalcollect_gateway-response-9140' => 'Hindi katanggap-tanggap na salaping umiiral.',
	'globalcollect_gateway-response-9150' => 'Hindi katanggap-tanggap na wika.',
	'globalcollect_gateway-response-400530' => 'Hindi katanggap-tanggap na paraan ng pagbabayad.',
	'globalcollect_gateway-response-430306' => 'Wala nang saysay ang iyong tarhetang pang-utang. Sumubok po ng ibang tarheta o isa sa ibang mga paraan ng pagbabayad sa amin.',
	'globalcollect_gateway-response-430330' => 'Hindi katanggap-tanggap na bilang ng tarheta',
	'globalcollect_gateway-response-430421' => 'Hindi mapatunayan ang iyong tarhetang pang-utang. Paki tiyak na ang lahat ng mga impormasyon ay tumutugma sa iyong balangkas ng katangian sa kard na pang-utang, o sumubok ng ibang tarheta.',
	'globalcollect_gateway-response-430360' => 'Hindi mapapahintulutan ang transaksiyon. Paki subukin ang isang ibang kard o isa sa aming iba pang mga paraan ng pagbabayad.',
	'globalcollect_gateway-response-430285' => 'Hindi mapapahintulutan ang transaksiyon. Paki subukin ang isang ibang tarheta o isa sa aming iba pang mga paraan ng pagbabayad.',
	'globalcollect_gateway-response-21000150' => 'Hindi katanggap-tanggap na bilang sa kuwentang bangko.',
	'globalcollect_gateway-response-21000155' => 'Hindi katanggap-tanggap na kodigo ng bangko.',
	'globalcollect_gateway-response-21000160' => 'Hindi katanggap-tanggap na bilang sa kuwentang Giro.',
	'globalcollect_gateway-response-default' => 'Nagkaroon ng kamalian sa pagsasagawa ng transaksiyon mo.
Mangyaring subukan muli mamaya.',
);

/** Turkish (Türkçe)
 * @author Emperyan
 */
$messages['tr'] = array(
	'globalcollectgateway' => 'Bağışınızı şimdi yapın',
	'globalcollect_gateway-desc' => 'GlobalCollect ödeme işlemleri',
);

/** Ukrainian (українська)
 * @author Base
 * @author Ата
 */
$messages['uk'] = array(
	'globalcollectgateway' => 'Зробіть Вашу пожертву зараз',
	'globalcollect_gateway-desc' => 'Шлюз обробки платежів GlobalCollect',
	'globalcollect_gateway-fakesucceed' => 'Нібито успішний платіж',
	'globalcollect_gateway-fakefail' => 'Нібито неуспішний платіж',
	'globalcollect_gateway-response-9130' => 'Недопустима країна.',
	'globalcollect_gateway-response-9140' => 'Недопустима валюта.',
	'globalcollect_gateway-response-9150' => 'Недопустима мова.',
	'globalcollect_gateway-response-400530' => 'Недопустимий метод оплати.',
	'globalcollect_gateway-response-430306' => 'Сплив термін дії Вашої кредитної картки. Будь ласка, спробуйте використати іншу кредитну картку, або інші методи оплати.',
	'globalcollect_gateway-response-430330' => 'Недопустимий номер картки.',
	'globalcollect_gateway-response-430421' => 'Ваша кредитна картка не пройшла перевірку. Будь ласка, перевірте чи вся введена інформація збігається із даними Вашої кредитної картки, або пробуйте використати іншу картку.',
	'globalcollect_gateway-response-430360' => 'Транзакція не може бути авторизована. Будь ласка, спробуйте використати іншу картку або оберіть інший спосіб платежу.',
	'globalcollect_gateway-response-430285' => 'Транзакція не може бути авторизована. Будь ласка, спробуйте використати іншу картку або оберіть інший спосіб платежу.',
	'globalcollect_gateway-response-21000150' => 'Неправильний номер банківського рахунку',
	'globalcollect_gateway-response-21000155' => 'Неправильний код банку.',
	'globalcollect_gateway-response-21000160' => 'Неправильний номер рахунку giro.',
	'globalcollect_gateway-response-default' => 'Сталася помилка при обробці Вашої транзакції.
Будь ласка, спробуйте знову пізніше.',
);

/** Urdu (اردو)
 * @author පසිඳු කාවින්ද
 */
$messages['ur'] = array(
	'globalcollectgateway' => 'اب آپ عطیہ کر',
	'globalcollect_gateway-response-9130' => 'باطل ملک.',
	'globalcollect_gateway-response-9140' => 'باطل کرنسی.',
	'globalcollect_gateway-response-9150' => 'باطل زبان ہے.',
	'globalcollect_gateway-response-400530' => 'باطل کی ادائیگی کا طریقہ.',
	'globalcollect_gateway-response-430330' => 'باطل کارڈ کی تعداد ۔',
	'globalcollect_gateway-response-21000150' => 'باطل بینک اکاؤنٹ کی تعداد ۔',
	'globalcollect_gateway-response-21000155' => 'باطل بینک کوڈ.',
);

/** Vietnamese (Tiếng Việt)
 * @author Minh Nguyen
 * @author Trần Nguyễn Minh Huy
 * @author Tuankiet65
 */
$messages['vi'] = array(
	'globalcollectgateway' => 'Quyên góp ngay bây giờ',
	'globalcollect_gateway-desc' => 'Xử lý thanh toán qua GlobalCollect',
	'globalcollect_gateway-fakesucceed' => 'Làm giả một thanh toán thành công',
	'globalcollect_gateway-fakefail' => 'Làm giả một thanh toán thất bại',
	'globalcollect_gateway-response-9130' => 'Quốc gia không hợp lệ.',
	'globalcollect_gateway-response-9140' => 'Loại tiền tệ không hợp lệ.',
	'globalcollect_gateway-response-9150' => 'Ngôn ngữ không hợp lệ.',
	'globalcollect_gateway-response-400530' => 'Phương thức thanh toán không hợp lệ.',
	'globalcollect_gateway-response-430306' => 'Thẻ tín dụng của bạn đã hết hạn. Hãy thử dùng một thẻ khác hoặc một trong các phương thức thanh toán khác của chúng tôi.',
	'globalcollect_gateway-response-430330' => 'Mã số thẻ không hợp lệ.',
	'globalcollect_gateway-response-430421' => 'Thẻ tín dụng của bạn không thể xác nhận. Xin vui lòng kiểm chứng rằng tất cả thông tin phù hợp với hồ sơ thẻ tín dụng của bạn hoặc thử dùng một thẻ khác.',
	'globalcollect_gateway-response-430360' => 'Giao dịch này không cho phép. Hãy thử dùng một thẻ khác hoặc một trong các phương thức thanh toán khác của chúng tôi.',
	'globalcollect_gateway-response-430285' => 'Giao dịch này không cho phép. Hãy thử dùng một thẻ khác hoặc một trong các phương thức thanh toán khác của chúng tôi.',
	'globalcollect_gateway-response-21000150' => 'Số tài khoản ngân hàng không hợp lệ.',
	'globalcollect_gateway-response-21000155' => 'Mã ngân hàng không hợp lệ.',
	'globalcollect_gateway-response-21000160' => 'Số tài khoản giro không hợp lệ.',
	'globalcollect_gateway-response-default' => 'Đã xảy ra lỗi khi xử lý giao dịch của bạn.
Xin hãy thử lại sau.',
);

/** Simplified Chinese (中文（简体）‎)
 * @author Yfdyh000
 */
$messages['zh-hans'] = array(
	'globalcollectgateway' => '马上捐款',
	'globalcollect_gateway-desc' => 'GlobalCollect 支付程序',
	'globalcollect_gateway-fakesucceed' => '测试一次成功付款',
	'globalcollect_gateway-fakefail' => '测试一次付款失败',
	'globalcollect_gateway-response-9130' => '无效的国家。',
	'globalcollect_gateway-response-9140' => '无效的货币。',
	'globalcollect_gateway-response-9150' => '无效的语言。',
	'globalcollect_gateway-response-400530' => '无效的支付方法。',
	'globalcollect_gateway-response-430306' => '您的信用卡已过期。请尝试使用其他卡或我们的其他支付方式。',
	'globalcollect_gateway-response-430330' => '卡号无效。',
	'globalcollect_gateway-response-430421' => '无法验证您的信用卡。请验证所有信息都匹配您的信用卡资料，或尝试使用其他卡。',
	'globalcollect_gateway-response-430360' => '业务无法授权。请尝试使用其他卡或我们的其他支付方式。',
	'globalcollect_gateway-response-430285' => '业务无法授权。请尝试使用其他卡或我们的其他支付方式。',
	'globalcollect_gateway-response-21000150' => '无效的银行帐号。',
	'globalcollect_gateway-response-21000155' => '无效的银行代码。',
	'globalcollect_gateway-response-21000160' => '无效的转帐帐户号码。',
	'globalcollect_gateway-response-default' => '处理您的交易过程中出错。
请稍后重试。',
);

/** Traditional Chinese (中文（繁體）‎)
 * @author Shirayuki
 * @author Simon Shek
 */
$messages['zh-hant'] = array(
	'globalcollectgateway' => '馬上捐款',
	'globalcollect_gateway-desc' => 'GlobalCollect付款處理',
	'globalcollect_gateway-fakesucceed' => '測試一次付款成功',
	'globalcollect_gateway-fakefail' => '測試一次付款失敗',
	'globalcollect_gateway-response-9130' => '無效的國家。',
	'globalcollect_gateway-response-9140' => '無效的貨幣。',
	'globalcollect_gateway-response-9150' => '無效的語言。',
	'globalcollect_gateway-response-400530' => '付款方法有誤。',
	'globalcollect_gateway-response-430306' => '您的信用卡已過期。請嘗試使用其他卡或我們的其他付款方式。',
	'globalcollect_gateway-response-430330' => '卡號不正確。',
	'globalcollect_gateway-response-430421' => '無法確認您的信用卡。請檢察所有信息與信用卡上的資料匹配，或嘗試使用其他卡。',
	'globalcollect_gateway-response-430360' => '本次交易無法獲得授權。請嘗試使用其他卡或我們的其他付款方式。',
	'globalcollect_gateway-response-430285' => '本次交易無法獲得授權。請嘗試使用其他卡或我們的其他付款方式。',
	'globalcollect_gateway-response-21000150' => '銀行帳戶號碼不正確。',
	'globalcollect_gateway-response-21000155' => '銀行代碼不正確。',
	'globalcollect_gateway-response-21000160' => '轉帳帳戶編號不正確。',
	'globalcollect_gateway-response-default' => '處理交易的過程中出錯。
請稍後再試。',
);
