// https://nuxt.com/docs/api/configuration/nuxt-config
export default defineNuxtConfig({
  compatibilityDate: '2024-11-01',
  devtools: {
    enabled: true,

    timeline: {
      enabled: true,
    },
  },
  modules: [
    '@nuxt/ui',
    // '@nuxt/eslint',
    // '@nuxt/icon',
    '@nuxt/fonts',
    '@nuxt/image',
    '@pinia/nuxt',
    '@vueuse/nuxt',
    '@nuxtjs/apollo',
    '@hey-api/nuxt'
  ],
  heyApi: {
    config: {
      input: 'http://localhost:8000/api/docs.jsonopenapi', // nebo cesta k tvé OpenAPI specifikaci
      output: 'types/api',
    },
  },
  css: ['~/assets/css/main.css'],
  ssr: false,
})