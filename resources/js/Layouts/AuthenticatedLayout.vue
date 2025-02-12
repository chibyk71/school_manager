<script setup lang="ts">
    import { ref } from 'vue';
    import Navbar from '@/Components/menu/Navbar.vue';
    import PageHeader from '@/Components/menu/PageHeader.vue';
    import SidePanel from '@/Components/menu/SidePanel.vue';
</script>

<template>
    <Navbar />
    <SidePanel />
    <!-- Page Wrapper -->
    <div class="page-wrapper">
        <div class="content blank-page">
            <PageHeader />
        </div>
    </div>
</template>
