<script lang="ts" setup>
import { isDarkTheme } from '@/store';
import { Link } from '@inertiajs/vue3';
import { Avatar, Button, IconField, Image, InputIcon, InputText, Menu, Menubar, OverlayBadge, Select } from 'primevue';
import { ref } from 'vue';

    let searchValue = ref("");
    let selectedAcademicSession = ref(2025);

    const toggleDarkMode = () => {
        isDarkTheme.value = !!document.querySelector('html')?.classList.toggle('dark');
    };
</script>

<template>
    <Menubar class="app-header">
        <!-- search input -->
        <template #button="slotProps">
            <IconField>
                <InputIcon class="pi pi-search" />
                <InputText class="!bg-transparent" v-model="searchValue" placeholder="Search" />
            </IconField>
        </template>
        <!-- Brand Logo -->
        <template #start>
            <Button variant="text" icon="pi pi-bars">
            </Button>
            <div class="brand">
                <Link :href="route('dashboard')" class="router-link-active router-link-exact-active brand-logo" aria-current="page">
                    <span class="brand-img">
                        <span class="brand-img-text text-theme">H</span>
                    </span>
                    <span class="brand-text">HUD ADMIN
                        <span class="text-xs/none font-bold absolute opacity-50 top-1.5 -ml-4">TM</span>
                    </span>
                </Link>
            </div>
        </template>

        <template #end>
            <div class="flex items-center gap-x-2">

                <!-- Select Academic session -->
                 <!-- TODO: make the options dynamic, may be by generating the options from the server -->
                <Select :options="[2021,2022,2023,2024,2025]" v-model="selectedAcademicSession" placeholder="Select Academic Session" class="bg-transparent">
                    <template #value="slotProps">
                        <div class="" v-if="slotProps.value">
                            <i class="pi pi-calendar-clock"></i>
                            Academic Year : {{ slotProps.value }}/{{ slotProps.value+1 }}
                        </div>
                        <span v-else>
                            {{ slotProps.placeholder }}
                        </span>
                    </template>
                    <template #option="slotProps">
                        <span>
                            {{ slotProps.option }}/{{ slotProps.option+1 }}
                        </span>
                    </template>
                </Select>

                <!-- Select Language -->
                 <!-- TODO: Make the selection effective, may be by generating the options from the server -->
                <div class="flex items-center">
                    <Button @click="(e) => $refs.language?.toggle(e)" variant="outlined">
                        <Image class="size-6" src="assets/img/flags/us.png" />
                    </Button>

                    <Menu :popup="true" ref="language" :model="[{name:'English', value:'us'},{name:'French', value:'fr'},{name:'Spanish', value:'es'},{name:'German', value:'de'}]" placeholder="Select Language">
                        <template #item="slotProps">
                            <div class="flex items-center py-1.5">
                                <Image class="size-6 mr-2" :src="`assets/img/flags/${ slotProps.item.value.toLowerCase() }.png`" />
                                <span class="font-semibold">{{ slotProps.item.name }}</span>
                            </div>
                        </template>
                    </Menu>
                </div>

                <!-- Create New Resource -->
                <!--  -->
                <div class="">
                    <Button icon="pi pi-plus" @click="(e)=> $refs.add?.toggle(e)" variant="outlined" severity="secondary" />
                    <Menu :pt="{list: {class:'grid grid-cols-2 gap-x-4'}}" :popup="true" ref="add" :model="[{name:'Add Student', value:'student'},{name:'Add Teacher', value:'teacher'},{name:'Add Staff', value:'staff'},{name:'Add Invoice', value:'invoice'}]" placeholder="Add New">
                        <template #start>
                            <div class="p-3 border-b">
                                <h5>Add New</h5>
                            </div>
                        </template>
                        <template class="grid grid-cols-2 gap-x-4" #item="slotProps">
                            <div class="">
                                <a v-ripple class="block bg-primary/10 rounded p-3 text-center mb-3">
                                    <div class="mb-2">
                                        <span class="inline-flex items-center justify-center w-12 h-12 bg-primary rounded-full"><i class="pi pi-building-columns"></i></span>
                                    </div>
                                    <p class="text-dark">{{ slotProps.item.name }}</p>
                                </a>
                            </div>
                        </template>
                    </Menu>
                </div>

                 <!-- Toggle Dark Mode -->
                <div class="">
                    <Button :icon="`pi pi-${ isDarkTheme ? 'sun' : 'moon' }`" @click="toggleDarkMode" variant="outlined" severity="secondary"></Button>
                </div>

                <!-- Notification -->
                <div class="">
                    <OverlayBadge severity="danger" class="">
                        <Button @click="(e) => $refs.notification?.toggle(e)" icon="pi pi-bell" class="" severity="secondary" variant="outlined" aria-label="Notification" />
                    </OverlayBadge>
                    <Menu ref="notification" :popup="true" :model="[{name:'Notification 1', value:'notification1'},{name:'Notification 2', value:'notification2'},{name:'Notification 3', value:'notification3'}]" placeholder="Notification" :pt="{list: {class:'px-2 h-72 overflow-y-auto'}}">

                        <template #start>
                            <div class="flex items-center justify-between border-b px-2 pb-3 mb-3">
                                <h4 class="notification-title">Notifications (2)</h4>
                                <Button variant="text" severity="primary" class="">Mark all as read</Button>
                            </div>
                        </template>
                        <template #item="slotProps">
                            <div class="border-bottom mb-3 pb-3">
                                <a href="activities.html">
                                    <div class="flex">
                                        <Avatar image="assets/img/profiles/avatar-27.jpg" size="large" shape="circle" class="me-2 flex-shrink-0"></Avatar>
                                        <div class="grow">
                                            <p class="mb-1 text-xs/none"><span class="text-dark font-semibold">Shawn</span>
                                                performance in Math is
                                                below the threshold.</p>
                                            <span>Just Now</span>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        </template>
                        <template #end>
                            <div class="flex px-2 py-2.5 border-t-2 gap-x-2">
                                <Button severity="danger" class="w-1/2">Cancel</Button>
                                <Button severity="primary" class="w-1/2">View All</Button>
                            </div>
                        </template>
                    </Menu>
                </div>

                <!-- Message -->
                <OverlayBadge severity="success" class="">
                    <Button :as="Link" href='/chat' icon="pi pi-envelope" class="" severity="secondary" variant="outlined" aria-label="Message" />
                </OverlayBadge>

                <!-- Fullscreen -->
                <div class="">
                    <Button icon="pi pi-expand" class="" variant="outlined" severity="secondary" aria-label="Fullscreen" />
                </div>

                <!-- Profile -->
                <div class="">
                    <Button :rounded="true" @click="(e) => $refs.profile?.toggle(e)" class="" variant="text" aria-label="Profile">
                        <Avatar image="assets/img/profiles/avatar-27.jpg" shape="circle" size="large"></Avatar>
                    </Button>
                    <Menu ref="profile" :popup="true" :model="[{name:'My Profile', value:'profile'},{name:'Settings', value:'settings'},{name:'Logout', value:'logout'}]" placeholder="Profile">

                        <template #start>
                            <div class="flex items-center p-2">
                                <Avatar image="assets/img/profiles/avatar-27.jpg" size="large" shape="circle" class="me-2 flex-shrink-0"></Avatar>
                                <div>
                                    <h6 class="">Kevin Larry</h6>
                                    <p class="text-primary mb-0 text-xs">Administrator</p>
                                </div>
                            </div>
                        </template>
                        <template #item="{ item }">
                            <Button :as="Link" icon="pi pi-user" :href="`/profile/${ item.value }`" variant="text" severity="secondary" :label="item.name" class="w-full justify-start">
                            </Button>
                        </template>
                    </Menu>
                </div>
            </div>
        </template>
    </Menubar>
</template>
