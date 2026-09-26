<script setup>
/*
 * Gráfico de barras horizontais (Chart.js) para distribuições simples.
 *
 * Apenas os módulos do Chart.js usados aqui são registrados, reduzindo o
 * bundle. O canvas não é acessível a leitores de tela, então os mesmos dados
 * são expostos em uma tabela visualmente oculta.
 */
import { BarController, BarElement, CategoryScale, Chart, Legend, LinearScale, Tooltip } from 'chart.js';
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';

Chart.register(BarController, BarElement, CategoryScale, LinearScale, Tooltip, Legend);

const props = defineProps({
    labels: { type: Array, required: true },
    datasets: { type: Array, required: true },
    summary: { type: String, required: true },
    formatValue: { type: Function, default: (value) => value },
});

const canvas = ref(null);
let chart = null;

function render() {
    chart?.destroy();
    chart = new Chart(canvas.value, {
        type: 'bar',
        data: { labels: props.labels, datasets: props.datasets },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            animation: false,
            plugins: {
                legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 12 } } },
                tooltip: { callbacks: { label: (context) => `${context.dataset.label}: ${props.formatValue(context.parsed.x)}` } },
            },
            scales: {
                x: { beginAtZero: true, ticks: { precision: 0, font: { size: 11 } }, grid: { color: '#f1f5f9' } },
                y: { ticks: { font: { size: 12 } }, grid: { display: false } },
            },
        },
    });
}

onMounted(render);
watch(() => [props.labels, props.datasets], render, { deep: true });
onBeforeUnmount(() => chart?.destroy());
</script>

<template>
    <div>
        <div class="relative" :style="{ height: `${Math.max(180, labels.length * 44 + 60)}px` }">
            <canvas ref="canvas" role="img" :aria-label="summary" />
        </div>
        <table class="sr-only">
            <caption>{{ summary }}</caption>
            <thead>
                <tr>
                    <th scope="col">Item</th>
                    <th v-for="dataset in datasets" :key="dataset.label" scope="col">{{ dataset.label }}</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="(label, index) in labels" :key="label">
                    <th scope="row">{{ label }}</th>
                    <td v-for="dataset in datasets" :key="dataset.label">{{ formatValue(dataset.data[index]) }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
