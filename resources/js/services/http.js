/*
 * Instância única do Axios usada por todos os serviços de API.
 *
 * A autenticação é feita pelo Sanctum em modo SPA: o navegador envia o cookie
 * de sessão (HttpOnly) automaticamente e o Axios copia o cookie XSRF-TOKEN para
 * o header X-XSRF-TOKEN em cada requisição. Por isso nenhum token é guardado
 * em localStorage. Os serviços de cada módulo importam esta instância em vez
 * de chamar o Axios diretamente, mantendo a configuração em um só lugar.
 */
import axios from 'axios';

const http = axios.create({
    baseURL: '/api',
    withCredentials: true,
    withXSRFToken: true,
    headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    },
});

export default http;
