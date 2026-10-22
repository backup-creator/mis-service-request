<script setup lang="ts">
import { AppWindow, Briefcase, CircleHelp, Code, IdCard, KeyRound, Laptop, Wifi, Wrench } from 'lucide-vue-next';
import type { Component } from 'vue';

interface LookupOption {
    id: number;
    name: string;
    code: string;
    description?: string | null;
}

defineProps<{
    categories: LookupOption[];
}>();

const emit = defineEmits<{
    select: [category: LookupOption];
}>();

const categoryIcons: Record<string, Component> = {
    ID_REQUEST: IdCard,
    ACCESS: KeyRound,
    HARDWARE: Laptop,
    SOFTWARE: AppWindow,
    NETWORK: Wifi,
    JOB_REQUEST: Briefcase,
    TECH_ASSIST: Wrench,
    OTHERS: CircleHelp,
};

function description(category: LookupOption): string {
    return category.description || 'Choose this category for related service and support requests.';
}

function icon(category: LookupOption): Component {
    return categoryIcons[category.code] ?? Code;
}
</script>

<template>
    <div>
        <div v-if="categories.length" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <button
                v-for="category in categories"
                :key="category.id"
                type="button"
                class="group rounded-xl border border-slate-200 bg-white p-5 text-left transition hover:-translate-y-0.5 hover:border-indigo-300 hover:bg-indigo-50/50 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-indigo-500"
                @click="emit('select', category)"
            >
                <span
                    class="flex h-11 w-11 items-center justify-center rounded-xl bg-indigo-100 text-indigo-700 transition group-hover:bg-indigo-600 group-hover:text-white"
                >
                    <component :is="icon(category)" class="h-5 w-5" aria-hidden="true" />
                </span>
                <span class="mt-4 block font-semibold text-slate-900">{{ category.name }}</span>
                <span class="mt-1 block text-sm leading-5 text-slate-500">{{ description(category) }}</span>
            </button>
        </div>
        <p v-else class="rounded-xl bg-amber-50 p-5 text-sm text-amber-800">
            No request categories are currently available. Please check back later.
        </p>
    </div>
</template>
