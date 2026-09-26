<script setup>
/*
 * Histórico de auditoria de um registro (ex.: cliente, apólice), usado nas
 * telas de detalhe. Carrega /api/activity-logs filtrado por subject e pagina
 * com "carregar mais" em vez de trazer todo o histórico de uma vez.
 */
import { onMounted, ref } from 'vue';
import { activityLogsApi } from '@/api';
import AppButton from '../ui/AppButton.vue';
import EmptyState from '../ui/EmptyState.vue';
import ErrorState from '../ui/ErrorState.vue';
import LoadingState from '../ui/LoadingState.vue';
import { formatDateTime } from '@/utils/format';
import ActivityChanges from './ActivityChanges.vue';

const props = defineProps({
    subjectType: { type: String, required: true },
    subjectId: { type: [Number, String], required: true },
});

const logs = ref([]);
const meta = ref(null);
const loading = ref(false);
const error = ref(null);

async function load(page = 1) {
    loading.value = true;
    error.value = null;

    try {
        const response = await activityLogsApi.list({
            subject_type: props.subjectType,
            subject_id: props.subjectId,
            page,
            per_page: 10,
        });
        logs.value = page === 1 ? response.data : [...logs.value, ...response.data];
        meta.value = response.meta;
    } catch (exception) {
        error.value = exception;
    } finally {
        loading.value = false;
    }
}

defineExpose({ reload: () => load(1) });

onMounted(() => load());
</script>

<template>
    <div>
        <ErrorState v-if="error && !logs.length" :error="error" title="Não foi possível carregar o histórico" @retry="load()" />
        <LoadingState v-else-if="loading && !logs.length" label="Carregando histórico…" />
        <EmptyState v-else-if="!logs.length" title="Sem registros no histórico" icon="clock" />
        <template v-else>
            <ol class="relative space-y-5 border-l border-slate-200 pl-5">
                <li v-for="log in logs" :key="log.id" class="relative">
                    <span class="absolute top-1.5 -left-[25px] size-2.5 rounded-full bg-brand-300 ring-4 ring-white" aria-hidden="true" />
                    <p class="text-sm text-slate-800">
                        <span class="font-medium">{{ log.action.label }}</span>
                        <span class="text-slate-500"> por {{ log.user?.name ?? 'Sistema' }}</span>
                    </p>
                    <p class="text-xs text-slate-500">
                        <time :datetime="log.created_at">{{ formatDateTime(log.created_at) }}</time>
                    </p>
                    <ActivityChanges :log="log" />
                </li>
            </ol>
            <AppButton
                v-if="meta && meta.current_page < meta.last_page"
                class="mt-4"
                variant="secondary"
                size="sm"
                :loading="loading"
                @click="load(meta.current_page + 1)"
            >
                Carregar mais
            </AppButton>
        </template>
    </div>
</template>
