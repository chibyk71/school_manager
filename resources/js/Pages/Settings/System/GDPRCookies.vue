<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import SettingsLayout from '../Partials/SettingsLayout.vue';
import SettingsRow from '../Partials/SettingsRow.vue';
import { Button, InputGroup, InputGroupAddon, InputNumber, InputText, Select, Textarea, ToggleSwitch } from 'primevue';
import { useForm } from '@inertiajs/vue3';

const form = useForm({
    content_text: '',
    position: 'Right',
    button_text: 'Decline',
    agree_button_text: 'Accept',
    decline_button_text: 'Decline',
    show_decline_button: false,
    show_agree_button: true
});

const submit = () => {
    form.post(route('system.gdpr.post'), {
        onSuccess(e){
            console.log(e);

        },
        onError() {

        }
    })
}
</script>

<template>
    <AuthenticatedLayout title="GDPR Cookies Setting" :crumb="[{label: 'Dashboard'},{label:'Settings'},{label:'System'}]">
        <SettingsLayout>
            <template #left>

            </template>
            <template #main>
                <div class="pl-3">
                    <form :action="route('system.gdpr.post')" method="post" @submit.prevent="submit">
                        <div class="flex items-center justify-between flex-wrap border-b pt-3 mb-3">
                            <div class="mb-3">
                                <h5 class="mb-1">GDPR Cookies</h5>
                                <p>GDPR Cookies configuration</p>
                            </div>
                            <div class="mb-3">
                                <Button severity="secondary" class="mr-2" type="button">Cancel</Button>
                                <Button class="" type="submit" label="Save" :loading="form.processing"></Button>
                            </div>
                        </div>
                        <div>
                            <SettingsRow label="Cookies Content Text" description="You Can Configure The Text">
                                <Textarea rows="3" v-model="form.content_text" fluid></Textarea>
                            </SettingsRow>

                            <SettingsRow label="Cookies Position" description="You can configure the position">
                                <Select fluid v-model="form.position" :options="['Right','Left','Center']" />
                            </SettingsRow>

                            <SettingsRow label="Cookies Button Text" description="You can configure the text here">
                                <InputText v-model="form.button_text" fluid />
                            </SettingsRow>

                            <SettingsRow label="Agree Button Text" description="You can configure the text here">
                                <InputText v-model="form.agree_button_text" fluid />
                            </SettingsRow>

                            <SettingsRow label="Decline Button Text" description="You can configure the text here">
                                <InputText v-model="form.decline_button_text" fluid />
                            </SettingsRow>

                            <SettingsRow label="Show Decline Button" description="To display decline button">
                                <ToggleSwitch v-model="form.show_agree_button" />
                            </SettingsRow>

                            <SettingsRow label="Show Agree Button" description="To display agree button">
                                <ToggleSwitch v-model="form.show_decline_button" />
                            </SettingsRow>
                        </div>
                    </form>
                </div>
            </template>
        </SettingsLayout>
    </AuthenticatedLayout>
</template>
