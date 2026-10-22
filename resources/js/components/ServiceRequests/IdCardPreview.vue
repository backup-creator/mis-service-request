<script setup lang="ts">
import { computed } from 'vue';

type PreviewMetadata = Record<string, unknown>;

const props = defineProps<{
    metadata?: PreviewMetadata;
    firstName?: string;
    middleName?: string;
    lastName?: string;
    suffix?: string;
    position?: string;
    photoUrl?: string | null;
    signatureUrl?: string | null;
    birthdate?: string;
    tin?: string;
    gsis?: string;
    emergencyName?: string;
    emergencyPhone?: string;
    emergencyAddress?: string;
}>();

function metadataText(...keys: string[]) {
    for (const key of keys) {
        const value = props.metadata?.[key];

        if (typeof value === 'string' || typeof value === 'number') {
            return String(value);
        }
    }

    return '';
}

function metadataImage(...keys: string[]) {
    const path = metadataText(...keys);

    if (!path) {
        return null;
    }

    if (/^(?:data:|https?:\/\/|\/)/i.test(path)) {
        return path;
    }

    return `/storage/${path.replace(/^(?:(?:public|storage)\/)+/, '')}`;
}

const firstName = computed(() => props.firstName || metadataText('id_first_name', 'first_name'));
const middleName = computed(() => props.middleName || metadataText('id_middle_name', 'middle_name'));
const lastName = computed(() => props.lastName || metadataText('id_last_name', 'last_name'));
const suffix = computed(() => props.suffix || metadataText('id_suffix', 'suffix'));
const position = computed(() => props.position || metadataText('id_position', 'position') || 'Position / Title');
const photoUrl = computed(() => props.photoUrl || metadataImage('id_photo_url', 'id_photo_path', 'id_photo', 'photo'));
const signatureUrl = computed(() => props.signatureUrl || metadataImage('client_signature_url', 'client_signature_path', 'id_signature', 'signature'));
const birthdate = computed(() => props.birthdate || metadataText('id_birthdate', 'id_dob', 'dob'));
const tin = computed(() => props.tin || metadataText('id_tin', 'tin'));
const gsis = computed(() => props.gsis || metadataText('id_gsis', 'gsis'));
const emergencyName = computed(() => props.emergencyName || metadataText('id_emergency_name', 'emergency_contact_name'));
const emergencyPhone = computed(() => props.emergencyPhone || metadataText('id_emergency_phone', 'emergency_contact_phone'));
const emergencyAddress = computed(() => props.emergencyAddress || metadataText('id_emergency_address', 'emergency_address'));
const idNumber = computed(() => metadataText('id_number', 'official_id_number') || '2026-XXXXXXX');

const fullName = computed(() => {
    const middleInitial = middleName.value.trim() ? `${[...middleName.value.trim()][0].toUpperCase()}.` : '';

    return [firstName.value, middleInitial, lastName.value, suffix.value].filter(Boolean).join(' ');
});

const formattedTin = computed(() => {
    const digits = tin.value.replace(/\D/g, '').slice(0, 9);

    return digits.match(/.{1,3}/g)?.join('-') || '—';
});

const formattedBirthdate = computed(() => {
    if (!birthdate.value) {
        return '—';
    }

    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(birthdate.value);

    if (!match) {
        const parsed = new Date(birthdate.value);

        return Number.isNaN(parsed.getTime()) ? birthdate.value : parsed.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
    }

    const date = new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]));

    return Number.isNaN(date.getTime()) ? '—' : date.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
});

const formattedGsis = computed(() => gsis.value.replace(/\D/g, '').slice(0, 10) || '—');
</script>

<template>
    <section class="rounded-2xl border border-slate-200 bg-slate-50 p-5 dark:border-slate-700 dark:bg-slate-900/40">
        <div class="mb-4">
            <h3 class="text-base font-bold text-slate-900 dark:text-white">Live ID Card Preview</h3>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Preview updates as you complete the employee details and upload images.</p>
        </div>

        <div class="grid grid-cols-1 justify-items-center gap-6 xl:grid-cols-2">
            <div>
                <p class="mb-2 text-sm font-semibold text-slate-700 dark:text-slate-200">Front</p>
                <div class="relative h-[505px] w-[320px] overflow-hidden rounded-xl border border-gray-200 bg-white shadow-md">
                    <img src="/images/id-template-front.png" alt="" class="absolute inset-0 h-full w-full object-fill" />
                    <img v-if="photoUrl" :src="photoUrl" alt="ID photo preview" class="absolute left-0.4 top-[96px] h-[175px] w-[152px] object-cover" />
                    <div class="absolute left-0 top-[300px] w-full truncate px-3 text-center text-base text-[20px] font-bold uppercase tracking-tight text-black">
                        {{ fullName || 'Employee Name' }}
                    </div>
                    <div
                        class="absolute left-0 top-[330px] line-clamp-2 h-8 w-full px-3 text-center text-[12px] font-semibold uppercase leading-snug tracking-tight text-black"
                    >
                        {{ position }}
                    </div>
                    <img
                        v-if="signatureUrl"
                        :src="signatureUrl"
                        alt="Digital signature preview"
                        class="absolute left-1/2 top-[350px] h-12 max-w-[100px] -translate-x-1/2 object-contain"
                    />
                    <div class="absolute bottom-[40px] left-[135px] max-w-[165px] truncate text-[16px] font-bold tracking-wider text-black">
                        {{ idNumber }}
                    </div>
                    <div
                        aria-hidden="true"
                        style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%) rotate(-35deg); font-size: 38px; font-weight: 900; color: rgba(220, 38, 38, 0.35); text-transform: uppercase; letter-spacing: 0.12em; pointer-events: none; white-space: nowrap; border: 4px dashed rgba(220, 38, 38, 0.4); padding: 8px 20px; border-radius: 12px; z-index: 30; text-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);"
                    >
                        SAMPLE ONLY
                    </div>
                </div>
            </div>

            <div>
                <p class="mb-2 text-sm font-semibold text-slate-700 dark:text-slate-200">Back</p>
                <div class="relative h-[505px] w-[320px] overflow-hidden rounded-xl border border-gray-200 bg-white shadow-md">
                    <img src="/images/id-template-back.png" alt="" class="absolute inset-0 h-full w-full object-fill" />
                    <div class="absolute left-[150px] top-[77px] max-w-[130px] truncate text-xs text-black">
                        {{ formattedBirthdate }}
                    </div>
                    <div class="absolute left-[150px] top-[98px] max-w-[130px] truncate text-xs text-black">
                        {{ formattedTin }}
                    </div>
                    <div class="absolute left-[150px] top-[118px] max-w-[130px] truncate text-xs text-black">
                        {{ formattedGsis }}
                    </div>
                    <div class="absolute left-0 top-[215px] w-full px-5 text-center text-xs leading-5 text-black">
                        <div class="truncate font-semibold">{{ emergencyName || 'Emergency contact name' }}</div>
                        <div class="line-clamp-2">{{ emergencyAddress || 'Emergency contact address' }}</div>
                        <div class="font-bold text-black">{{ emergencyPhone || 'Emergency contact phone' }}</div>
                    </div>
                    <div
                        aria-hidden="true"
                        style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%) rotate(-35deg); font-size: 38px; font-weight: 900; color: rgba(220, 38, 38, 0.35); text-transform: uppercase; letter-spacing: 0.12em; pointer-events: none; white-space: nowrap; border: 4px dashed rgba(220, 38, 38, 0.4); padding: 8px 20px; border-radius: 12px; z-index: 30; text-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);"
                    >
                        SAMPLE ONLY
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
