import http from './http';

export async function fetchHealth() {
    const { data } = await http.get('/health');

    return data;
}
