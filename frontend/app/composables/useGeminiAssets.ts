export const useGeminiAssets = () => {
  useHead({
    link: [
      {
        rel: 'preconnect',
        href: 'https://fonts.googleapis.com',
      },
      {
        rel: 'preconnect',
        href: 'https://fonts.gstatic.com',
        crossorigin: '',
      },
      {
        rel: 'stylesheet',
        href: 'https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap',
      },
    ],
    script: [
      {
        key: 'gemini-phosphor-icons',
        src: 'https://unpkg.com/@phosphor-icons/web',
      },
    ],
  })
}
