<template>
	<header-component></header-component>
	<router-view></router-view>
	<footer-component></footer-component>
</template>

<script>
const HeaderComponent = require('./Header.vue')
const Footer = require('./Footer.vue')
const { RouterView } = require('vue-router')

module.exports = exports = {
	name: 'DonorPortal',

	components: {
		'header-component': HeaderComponent,
		'footer-component': Footer,
		'router-view': RouterView
	},

	computed: {
	}
};

</script>
