<template>
	<!-- Clickable Link -->
	<a
		href="#"
		class="link"
		@click.prevent="openModal">
		<slot name="link-text"></slot>
	</a>

	<!-- Popup Modal -->
	<div
		v-if="isModalOpen"
		class="popup-overlay"
		@click="closeModal">
		<div class="popup-modal">
			<!-- Render HTML content -->
			<slot name="popup-body"></slot>
			<div class="popup-footer">
				<button class="popup-close" @click="closeModal">
					{{ $i18n( 'donorportal-close' ).text() }}
				</button>
			</div>
		</div>
	</div>
</template>

<script>
const { defineComponent, ref } = require( 'vue' );

module.exports = exports = defineComponent( {
	name: 'PopupLink',
	setup() {
		const isModalOpen = ref( false );
		return {
			isModalOpen
		};
	},
	methods: {
		openModal() {
			this.isModalOpen = true;
		},
		closeModal() {
			this.isModalOpen = false;
		}
	}
} );
</script>
