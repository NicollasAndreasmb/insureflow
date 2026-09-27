import { describe, expect, it } from 'vitest';
import ActivityChanges from '@/components/activity/ActivityChanges.vue';
import { mountPage } from './support';

const normalize = (text) => text.replace(/\s+/g, ' ').trim();

describe('ActivityChanges', () => {
    it('shows capitalized labels, spaced values and formatted money and percentages', async () => {
        const { wrapper } = await mountPage(ActivityChanges, {
            props: {
                log: {
                    id: 1,
                    action: { value: 'updated', label: 'Alteração' },
                    subject_type: 'policy',
                    subject_id: 7,
                    changes: {
                        old: { premium: '2400.00', commission_rate: '15.00', notes: null },
                        new: { premium: '2600.50', commission_rate: '12.50', notes: 'Endosso' },
                    },
                },
            },
        });

        const visible = wrapper.findAll('li').map((item) => normalize(
            item.findAll('span:not(.sr-only)').map((span) => span.element.textContent).join(''),
        ));

        expect(visible).toEqual([
            'Prêmio: R$ 2.400,00 → R$ 2.600,50',
            'Commission rate: 15% → 12,5%',
            'Notes: — → Endosso',
        ]);
    });
});
