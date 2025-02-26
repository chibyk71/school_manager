<script setup lang="ts">

</script>

<template>
    <div class="grid grid-cols-12 gap-4 divide-x-2 border-t">
        <div class="xxl:col-span-2 xl:col-span-3 col-span-12">
            <slot name="left"></slot>
        </div>
        <div class="xxl:col-span-10 xl:col-span-9 col-span-12">
            <slot name="main"></slot>
        </div>
    </div>
</template>
