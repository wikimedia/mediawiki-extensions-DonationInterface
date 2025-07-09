<template>
    <div class="greeting">
        <h2>{{ $i18n("donorportal-greeting", name).text() }}</h2>
        <p><strong>{{ $i18n("donorportal-boldtext").text() }}</strong></p>
        <p>{{ $i18n("donorportal-smalltext").text() }}</p>
    </div>
</template>
<script>
module.exports = exports = {
    props: {
        name: {
            type: String,
            required: false
        }
    }
}
</script>