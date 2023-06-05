<script>
    export let message = undefined;
    let className;
    export { className as class };
</script>

{#if message}
    <p class="text-sm text-red-600 dark:text-red-400 {className}">
        {message}
    </p>
{/if}
