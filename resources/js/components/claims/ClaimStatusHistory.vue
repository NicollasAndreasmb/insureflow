<script setup>
import StatusBadge from '../ui/StatusBadge.vue';
import { formatDateTime } from '@/utils/format';

defineProps({
    history: { type: Array, required: true },
});
</script>

<template>
    <ol class="relative space-y-5 border-l border-slate-200 pl-5">
        <li v-for="entry in history" :key="entry.id" class="relative">
            <span class="absolute top-1 -left-[25px] size-2.5 rounded-full bg-accent-500 ring-4 ring-white" aria-hidden="true" />
            <div class="flex flex-wrap items-center gap-1.5 text-sm">
                <template v-if="entry.from_status">
                    <StatusBadge :status="entry.from_status" />
                    <span class="text-slate-400" aria-hidden="true">→</span>
                    <span class="sr-only">para</span>
                </template>
                <StatusBadge :status="entry.to_status" />
            </div>
            <p class="mt-1 text-xs text-slate-500">
                <time :datetime="entry.created_at">{{ formatDateTime(entry.created_at) }}</time>
                · {{ entry.user?.name ?? 'Sistema' }}
            </p>
            <p v-if="entry.notes" class="mt-1 text-sm whitespace-pre-line text-slate-700">{{ entry.notes }}</p>
        </li>
    </ol>
</template>
