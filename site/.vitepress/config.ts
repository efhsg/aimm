import { defineConfig } from 'vitepress'

export default defineConfig({
  base: '/docs/',
  title: 'AIMM',
  description: 'Equity intelligence pipeline for smarter investment decisions',

  head: [
    ['link', { rel: 'preconnect', href: 'https://fonts.googleapis.com' }],
    ['link', { rel: 'preconnect', href: 'https://fonts.gstatic.com', crossorigin: '' }],
    ['link', {
      rel: 'stylesheet',
      href: 'https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500&family=Inter:wght@400;500;600;700&display=swap'
    }],
  ],

  themeConfig: {
    logo: '/logo.svg',
    nav: [
      { text: 'Overview', link: '/' },
      { text: 'Setup', link: '/setup' },
      { text: 'Pipeline', link: '/pipeline' },
      { text: 'Architecture', link: '/architecture' },
      { text: 'Data Quality', link: '/data-quality' },
      { text: 'Configuration', link: '/configuration' },
      { text: 'CLI Usage', link: '/cli-usage' },
      { text: 'Tech Stack', link: '/tech-stack' },
      { text: 'Admin UI', link: '/admin-ui/' },
      { text: 'Glossary', link: '/glossary' }
    ],

    sidebar: [
      {
        text: 'Guide',
        items: [
          { text: 'Overview', link: '/' },
          { text: 'Setup', link: '/setup' },
          { text: 'Pipeline', link: '/pipeline' },
          { text: 'Architecture', link: '/architecture' },
          { text: 'Data Quality', link: '/data-quality' },
          { text: 'Configuration', link: '/configuration' },
          { text: 'CLI Usage', link: '/cli-usage' },
          { text: 'Tech Stack', link: '/tech-stack' },
          { text: 'Glossary', link: '/glossary' }
        ]
      },
      {
        text: 'Reference',
        items: [
          { text: 'Validation Gates', link: '/validation-gates' },
          { text: 'Rating Logic', link: '/rating-logic' },
          { text: 'Outputs', link: '/outputs' },
          { text: 'Directory Structure', link: '/directory-structure' },
          { text: 'Squash Migrations', link: '/squash-migrations' }
        ]
      },
      {
        text: 'Admin UI',
        items: [
          { text: 'Overview', link: '/admin-ui/' },
          { text: 'Industries', link: '/admin-ui/peer-groups' },
          { text: 'Collection Policies', link: '/admin-ui/collection-policies' },
          { text: 'Collection Runs', link: '/admin-ui/collection-runs' },
          { text: 'Industry Configs', link: '/admin-ui/industry-configs' }
        ]
      }
    ],

    footer: {
      message: 'AIMM Documentation',
      copyright: 'Internal use only'
    },

    outline: {
      level: [2, 3]
    },

    search: {
      provider: 'local'
    }
  }
})
