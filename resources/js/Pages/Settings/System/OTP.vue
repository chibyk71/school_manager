<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import SettingsLayout from '../Partials/SettingsLayout.vue';
import SettingsRow from '../Partials/SettingsRow.vue';
import { InputGroup, InputGroupAddon, InputNumber, Select } from 'primevue';
import { useForm } from '@inertiajs/vue3';

const form = useForm({
    otp_type: 'email',
    limit: '5',
    eol: '5'
})

const submit = () => {
    form.post(route('system.otp.post'), {
        onSuccess(e){
            console.log(e);

        },
        onError() {

        }
    })
}
</script>

<template>
    <AuthenticatedLayout title="OTP Setting" :crumb="[{label: 'Dashboard'},{label:'Settings'},{label:'System'}]">
        <SettingsLayout>
            <template #left>

            </template>
            <template #main>
                <div class="pl-3">
                    <form :action="route('system.otp.post')" method="post" @submit.prevent="submit">
                        <div
                            class="flex items-center justify-between flex-wrap border-bottom pt-3 mb-3">
                            <div class="mb-3">
                                <h5 class="mb-1">OTP</h5>
                                <p>OTP configuration</p>
                            </div>
                            <div class="mb-3">
                                <button class="btn btn-light mr-2" type="button">Cancel</button>
                                <button class="btn btn-primary" type="submit">Save</button>
                            </div>
                        </div>
                        <div class="block">
                            <SettingsRow label="OTP Type" description="You can configure the type">
                                <Select fluid :options="['SMS','Email']" v-model="form.otp_type" />
                            </SettingsRow>

                            <SettingsRow label="OTP Digit Limit" description="Select Size Of The Format">
                                <InputNumber fluid :max="10" :min="4" v-model="form.limit" />
                            </SettingsRow>

                            <SettingsRow label="OTP Expire Time" description="Select Expire time of OTP">
                                <InputGroup>
                                    <InputNumber :max="10" :min="4" :model-value="5" />
                                    <InputGroupAddon>
                                        mins
                                    </InputGroupAddon>
                                </InputGroup>
                            </SettingsRow>
                        </div>
                    </form>
                </div>
            </template>
        </SettingsLayout>
    </AuthenticatedLayout>
</template>
