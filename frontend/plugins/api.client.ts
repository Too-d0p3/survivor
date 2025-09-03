import { defineNuxtPlugin, useCookie } from '#app'

export default defineNuxtPlugin(() => {
    const token = computed(() => {
        return useCookie<string | null>('token').value;
    })

    const api = $fetch.create({
        baseURL: '/api',
        onRequest({ options }) {
            // const token = useCookie<string | null>('token');
            if (token.value) {
                options.headers = {
                    ...options.headers,
                    Authorization: `Bearer ${token.value}`
                }
            }
        },
        onResponseError({ response }) {
            if (response.status === 401) {
                // const token = useCookie<string | null>('token')
                // token.value = null
            }
        }
    })

    return { provide: { api } }
})
