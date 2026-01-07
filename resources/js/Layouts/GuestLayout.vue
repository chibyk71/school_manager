<script setup lang="ts">
    import { DynamicDialog, Image, type Toast } from 'primevue';
    import '../../css/auth.postcss';
</script>

<template>
    <!-- Main Wrapper -->
    <div class="main-wrapper account-page">

        <div class="w-full">
            <div class="login-wrapper w-full overflow-hidden relative flex-wrap block h-screen">
                <div class="block lg:grid lg:grid-cols-2 w-full h-full">
                    <div class="hidden lg:block">
                        <div class="bg-01 bg-light-300 relative lg:flex items-center justify-center hidden flex-wrap h-screen">
                            <div>
                                <Image class="max-w-full block h-auto" src="assets/img/authentication/authentication-06.svg" alt="Img" />
                            </div>
                        </div>
                    </div>
                    <div class="w-full">
                        <div class="flex justify-center items-center h-screen overflow-auto flex-wrap ">
                            <div class="w-full lg:w-8/12 mx-auto p-4">
                                <div>
                                    <div class="mx-auto mb-12 text-center">
                                        <Image src="assets/img/authentication/authentication-logo.svg"
                                            imageClass="inline text-center" alt="Logo" />
                                    </div>
                                    <slot></slot>
                                    <div class="mt-12 text-center">
                                        <p class="">Copyright &copy; 2024 - Preskool</p>
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
     <Toast />
     <ConfirmDialog />
</template>
