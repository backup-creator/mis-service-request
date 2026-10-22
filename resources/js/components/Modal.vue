<script setup lang="ts">
import { Dialog, DialogPanel, DialogTitle, TransitionChild, TransitionRoot } from '@headlessui/vue';
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        show: boolean;
        title?: string;
        description?: string;
        maxWidth?: 'md' | 'lg' | '2xl' | '3xl' | '4xl' | '5xl' | '6xl';
    }>(),
    {
        title: '',
        description: '',
        maxWidth: '2xl',
    },
);

const emit = defineEmits<{
    close: [];
}>();

const widthClass = computed(
    () =>
        ({
            md: 'max-w-md',
            lg: 'max-w-lg',
            '2xl': 'max-w-2xl',
            '3xl': 'max-w-3xl',
            '4xl': 'max-w-4xl',
            '5xl': 'max-w-5xl',
            '6xl': 'max-w-6xl',
        })[props.maxWidth],
);
</script>

<template>
    <TransitionRoot appear :show="show" as="template">
        <Dialog as="div" class="relative z-[100]" @close="emit('close')">
            <TransitionChild
                as="template"
                enter="duration-200 ease-out"
                enter-from="opacity-0"
                enter-to="opacity-100"
                leave="duration-150 ease-in"
                leave-from="opacity-100"
                leave-to="opacity-0"
            >
                <div class="fixed inset-0 bg-slate-950/60 backdrop-blur-sm" aria-hidden="true" />
            </TransitionChild>

            <div class="fixed inset-0 overflow-y-auto">
                <div class="flex min-h-full items-center justify-center p-3 sm:p-6">
                    <TransitionChild
                        as="template"
                        enter="duration-200 ease-out"
                        enter-from="translate-y-4 scale-95 opacity-0 sm:translate-y-0"
                        enter-to="translate-y-0 scale-100 opacity-100"
                        leave="duration-150 ease-in"
                        leave-from="translate-y-0 scale-100 opacity-100"
                        leave-to="translate-y-4 scale-95 opacity-0 sm:translate-y-0"
                    >
                        <DialogPanel
                            class="flex max-h-[94vh] w-full flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl"
                            :class="widthClass"
                        >
                            <header class="flex shrink-0 items-start justify-between gap-4 border-b border-slate-200 px-5 py-5 sm:px-8">
                                <div class="min-w-0">
                                    <DialogTitle v-if="title || $slots.title" class="text-xl font-bold tracking-tight text-slate-900">
                                        <slot name="title">{{ title }}</slot>
                                    </DialogTitle>
                                    <p v-if="description || $slots.description" class="mt-1 text-sm text-slate-500">
                                        <slot name="description">{{ description }}</slot>
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    aria-label="Close dialog"
                                    class="shrink-0 rounded-lg p-2 text-slate-500 transition hover:bg-slate-100 hover:text-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                                    @click="emit('close')"
                                >
                                    <svg class="size-5" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                        <path d="m5 5 10 10M15 5 5 15" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                                    </svg>
                                </button>
                            </header>

                            <div class="min-h-0 flex-1 overflow-y-auto">
                                <slot />
                            </div>

                            <footer v-if="$slots.footer" class="shrink-0 border-t border-slate-200 bg-slate-50">
                                <slot name="footer" />
                            </footer>
                        </DialogPanel>
                    </TransitionChild>
                </div>
            </div>
        </Dialog>
    </TransitionRoot>
</template>
