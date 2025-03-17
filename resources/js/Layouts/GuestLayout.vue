<script setup lang="ts">
    import { Button, Card, DynamicDialog, IconField, Image, InputChips, InputIcon, InputText } from 'primevue';
    import '../../css/auth.postcss';
    import { Link } from '@inertiajs/vue3';
</script>

<template>
    <!-- Main Wrapper -->
    <div class="main-wrapper">

        <div class="w-full">
            <div class="login-wrapper w-full overflow-hidden relative flex-wrap block h-screen">
                <div class="block lg:grid lg:grid-cols-2">
                    <div class="hidden lg:block">
                        <div class="login-background relative lg:flex items-center justify-center hidden flex-wrap h-screen">
                            <div>
                                <Image class="max-w-full block h-auto" src="assets/img/authentication/authentication-02.jpg" alt="Img" />
                            </div>
                            <div class="authen-overlay-item  w-full p-4">
                                <h4 class="text-primary-contrast mb-3">What's New on Preskool !!!</h4>
                                <Card v-for="i in 4" class="flex items-center flex-row mb-3 justify-between pr-7 gap-3 relative">
                                    <template #title>
                                        <h6>Summer Vacation Holiday Homework</h6>
                                    </template>
                                    <template #content>
                                        <p class="mb-0 text-truncate">The school will remain closed from April 20th to
                                            June...</p>
                                        <Link href="javascript:void(0);" class="absolute top-1/2 right-2.5 -translate-y-1/2"><i class="ti ti-chevrons-right"></i></Link>
                                    </template>
                                </Card>
                                <!-- TODO replace href with actual link for blog page -->
                                <Button :as="Link" href="events.html" class="w-full" severity="primary">See All</Button>
                            </div>
                        </div>
                    </div>
                    <div class="">
                        <div class="flex justify-center items-center h-screen overflow-auto flex-wrap ">
                            <div class="w-full lg:w-8/12 mx-auto p-4">
                                <div>
                                    <div class=" mx-auto mb-5 text-center">
                                        <Image src="assets/img/authentication/authentication-logo.svg"
                                            class="text-center" alt="Logo" />
                                    </div>
                                    <slot></slot>
                                    <div class="mt-7 text-center">
                                        <p class="mb-0 ">Copyright &copy; 2024 - Preskool</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
    <!-- /Main Wrapper -->
     <DynamicDialog />
</template>
