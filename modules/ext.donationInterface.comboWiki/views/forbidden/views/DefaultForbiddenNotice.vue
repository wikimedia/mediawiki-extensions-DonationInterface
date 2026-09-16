<template>
	<div class="wikimedia-landing-container">
		<main class="content">
			<h2>Thank you for your interest</h2>

			<p>
				Thank you for your interest in supporting the Wikimedia Foundation. We are not fundraising in your country at this time, but monetary contributions are not your only option for donating to and supporting Wikipedia.
			</p>

			<p>
				Wikipedia is a "wiki", which means that everyone can edit pages. You don't need to apply or get special permission to join us. At the top of each page is an "edit" label. Try it, for example, at
				<a :href="sandbox">the Wikipedia sandbox</a>. You don't even need to log in to edit, although
				<a :href="createAccount">creating an account</a> gives you more options and helps you keep track of your contributions. There is no central editorial board; all edits are made by individual members of the Wikipedia community. For more information, please see the
				<a :href="introduction">introduction</a>.
			</p>

			<p>
				If you have any further questions regarding Wikipedia or editing, please contact
				<a :href="`mailto:${contactEmail}`">{{ contactEmail }}</a>. This email address is answered by longtime project participants who have vast experience in Wikipedia-related issues.
			</p>

			<p>Thanks again for your interest, and we hope you join us!</p>
		</main>
	</div>
</template>

<script>
const { defineComponent } = require( 'vue' );

module.exports = exports = defineComponent( {
	name: 'DefaultForbiddenNotice',

	data() {
		return {
			createAccount: 'https://foundation.wikimedia.org/wiki/Privacy_policy',
			sandbox: 'https://en.wikipedia.org/wiki/Wikipedia:Sandbox',
			introduction: 'https://en.wikipedia.org/wiki/Wikipedia:Introduction',
			contactEmail: 'info@wikimedia.org'
		};
	}
} );
</script>
