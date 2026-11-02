import { defineConfig } from 'vitepress';

export default defineConfig({
  base: '/laravel-smart-upload/',
  title: 'Laravel Smart Upload',
  description: 'Fast, zero-database file uploads for Laravel.',
  cleanUrls: true,
  lastUpdated: true,

  markdown: {
    lineNumbers: true
  },

  head: [
    ['link', { rel: 'icon', type: 'image/svg+xml', href: '/laravel-smart-upload/logo.svg' }],
    ['meta', { name: 'theme-color', content: '#ff2d20' }],
    ['meta', { property: 'og:type', content: 'website' }],
    ['meta', { property: 'og:title', content: 'Laravel Smart Upload' }],
    ['meta', { property: 'og:description', content: 'Fast, zero-database file uploads and chunk streaming for Laravel.' }]
  ],

  themeConfig: {
    siteTitle: 'Smart Upload',
    logo: '/logo.svg',

    outline: {
      level: [2, 3],
      label: 'On this page'
    },

    editLink: {
      pattern: 'https://github.com/kaziomarsh24/laravel-smart-upload/edit/main/docs/:path',
      text: 'Edit this page on GitHub'
    },

    docFooter: {
      prev: 'Previous page',
      next: 'Next page'
    },

    lastUpdated: {
      text: 'Last updated',
      formatOptions: {
        dateStyle: 'medium',
        timeStyle: 'short'
      }
    },

    nav: [
      { text: 'Docs', link: '/guide/getting-started' },
      { text: 'Cheat Sheet', link: '/guide/cheat-sheet' },
      {
        text: 'v1.3',
        items: [
          { text: 'Changelog', link: 'https://github.com/kaziomarsh24/laravel-smart-upload/blob/main/CHANGELOG.md' },
          { text: 'Packagist Release', link: 'https://packagist.org/packages/thekaziomar/laravel-smart-upload' },
          { text: 'GitHub Source', link: 'https://github.com/kaziomarsh24/laravel-smart-upload' }
        ]
      }
    ],

    sidebar: [
      {
        text: 'Getting Started',
        items: [
          { text: 'Why Smart Upload?', link: '/guide/getting-started' },
          { text: 'Installation', link: '/guide/installation' },
          { text: 'Quickstart', link: '/guide/quickstart' },
          { text: 'Method Cheat Sheet', link: '/guide/cheat-sheet' },
        ]
      },
      {
        text: 'Features',
        items: [
          { text: 'Fluent Upload API', link: '/guide/layer-1-fluent-engine' },
          { text: 'Security & Sanitization', link: '/guide/layer-2-security' },
          { text: 'DB Transaction Protection', link: '/guide/layer-3-rollback-shield' },
          { text: 'Resumable Video Chunks', link: '/guide/layer-4-chunk-upload' },
          { text: 'Two-Step SPA & Mobile', link: '/guide/two-step-upload' },
          { text: 'Model File Auto-Purge', link: '/guide/layer-6-eloquent-purge' },
          { text: 'Pruning & Maintenance', link: '/guide/layer-7-maintenance' },
        ]
      },
      {
        text: 'Reference',
        items: [
          { text: 'Configuration', link: '/guide/configuration' },
          { text: 'FAQ & Troubleshooting', link: '/guide/faq' },
        ]
      }
    ],

    search: {
      provider: 'local',
      options: {
        locales: {
          root: {
            translations: {
              button: {
                buttonText: 'Search docs...',
                buttonAriaLabel: 'Search docs'
              }
            }
          }
        }
      }
    },

    socialLinks: [
      { icon: 'github', link: 'https://github.com/kaziomarsh24/laravel-smart-upload' }
    ],

    footer: {
      message: 'Released under the MIT License.',
      copyright: 'Copyright © 2026 <a href="https://kaziomar.vercel.app" target="_blank" rel="noopener noreferrer" style="color: var(--vp-c-brand-1); font-weight: 500; text-decoration: underline; text-underline-offset: 3px;">Kazi Omar Faruk</a>'
    }
  }
});
