import { useCookie } from '#app'

export function useAuth() {
    const token = useCookie<string | null>('token')  // přežije F5
    const user = useState<any | null>('user', () => null)
    const isAuthenticated = computed(() => !!token.value)

    const { $api } = useNuxtApp()

    async function login(email: string, password: string) {
        try {
            const { token: newToken } = await $api<{ token: string }>('/login', {
                method: 'POST',
                body: { email, password }
            })
            token.value = newToken
            await fetchUser()
            return true
        } catch (err) {
            console.error('Login failed:', err)
            return false
        }
    }

    async function logout() {
        token.value = null
        user.value = null
        await navigateTo('/login')
    }

    async function fetchUser() {
        if (!token.value) {
            user.value = null
            return
        }
        try {
            user.value = await $api('/user/me')
        } catch (err) {
            console.error('Failed to fetch user:', err)
            // token.value = null
            // user.value = null
        }
    }

    return { token, user, isAuthenticated, login, logout, fetchUser }
}
