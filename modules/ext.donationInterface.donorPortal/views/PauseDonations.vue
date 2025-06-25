<template>
	<div>
		<p>Pause</p>
	</div>
</template>

<script>
module.exports = exports = {
	name: 'PauseDonationsView',


	mounted: () => {
		let params = new URLSearchParams( document.location.search );
		console.log('Pause',params.get('contribution_recur_id'))
	}
};
</script>
