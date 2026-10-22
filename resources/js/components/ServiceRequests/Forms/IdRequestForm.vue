<script setup lang="ts">
import IdCardPreview from '@/components/ServiceRequests/IdCardPreview.vue';
import { reactive, ref, watch } from 'vue';

const props = defineProps<{
    id?: string;
    initial?: Record<string, any>;
    loading?: boolean;
    validationErrors?: Record<string, string[]>;
}>();

const emit = defineEmits<{
    (e: 'submit', payload: Record<string, any>): void;
    (e: 'cancel'): void;
}>();

const local = reactive({
    id_request_type: '',
    id_first_name: '',
    id_middle_name: '',
    id_last_name: '',
    id_suffix: '',
    id_position: '',
    id_office: '',
    id_tin: '',
    id_gsis: '',
    id_birthdate: '',
    id_photo: null as File | null,
    client_signature: null as File | null,
    id_emergency_name: '',
    id_emergency_phone: '',
    id_emergency_address: '',
});

const maxBirthdate = new Date().toISOString().split('T')[0];
const requestForm = ref<HTMLFormElement | null>(null);
const photoPreview = ref<string | null>(null);
const sigPreview = ref<string | null>(null);
const photoPreviewError = ref('');
const signaturePreviewError = ref('');

function readAsDataUrl(file: File): Promise<string> {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();

        reader.onload = () => {
            if (typeof reader.result === 'string') {
                resolve(reader.result);
            } else {
                reject(new Error('The selected image could not be previewed.'));
            }
        };
        reader.onerror = () => reject(reader.error ?? new Error('The selected image could not be read.'));
        reader.readAsDataURL(file);
    });
}

async function handlePhotoChange(e: Event) {
    const target = e.target as HTMLInputElement;
    if (target.files && target.files[0]) {
        const file = target.files[0];
        try {
            photoPreview.value = await readAsDataUrl(file);
            local.id_photo = file;
            photoPreviewError.value = '';
        } catch {
            photoPreview.value = null;
            local.id_photo = null;
            photoPreviewError.value = 'The selected photo could not be read. Please choose another image.';
        }
    }
}

async function handleSigChange(e: Event) {
    const target = e.target as HTMLInputElement;
    if (target.files && target.files[0]) {
        const file = target.files[0];
        try {
            sigPreview.value = await readAsDataUrl(file);
            local.client_signature = file;
            signaturePreviewError.value = '';
        } catch {
            sigPreview.value = null;
            local.client_signature = null;
            signaturePreviewError.value = 'The selected signature could not be read. Please choose another image.';
        }
    }
}

function formatTin(event: Event) {
    const input = event.target as HTMLInputElement;
    const digits = input.value.replace(/\D/g, '').slice(0, 9);

    local.id_tin = digits.match(/.{1,3}/g)?.join('-') ?? '';
}

function formatGsis(event: Event) {
    const input = event.target as HTMLInputElement;

    local.id_gsis = input.value.replace(/\D/g, '').slice(0, 10);
}

function formatEmergencyPhone(event: Event) {
    const input = event.target as HTMLInputElement;

    local.id_emergency_phone = input.value.replace(/\D/g, '').slice(0, 11);
}

watch(
    () => props.initial,
    (value) => {
        if (value) {
            local.id_request_type = value.id_request_type ?? '';
            local.id_first_name = value.id_first_name ?? '';
            local.id_middle_name = value.id_middle_name ?? '';
            local.id_last_name = value.id_last_name ?? '';
            local.id_suffix = value.id_suffix ?? '';
            local.id_position = value.id_position ?? '';
            local.id_office = value.id_office ?? '';
            local.id_tin = value.id_tin ?? '';
            local.id_gsis = value.id_gsis ?? '';
            local.id_birthdate = value.id_birthdate ?? '';
            local.id_emergency_name = value.id_emergency_name ?? '';
            local.id_emergency_phone = value.id_emergency_phone ?? '';
            local.id_emergency_address = value.id_emergency_address ?? '';
        }
    },
    { immediate: true, deep: true },
);

function submitForm() {
    requestForm.value?.requestSubmit();
}

function emitPayload() {
    emit('submit', { ...local });
}

defineExpose({ submitForm });

const inputClass =
    'block w-full rounded-xl border border-slate-200 bg-slate-50/50 px-4 py-2.5 text-sm text-slate-900 shadow-sm transition-all placeholder:text-slate-400 hover:border-slate-300 focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-4 focus:ring-blue-500/10 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-700 dark:bg-slate-900/50 dark:text-white dark:focus:border-blue-500 dark:focus:bg-slate-900';
</script>

<template>
    <form ref="requestForm" :id="props.id" @submit.prevent="emitPayload" class="space-y-8">
        <div class="rounded-[16px] border border-blue-200 bg-blue-50/80 p-5 dark:border-blue-500/30 dark:bg-blue-900/20">
            <div class="flex items-start gap-4">
                <span
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-blue-100 text-blue-600 dark:bg-blue-900/50 dark:text-blue-400"
                >
                    <span class="material-symbols-outlined text-[20px]">styler</span>
                </span>
                <div>
                    <p class="text-sm font-bold tracking-wide text-blue-900 dark:text-blue-300">Dress Code Requirement</p>
                    <p class="mt-1 text-sm leading-relaxed text-blue-800/80 dark:text-blue-200/80">
                        Please ensure you are wearing
                        <span class="font-semibold text-blue-900 dark:text-blue-100">Formal White, Barong, or Filipiniana</span>
                        for your ID picture.
                    </p>
                </div>
            </div>
        </div>

        <section>
            <div class="mb-5 flex items-center gap-3">
                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400">
                    <span class="material-symbols-outlined text-[18px]">badge</span>
                </div>
                <h3 class="text-base font-bold text-slate-900 dark:text-white">Employee Information</h3>
            </div>

            <div class="space-y-6 rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700/80 dark:bg-slate-950/50">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">
                        Type of Request <span class="text-rose-500">*</span>
                    </label>
                    <div class="relative">
                        <select v-model="local.id_request_type" :disabled="props.loading" required :class="inputClass" class="appearance-none pr-10">
                            <option value="" disabled>Select the purpose of this request</option>
                            <option value="New">New (Newly Hired)</option>
                            <option value="Replacement">Replacement (Damaged / Worn Out)</option>
                            <option value="Lost">Lost</option>
                            <option value="Update">Update / Change of Information</option>
                        </select>
                        <span class="material-symbols-outlined pointer-events-none absolute right-3 top-2.5 text-slate-400">expand_more</span>
                    </div>
                    <p v-if="props.validationErrors?.id_request_type" class="mt-1.5 text-xs text-rose-500">
                        {{ props.validationErrors.id_request_type.join(', ') }}
                    </p>
                </div>

                <hr class="border-slate-100 dark:border-slate-800/60" />

                <div>
                    <label class="mb-3 block text-sm font-medium text-slate-700 dark:text-slate-300">
                        Full Name <span class="text-rose-500">*</span>
                    </label>
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-12">
                        <div class="md:col-span-4">
                            <input
                                v-model="local.id_first_name"
                                type="text"
                                :disabled="props.loading"
                                required
                                placeholder="First Name"
                                :class="inputClass"
                            />
                            <p v-if="props.validationErrors?.id_first_name" class="mt-1.5 text-xs text-rose-500">
                                {{ props.validationErrors.id_first_name.join(', ') }}
                            </p>
                        </div>
                        <div class="md:col-span-4">
                            <input
                                v-model="local.id_middle_name"
                                type="text"
                                :disabled="props.loading"
                                placeholder="Middle Name"
                                :class="inputClass"
                            />
                            <p v-if="props.validationErrors?.id_middle_name" class="mt-1.5 text-xs text-rose-500">
                                {{ props.validationErrors.id_middle_name.join(', ') }}
                            </p>
                        </div>
                        <div class="md:col-span-3">
                            <input
                                v-model="local.id_last_name"
                                type="text"
                                :disabled="props.loading"
                                required
                                placeholder="Last Name"
                                :class="inputClass"
                            />
                            <p v-if="props.validationErrors?.id_last_name" class="mt-1.5 text-xs text-rose-500">
                                {{ props.validationErrors.id_last_name.join(', ') }}
                            </p>
                        </div>
                        <div class="md:col-span-1">
                            <input v-model="local.id_suffix" type="text" :disabled="props.loading" placeholder="Jr., III" :class="inputClass" />
                            <p v-if="props.validationErrors?.id_suffix" class="mt-1.5 text-xs text-rose-500">
                                {{ props.validationErrors.id_suffix.join(', ') }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">
                            Position / Title <span class="text-rose-500">*</span>
                        </label>
                        <input
                            v-model="local.id_position"
                            type="text"
                            :disabled="props.loading"
                            required
                            placeholder="e.g. Instructor I"
                            :class="inputClass"
                        />
                        <p v-if="props.validationErrors?.id_position" class="mt-1.5 text-xs text-rose-500">
                            {{ props.validationErrors.id_position.join(', ') }}
                        </p>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">
                            Department / Office <span class="text-rose-500">*</span>
                        </label>
                        <input
                            v-model="local.id_office"
                            type="text"
                            :disabled="props.loading"
                            required
                            placeholder="e.g. College of IT"
                            :class="inputClass"
                        />
                        <p v-if="props.validationErrors?.id_office" class="mt-1.5 text-xs text-rose-500">
                            {{ props.validationErrors.id_office.join(', ') }}
                        </p>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">TIN No.</label>
                        <input
                            :value="local.id_tin"
                            type="text"
                            inputmode="numeric"
                            maxlength="11"
                            :disabled="props.loading"
                            placeholder="123-123-123"
                            :class="inputClass"
                            @input="formatTin"
                        />
                        <p v-if="props.validationErrors?.id_tin" class="mt-1.5 text-xs text-rose-500">
                            {{ props.validationErrors.id_tin.join(', ') }}
                        </p>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">GSIS No.</label>
                        <input
                            :value="local.id_gsis"
                            type="text"
                            inputmode="numeric"
                            maxlength="10"
                            :disabled="props.loading"
                            placeholder="1234567890"
                            :class="inputClass"
                            @input="formatGsis"
                        />
                        <p v-if="props.validationErrors?.id_gsis" class="mt-1.5 text-xs text-rose-500">
                            {{ props.validationErrors.id_gsis.join(', ') }}
                        </p>
                    </div>

                    <div class="md:col-span-2">
                        <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">
                            Birthdate <span class="text-rose-500">*</span>
                        </label>
                        <input v-model="local.id_birthdate" type="date" :max="maxBirthdate" :disabled="props.loading" required :class="inputClass" />
                        <p v-if="props.validationErrors?.id_birthdate" class="mt-1.5 text-xs text-rose-500">
                            {{ props.validationErrors.id_birthdate.join(', ') }}
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <section>
            <div class="mb-5 flex items-center gap-3">
                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400">
                    <span class="material-symbols-outlined text-[18px]">photo_camera</span>
                </div>
                <h3 class="text-base font-bold text-slate-900 dark:text-white">Attachments</h3>
            </div>

            <div
                class="grid gap-5 rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700/80 dark:bg-slate-950/50 md:grid-cols-2"
            >
                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700 dark:text-slate-300">
                        2x2 ID Picture <span class="text-rose-500">*</span>
                    </label>
                    <div
                        class="group relative flex h-44 w-full cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed border-slate-300 bg-slate-50 transition-all hover:border-blue-400 hover:bg-blue-50/50 dark:border-slate-600 dark:bg-slate-900/30 dark:hover:border-blue-500 dark:hover:bg-blue-900/10"
                    >
                        <input
                            type="file"
                            accept="image/*"
                            required
                            @change="handlePhotoChange"
                            class="absolute inset-0 z-10 h-full w-full cursor-pointer opacity-0"
                        />
                        <div v-if="!photoPreview" class="flex flex-col items-center text-center transition-transform group-hover:scale-105">
                            <div
                                class="mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-slate-400 group-hover:bg-blue-100 group-hover:text-blue-500 dark:bg-slate-800 dark:text-slate-500"
                            >
                                <span class="material-symbols-outlined">add_photo_alternate</span>
                            </div>
                            <span class="text-sm font-semibold text-slate-600 dark:text-slate-300">Click to upload photo</span>
                            <span class="mt-1 text-xs text-slate-400">JPG, PNG (Max 5MB)</span>
                        </div>
                        <img v-else :src="photoPreview" class="z-0 h-full w-full rounded-2xl object-cover p-1 shadow-sm" />
                    </div>
                    <p v-if="props.validationErrors?.id_photo" class="mt-1.5 text-xs text-rose-500">
                        {{ props.validationErrors.id_photo.join(', ') }}
                    </p>
                    <p v-if="photoPreviewError" class="mt-1.5 text-xs text-rose-500">
                        {{ photoPreviewError }}
                    </p>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700 dark:text-slate-300">
                        Digital Signature <span class="text-rose-500">*</span>
                    </label>
                    <div
                        class="group relative flex h-44 w-full cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed border-slate-300 bg-slate-50 transition-all hover:border-blue-400 hover:bg-blue-50/50 dark:border-slate-600 dark:bg-slate-900/30 dark:hover:border-blue-500 dark:hover:bg-blue-900/10"
                    >
                        <input
                            type="file"
                            accept="image/*"
                            required
                            @change="handleSigChange"
                            class="absolute inset-0 z-10 h-full w-full cursor-pointer opacity-0"
                        />
                        <div v-if="!sigPreview" class="flex flex-col items-center text-center transition-transform group-hover:scale-105">
                            <div
                                class="mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-slate-400 group-hover:bg-blue-100 group-hover:text-blue-500 dark:bg-slate-800 dark:text-slate-500"
                            >
                                <span class="material-symbols-outlined">draw</span>
                            </div>
                            <span class="text-sm font-semibold text-slate-600 dark:text-slate-300">Click to upload signature</span>
                            <span class="mt-1 text-xs text-slate-400">Clear background preferred</span>
                        </div>
                        <img v-else :src="sigPreview" class="z-0 h-full w-full rounded-2xl object-contain p-3 shadow-sm" />
                    </div>
                    <p v-if="props.validationErrors?.client_signature" class="mt-1.5 text-xs text-rose-500">
                        {{ props.validationErrors.client_signature.join(', ') }}
                    </p>
                    <p v-if="signaturePreviewError" class="mt-1.5 text-xs text-rose-500">
                        {{ signaturePreviewError }}
                    </p>
                </div>
            </div>
        </section>

        <section>
            <div class="mb-5 flex items-center gap-3">
                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400">
                    <span class="material-symbols-outlined text-[18px]">medical_services</span>
                </div>
                <h3 class="text-base font-bold text-slate-900 dark:text-white">In Case of Emergency</h3>
            </div>

            <div
                class="grid gap-5 rounded-[24px] border border-slate-200 bg-slate-50/50 p-6 dark:border-slate-700/80 dark:bg-slate-900/20 md:grid-cols-2"
            >
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">
                        Contact Person <span class="text-rose-500">*</span>
                    </label>
                    <input
                        v-model="local.id_emergency_name"
                        type="text"
                        :disabled="props.loading"
                        required
                        placeholder="Full name of contact"
                        :class="inputClass"
                        class="!bg-white dark:!bg-slate-900/50"
                    />
                    <p v-if="props.validationErrors?.id_emergency_name" class="mt-1.5 text-xs text-rose-500">
                        {{ props.validationErrors.id_emergency_name.join(', ') }}
                    </p>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">
                        Contact Number <span class="text-rose-500">*</span>
                    </label>
                    <input
                        :value="local.id_emergency_phone"
                        type="text"
                        inputmode="numeric"
                        maxlength="11"
                        :disabled="props.loading"
                        required
                        placeholder="e.g. 09204762122"
                        :class="inputClass"
                        class="!bg-white dark:!bg-slate-900/50"
                        @input="formatEmergencyPhone"
                    />
                    <p v-if="props.validationErrors?.id_emergency_phone" class="mt-1.5 text-xs text-rose-500">
                        {{ props.validationErrors.id_emergency_phone.join(', ') }}
                    </p>
                </div>

                <div class="md:col-span-2">
                    <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">
                        Full Address <span class="text-rose-500">*</span>
                    </label>
                    <input
                        v-model="local.id_emergency_address"
                        type="text"
                        :disabled="props.loading"
                        required
                        placeholder="Complete residential address"
                        :class="inputClass"
                        class="!bg-white dark:!bg-slate-900/50"
                    />
                    <p v-if="props.validationErrors?.id_emergency_address" class="mt-1.5 text-xs text-rose-500">
                        {{ props.validationErrors.id_emergency_address.join(', ') }}
                    </p>
                </div>
            </div>
        </section>

        <IdCardPreview
            :first-name="local.id_first_name"
            :middle-name="local.id_middle_name"
            :last-name="local.id_last_name"
            :suffix="local.id_suffix"
            :position="local.id_position"
            :photo-url="photoPreview"
            :signature-url="sigPreview"
            :birthdate="local.id_birthdate"
            :tin="local.id_tin"
            :gsis="local.id_gsis"
            :emergency-name="local.id_emergency_name"
            :emergency-phone="local.id_emergency_phone"
            :emergency-address="local.id_emergency_address"
        />
    </form>
</template>
