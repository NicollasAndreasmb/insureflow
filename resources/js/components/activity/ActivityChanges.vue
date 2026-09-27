<script setup>
/*
 * Exibe o campo `changes` de um activity log ({old, new}) de forma legível.
 *
 * Nomes de campos e valores de enums são traduzidos com os rótulos de
 * /api/options; datas "YYYY-MM-DD", booleanos e decimais são formatados (no
 * banco, todo decimal com duas casas é dinheiro, exceto a taxa de comissão,
 * que é percentual). Na criação, os valores iniciais ficam recolhidos para
 * não poluir a linha do tempo.
 */
import { computed } from 'vue';
import { useOptionsStore } from '@/stores/options';
import { formatDate, formatMoney, formatPercent } from '@/utils/format';

const PERCENT_FIELDS = ['commission_rate'];

function capitalize(text) {
    return text.charAt(0).toUpperCase() + text.slice(1);
}

const props = defineProps({
    log: { type: Object, required: true },
});

const options = useOptionsStore();

const STATUS_OPTIONS = { policy: 'policy_statuses', claim: 'claim_statuses', installment: 'installment_statuses' };
const FIELD_OPTIONS = { person_type: 'person_types', payment_method: 'payment_methods', role: 'user_roles' };

function formatValue(field, value) {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    if (typeof value === 'boolean') {
        return value ? 'Sim' : 'Não';
    }

    const optionsKey = field === 'status' ? STATUS_OPTIONS[props.log.subject_type] : FIELD_OPTIONS[field];

    if (optionsKey) {
        return options.label(optionsKey, value);
    }

    if (typeof value === 'string' && /^\d{4}-\d{2}-\d{2}/.test(value) && (field.endsWith('_on') || field.endsWith('_date'))) {
        return formatDate(value);
    }

    if (typeof value === 'string' && /^-?\d+\.\d{2}$/.test(value)) {
        return PERCENT_FIELDS.includes(field) ? formatPercent(value) : formatMoney(value);
    }

    return String(value);
}

const rows = computed(() => {
    const changes = props.log.changes ?? {};
    const fields = Object.keys(changes.new ?? {});

    return fields.map((field) => ({
        field,
        label: capitalize(options.fieldLabel(field)),
        old: changes.old ? formatValue(field, changes.old[field]) : null,
        new: formatValue(field, changes.new[field]),
    }));
});
</script>

<template>
    <div v-if="rows.length" class="mt-1.5 text-xs">
        <details v-if="log.action.value === 'created'" class="group">
            <summary class="cursor-pointer text-slate-500 hover:text-slate-700">Ver dados iniciais ({{ rows.length }} campos)</summary>
            <dl class="mt-2 grid grid-cols-[auto_1fr] gap-x-3 gap-y-1">
                <template v-for="row in rows" :key="row.field">
                    <dt class="text-slate-500">{{ row.label }}</dt>
                    <dd class="break-words text-slate-700">{{ row.new }}</dd>
                </template>
            </dl>
        </details>
        <ul v-else class="space-y-0.5">
            <li v-for="row in rows" :key="row.field" class="text-slate-600">
                <span class="font-medium text-slate-700">{{ row.label }}:&nbsp;</span>
                <span class="text-slate-500 line-through decoration-slate-300">{{ row.old }}</span>
                <span aria-hidden="true">&nbsp;→&nbsp;</span>
                <span class="sr-only">alterado para</span>
                <span class="text-slate-800">{{ row.new }}</span>
            </li>
        </ul>
    </div>
</template>
