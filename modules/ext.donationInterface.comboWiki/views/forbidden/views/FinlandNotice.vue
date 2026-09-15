<template>
	<div class="wikimedia-landing-container">
		<main class="content-grid">
			<section class="lang-section" lang="en">
				<h2>Thank you for your interest</h2>

				<p>
					Thank you for your interest in supporting the Wikimedia Foundation.
					Unfortunately this web page cannot include information on how to provide
					funding to Wikimedia due to the current authority interpretation of the
					Finnish money collection legislation. For more information, please refer
					to the statements by the Wikimedia Foundation and the Finnish National
					Police Board.
					<span>
						[<a :href="statements.wmf" target="_blank">1</a>]
						[<a :href="statements.police" target="_blank">2</a>]
						[<a :href="statements.policeAlt" target="_blank">3</a>]
						(in Finnish)
					</span>
				</p>

				<p>
					Please know that monetary contributions are not your only option for
					supporting Wikipedia.
				</p>

				<p>
					Wikipedia is a "wiki", which means that everyone can edit pages. You don't
					need to apply or get special permission to join us. At the top of each
					page is an "edit" label. Try it, for example, at
					<a :href="sandbox">the Wikipedia sandbox</a>. You don't even need to log in to edit, although
					<a :href="createAccount">creating an account</a> gives you more options and helps you keep track of your contributions. There is no central editorial board; all edits are made by individual members of the Wikipedia community. For more information, please see the
					<a :href="introduction">introduction</a> and
					<a :href="tutorial">tutorial</a>.
				</p>

				<p>
					If you have any further questions regarding Wikipedia or editing, please
					contact <a :href="`mailto:${contactEmail}`">{{ contactEmail }}</a>. This
					email address is answered by longtime project participants who have vast
					experience in Wikipedia-related issues.
				</p>

				<p class="sign-off">
					Thanks again for your interest, and we hope you join us!
				</p>
			</section>

			<section class="lang-section" lang="fi">
				<h2>Kiitos mielenkiinnostasi</h2>

				<p>
					Kiitos mielenkiinnostasi Wikimedia Foundationin tukemista kohtaan.
				</p>

				<p>
					Valitettavasti rahankeräyslain nykyisen viranomaistulkinnan johdosta tämä
					sivu ei voi sisältää tietoa siitä, miten Wikimedian toimintaa voi rahoittaa.
					Tutustu Wikimedia Foundationin ja Suomen poliisihallituksen asiaa koskeviin
					lausuntoihin.
					<span>
						[<a :href="statements.wmf" target="_blank">4</a>]
						[<a :href="statements.police" target="_blank">5</a>]
						[<a :href="statements.policeAlt" target="_blank">6</a>]
					</span>
				</p>

				<p>
					Raha ei kuitenkaan ole ainoa tapa tukea Wikipedian toimintaa.
				</p>

				<p>
					Wikipedia on ”wiki”, mikä tarkoittaa, että kaikki voivat muokata sen sivuja.
					Päästäksesi mukaan sinun ei tarvitse täyttää hakemusta tai saada meiltä
					lupaa. Jokaisen sivun yläreunassa on ”muokkaa”-painike. Kokeile sitä
					esimerkiksi <a :href="sandboxFi">Wikipedian Hiekkalaatikossa</a>.
					Muokkauksia tehdäksesi sinun ei tarvitse edes rekisteröityä, vaikkakin
					<a :href="createAccountFi">tilin luomalla</a> saat käyttöösi enemmän vaihtoehtoja, ja se auttaa sinua pitämään kirjaa omista kirjoituksistasi. Keskitettyä toimituskuntaa ei ole; Wikipedia-yhteisön yksittäiset jäsenet tekevät kaikki muokkaukset. Lisätietoja löydät
					<a :href="introductionFi">Tervetuloa Wikipediaan -osiosta</a>.
				</p>

				<p>
					Wikipediaan tai sen muokkaamiseen liittyvät kysymykset voit lähettää
					osoitteeseen <a :href="`mailto:${contactEmail}`">{{ contactEmail }}</a>.
					Viesteihisi vastaavat pitkäaikaiset projektiin osallistujat, joilla on paljon
					kokemusta Wikipediaan liittyvistä kysymyksistä.
				</p>

				<p class="sign-off">
					Kiitos vielä kerran mielenkiinnostasi, toivottavasti liityt joukkoomme!
				</p>
			</section>
		</main>
	</div>
</template>

<script>
const { defineComponent } = require( 'vue' );

module.exports = exports = defineComponent( {
	name: 'FinlandNotice',

	data() {
		return {
			createAccount: 'https://en.wikipedia.org/w/index.php?title=Special:CreateAccount',
			sandbox: 'https://en.wikipedia.org/wiki/Wikipedia:Sandbox',
			introduction: 'https://en.wikipedia.org/wiki/Help:Introduction',
			tutorial: 'https://en.wikipedia.org/wiki/Help:Tutorial',
			createAccountFi: 'https://fi.wikipedia.org/w/index.php?title=Toiminnot:Luo_tunnus',
			sandboxFi: 'https://fi.wikipedia.org/wiki/Wikipedia:Hiekkalaatikko',
			introductionFi: 'https://fi.wikipedia.org/wiki/Ohje:Tervetuloa_Wikipediaan',
			contactEmail: 'info@wikimedia.org',
			statements: {
				wmf: 'https://wikimediafoundation.org/',
				police: 'https://poliisi.fi/',
				policeAlt: 'https://poliisi.fi/'
			}
		};
	}
} );
</script>
