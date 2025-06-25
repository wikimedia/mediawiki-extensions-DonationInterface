<template>
    <div class="contact-details">
			<p>{{ $i18n("donorportal-contact-details").text() }}</p>
			<table>
				<tr>
					<td>{{ name }}</td>
					<td>{{ $i18n("donorportal-donorid", id).text() }}</td>
				</tr>
				<tr>
					<td>{{ address.street_address }}</td>
					<td></td>
				</tr>
				<tr>
					<td>{{ address.city }}</td>
					<td></td>
				</tr>
				<tr>
					<td>{{ address.state_province }}</td>
					<td>{{ email }}</td>
				</tr>
				<tr>
					<td>{{ address.postal_code }}</td>
					<td></td>
				</tr>
				<tr>
					<td>{{ address.country }}</td>
					<td><a href="#" class="donorportal-update-prefs">{{ $i18n("donorportal-update-preferences").text() }}</a>
					</td>
				</tr>
			</table>
		</div>
</template>
<script>
module.exports = exports = {
    props: {
        name: {
            type: String,
            required: true
        },
        id: {
            type: String,
            required: true
        },
        email: {
            type: String,
            required: true
        },
        address: {
            type: Object,
        }
    }
}
</script>