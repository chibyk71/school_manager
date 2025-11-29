<template>
    <tr v-for="n in 5" :key="n">
        <td colspan="100" class="py-4 px-6">
            <Skeleton height="2rem" />
        </td>
    </tr>
</template>

<script setup lang="ts"> 
</script>