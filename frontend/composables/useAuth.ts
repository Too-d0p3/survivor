import { useCookie } from '#app'
import {client} from '@/types/api/client.gen';
import type { GetCurrentUserResponse } from '@/types/api/index';
import { getCurrentUser } from '@/types/api/index';

export function useAuth() {
    const token = useCookie('token')
    const isAuthenticated = computed(() => !!token.value)
    const user = useState<GetCurrentUserResponse | null>('user', () => null);

    async function login(email: string, password: string) {
        try {
            const response = await $fetch<{ token: string }>('/api/login', {
                method: 'POST',
                body: { email, password },
            })
            token.value = response.token
            client.setConfig({
                headers: {
                    'Authorization': `Bearer ${token.value}`
                }
            })
            await fetchUser();
            return true
        } catch (error) {
            console.error('Login failed:', error)
            return false
        }
    }

    async function logout() {
        token.value = null
        client.setConfig({
            headers: {}
        })
        await navigateTo('/login')
        user.value = null;
    }

    async function fetchUser() {
        if (!token.value) {
            user.value = null;
            return;
        }
        try {
            const fetchedUser = await getCurrentUser({
                composable: "$fetch",
            });
            user.value = fetchedUser;
        } catch (error) {
            console.error('Failed to fetch user:', error);
            token.value = null;
            user.value = null;
        }
    }

    return {
        isAuthenticated,
        login,
        logout,
        user,
        fetchUser,
    }
}
