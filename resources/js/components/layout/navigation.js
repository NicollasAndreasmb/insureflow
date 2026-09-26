/*
 * Itens da navegação principal. `permission` usa as chaves do mapa de
 * permissões devolvido por /api/user; itens sem permissão são consultas
 * liberadas a todos os perfis autenticados. A matriz de perfis continua
 * existindo apenas no backend.
 */
export const navigationSections = [
    {
        label: 'Visão geral',
        items: [{ label: 'Dashboard', to: { name: 'dashboard' }, icon: 'dashboard' }],
    },
    {
        label: 'Operação',
        items: [
            { label: 'Clientes', to: { name: 'clients.index' }, icon: 'users' },
            { label: 'Apólices', to: { name: 'policies.index' }, icon: 'shield' },
            { label: 'Parcelas', to: { name: 'installments.index' }, icon: 'cash' },
            { label: 'Sinistros', to: { name: 'claims.index' }, icon: 'alert' },
        ],
    },
    {
        label: 'Cadastros',
        items: [
            { label: 'Seguradoras', to: { name: 'insurers.index' }, icon: 'building' },
            { label: 'Modalidades', to: { name: 'products.index' }, icon: 'tag' },
        ],
    },
    {
        label: 'Administração',
        items: [
            { label: 'Usuários', to: { name: 'users.index' }, icon: 'user', permission: 'users.manage' },
            { label: 'Auditoria', to: { name: 'activity-logs.index' }, icon: 'clipboard', permission: 'activity_logs.view' },
        ],
    },
];

export function visibleSections(can) {
    return navigationSections
        .map((section) => ({
            ...section,
            items: section.items.filter((item) => !item.permission || can(item.permission)),
        }))
        .filter((section) => section.items.length > 0);
}
