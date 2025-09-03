export default defineNuxtRouteMiddleware((to, from) => {
    const token = useCookie('token');
    const publicPages = ['/login', '/login/register'] // Stránky přístupné nepřihlášeným

    // Pokud je uživatel přihlášen a snaží se jít na login/register, přesměruj na /
    if (token.value && publicPages.includes(to.path)) {
        return navigateTo('/')
    }

    // Pokud uživatel není přihlášen a snaží se jít na chráněnou stránku
    if (!token.value && !publicPages.includes(to.path)) {
        return navigateTo('/login')
    }

    // V ostatních případech (přihlášen na chráněné, nepřihlášen na veřejné) nic nedělej
})