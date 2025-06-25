<template>
	<footer>
		<div class="footer-legal mw-620">
			<p id="donor-portal-learn-more" v-html="footerLearnMore"></p>
			<p id="donor-portal-questions" v-html="footerQuestions"></p>
			<p id="donor-portal-license" v-html="footerTextLicense"></p>
		</div>
	</footer>

</template>

<script>
module.exports = exports = {
	data() {
		return {
			footerLearnMore: this.$i18n("emailpreferences-footer-learn_more").text(),
			footerQuestions: this.$i18n("emailpreferences-footer-questions_email").text(),
			footerTextLicense: this.$i18n("emailpreferences-footer-text_license").text()
		}
	}
}
</script>
