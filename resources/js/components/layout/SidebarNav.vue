<script setup>
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import AppIcon from '../ui/AppIcon.vue';
import { visibleSections } from './navigation';

defineEmits(['navigate']);

const auth = useAuthStore();
const route = useRoute();
const sections = computed(() => visibleSections(auth.can));

function isActive(item) {
    const module = item.to.name.split('.')[0];

    return route.name === item.to.name || String(route.name ?? '').startsWith(`${module}.`);
}
</script>

<template>
    <nav aria-label="Navegação principal" class="flex-1 space-y-6 overflow-y-auto px-3 py-4">
        <div v-for="section in sections" :key="section.label">
            <p class="px-3 pb-2 text-[11px] font-semibold tracking-wider text-brand-200/70 uppercase">{{ section.label }}</p>
            <ul class="space-y-0.5">
                <li v-for="item in section.items" :key="item.label">
                    <RouterLink
                        :to="item.to"
                        class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors focus-visible:outline-2 focus-visible:outline-accent-500"
                        :class="isActive(item) ? 'bg-white/10 text-white' : 'text-brand-100 hover:bg-white/5 hover:text-white'"
                        :aria-current="isActive(item) ? 'page' : undefined"
                        @click="$emit('navigate')"
                    >
                        <AppIcon :name="item.icon" :size="18" class="shrink-0" :class="isActive(item) ? 'text-accent-500' : 'opacity-70'" />
                        {{ item.label }}
                    </RouterLink>
                </li>
            </ul>
        </div>
    </nav>
</template>
