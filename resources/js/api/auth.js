import http, { fetchCsrfCookie } from './http';

export async function login(credentials) {
    await fetchCsrfCookie();
    const { data } = await http.post('/login', credentials, { skipAuthRedirect: true });

    return data.data;
}

export async function logout() {
    await http.post('/logout', null, { skipAuthRedirect: true });
}

export async function fetchCurrentUser() {
    const { data } = await http.get('/user', { skipAuthRedirect: true });

    return data.data;
}
