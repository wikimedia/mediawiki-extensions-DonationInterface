<template>
	<div class="wikimedia-landing-container">
		<main class="content-grid">
			<section class="lang-section" lang="ru">
				<p>
					Благодарим вас за ваш интерес к поддержке Фонда Викимедиа. К сожалению, в настоящий момент мы не можем принимать пожертвования из России. Мы ценим вашу поддержку, ибо наша цель – нести знания в каждый уголок мира.
				</p>

				<p>
					Но сам факт, что вы не можете внести финансовый вклад, не означает невозможности участия в нашей миссии, которая заключается в предоставлении свободного доступа ко всем накопленным знаниям каждому:
				</p>

				<ul>
					<li>
						У вас есть возможность редактирования и написания статей в Википедии - не только на русском языке, но и на любом другом языке, которым вы владеете. Вы можете узнать подробно о создании учетной записи на сайте Википедии <a :href="createAccount">здесь</a>. Для получения более подробной информации ознакомьтесь со страницей «<a :href="introductionRu">Введение в Википедию</a>».
					</li>
					<li>
						Вы можете добавлять фотографии и другие материалы на <a :href="commons">Викисклад</a> - место, где мы размещаем все изображения и другие медиафайлы, которые появляются в Википедии.
					</li>
					<li>
						Конкурсы фотографии, такие как «Вики любит Землю» и «Вики любит памятники», привлекают участников со всего мира. В последние годы российские энтузиасты Википедии предоставили много удостоенных наград фотографий, таких как <a :href="contests.wle2020">эта</a> из конкурса «Вики любит Землю - 2020» или <a :href="contests.wlm2018">эта</a> из конкурса «Вики любит памятники - 2018».
					</li>
					<li>
						Если у вас остались какие-либо дополнительные вопросы касательно Википедии или ее редактирования, пожалуйста, отправляйте их на адрес <a :href="`mailto:${contactEmail}`">{{ contactEmail }}</a>.
					</li>
				</ul>

				<p>
					Мы благодарим вас за ваше стремление и любовь к познанию от имени всех сотрудников Фонда Викимедиа, которые верят в то, что знания должны распространяться без границ.
				</p>

				<p class="sign-off">
					Фонд Викимедиа
				</p>
			</section>

			<section class="lang-section" lang="en">
				<p>
					Thank you for your interest in supporting the Wikimedia Foundation. Unfortunately, it is not possible for us to accept donations from Russia at this time. We appreciate your support, because our goal is to bring knowledge to every corner of the globe.
				</p>

				<p>
					But you are still able to be involved in our mission to make the sum of knowledge free for everyone:
				</p>

				<ul>
					<li>
						You have the option of editing and writing articles on Wikipedia--not just in Russian, but in any language you speak. You can learn more about creating a Wikipedia account <a :href="createAccount">here</a>. For more information, please see the <a :href="introductionEn">introduction to Wikipedia</a> page.
					</li>
					<li>
						You can submit photos and other media to <a :href="commons">Wikimedia Commons</a>, where we host all of the images and other media files which appear on Wikipedia.
					</li>
					<li>
						Russian residents can take part in numerous all-Russia or international <a :href="contests.main">photo and article writing contests</a>. For example, in recent years, Russian Wikipedians have submitted many award-winning images like <a :href="contests.wsc2019">this one</a> from the 2019 Wiki Science competition, <a :href="contests.wle2020">this one</a> from 2020 Wiki Loves Earth, or <a :href="contests.wlm2018">this one</a> from 2018 Wiki Loves Monuments.
					</li>
					<li>
						If you have any further questions regarding Wikipedia or editing, please contact <a :href="`mailto:${contactEmail}`">{{ contactEmail }}</a>
					</li>
				</ul>

				<p>
					From everyone here at the Wikimedia Foundation who believes that knowledge should transcend borders, we thank you for your time, effort, and love of learning.
				</p>

				<p class="sign-off">
					The Wikimedia Foundation
				</p>
			</section>
		</main>
	</div>
</template>

<script>
const { defineComponent } = require( 'vue' );

module.exports = exports = defineComponent( {
	name: 'DefaultForbiddenNotice',

	data() {
		return {
			createAccount: 'https://ru.wikipedia.org/w/index.php?title=%D0%A1%D0%BB%D1%83%D0%B6%D0%B5%D0%B1%D0%BD%D0%B0%D1%8F:%D0%A1%D0%BE%D0%B7%D0%B4%D0%B0%D1%8C%D0%A3%D1%87%D0%B5%D1%82%D0%BD%D1%83%D1%8E%D0%97%D0%B0%D0%BF%D0%B8%D1%81%D1%8C',
			introductionRu: 'https://ru.wikipedia.org/wiki/%D0%92%D0%B8%D0%BA%D0%B8%D0%BF%D0%B5%D0%B4%D0%B8%D1%8F:%D0%92%D0%B2%D0%B5%D0%B4%D0%B5%D0%BD%D0%B8%D0%B5',
			introductionEn: 'https://en.wikipedia.org/wiki/Help:Introduction',
			commons: 'https://commons.wikimedia.org/',
			contactEmail: 'info-ru@wikimedia.org',
			contests: {
				main: 'https://ru.wikimedia.org/wiki/%D0%9A%D0%BE%D0%BD%D0%BA%D1%83%D1%80%D1%81%D1%8B',
				wsc2019: 'https://commons.wikimedia.org/wiki/File:Trientalis_europaea_comb_edit.jpg',
				wle2020: 'https://commons.wikimedia.org/wiki/File:Sayano-Shushenski_Nature_Reserve_13.jpg',
				wlm2018: 'https://commons.wikimedia.org/wiki/File:Khor_Anik_lighthouse_under_starry_sky.jpg'
			}
		};
	}
} );
</script>
