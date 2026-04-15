// https://nuxt.com/docs/api/configuration/nuxt-config
export default defineNuxtConfig({
  compatibilityDate: '2025-07-15',
  css: ['~/assets/css/main.css'],
  devtools: { enabled: process.env.NUXT_DEVTOOLS === 'true' },
  runtimeConfig: {
    public: {
      apiBase: process.env.NUXT_PUBLIC_API_BASE || 'http://localhost:8080',
    },
  },
})
