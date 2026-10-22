<script setup lang="ts">
import { Dialog, DialogPanel, DialogTitle } from '@headlessui/vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { SharedData } from '@/types';

const page = usePage<SharedData>();
const form = useForm({
    location_id: page.props.auth.user.location_id ?? '',
    department_id: page.props.auth.user.department_id ?? '',
});

const isIncomplete = computed(() => !page.props.auth.user.location_id || !page.props.auth.user.department_id);
const departments = computed(() =>
    page.props.officeOptions.departments.filter((department) => department.location_id === Number(form.location_id)),
);

function saveOffice() {
    form.patch(route('profile.office.update'), {
        preserveScroll: true,
    });
}
</script>

<template>
    <Dialog :open="isIncomplete" as="div" class="relative z-[120]" @close="() => undefined">
        <div class="fixed inset-0 bg-slate-950/70 backdrop-blur-sm" aria-hidden="true" />
        <div class="fixed inset-0 overflow-y-auto">
            <div class="flex min-h-full items-center justify-center p-4">
                <DialogPanel class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl sm:p-8">
                    <DialogTitle class="text-xl font-bold tracking-tight text-slate-900">
                        Complete your profile
                    </DialogTitle>
                    <p class="mt-2 text-sm leading-6 text-slate-600">
                        Select your campus and department to continue using the service desk.
                    </p>

                    <form class="mt-6 space-y-5" @submit.prevent="saveOffice">
                        <div>
                            <label for="onboarding-location" class="block text-sm font-semibold text-slate-900">
                                Campus Location
                            </label>
                            <select
                                id="onboarding-location"
                                v-model="form.location_id"
                                required
                                class="mt-2 block w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                @change="form.department_id = ''"
                            >
                                <option value="" disabled>Select a campus</option>
                                <option v-for="location in page.props.officeOptions.locations" :key="location.id" :value="location.id">
                                    {{ location.name }}
                                </option>
                            </select>
                            <p v-if="form.errors.location_id" class="mt-2 text-sm text-rose-600">{{ form.errors.location_id }}</p>
                        </div>

                        <div>
                            <label for="onboarding-department" class="block text-sm font-semibold text-slate-900">
                                Department / Office
                            </label>
                            <select
                                id="onboarding-department"
                                v-model="form.department_id"
                                required
                                :disabled="!form.location_id"
                                class="mt-2 block w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-slate-100"
                            >
                                <option value="" disabled>Select a department</option>
                                <option v-for="department in departments" :key="department.id" :value="department.id">
                                    {{ department.name }}
                                </option>
                            </select>
                            <p v-if="form.errors.department_id" class="mt-2 text-sm text-rose-600">{{ form.errors.department_id }}</p>
                        </div>

                        <button
                            type="submit"
                            :disabled="form.processing || !form.location_id || !form.department_id"
                            class="inline-flex w-full justify-center rounded-lg bg-indigo-600 px-4 py-3 text-sm font-semibold text-white hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            {{ form.processing ? 'Saving…' : 'Save and continue' }}
                        </button>
                    </form>
                </DialogPanel>
            </div>
        </div>
    </Dialog>
</template>
