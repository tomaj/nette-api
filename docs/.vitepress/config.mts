import { defineConfig } from 'vitepress'
import { navEn, navSk, sidebarEn, sidebarSk } from '../site-nav'
import { seoHead, siteUrl } from './seo'

const base = process.env.DOCS_BASE || '/nette-api/'
// Pull request previews set DOCS_NOINDEX=1 so search engines index only the published site.
const noindex = process.env.DOCS_NOINDEX === '1'

const descriptionEn = 'Robust REST APIs inside your Nette application: versioned endpoints, authorization, validated params and outputs, CORS, rate limiting, logging and OpenAPI docs'
const descriptionSk = 'Robustné REST API priamo vo vašej Nette aplikácii: verzované endpointy, autorizácia, validované parametre aj výstupy, CORS, rate limiting, logovanie a OpenAPI dokumentácia'

// https://vitepress.dev/reference/site-config
export default defineConfig({
  srcExclude: ['public/**', 'diagrams/**', 'scripts/**'],
  title: 'Nette API',
  description: descriptionEn,
  base,

  head: [
    ['link', { rel: 'icon', href: `${base}favicon.ico`, sizes: '48x48' }],
    ['link', { rel: 'icon', type: 'image/svg+xml', href: `${base}favicon.svg` }],
    ['link', { rel: 'icon', type: 'image/png', sizes: '32x32', href: `${base}favicon-32.png` }],
    ['link', { rel: 'icon', type: 'image/png', sizes: '16x16', href: `${base}favicon-16.png` }],
    ['link', { rel: 'apple-touch-icon', sizes: '180x180', href: `${base}apple-touch-icon.png` }],
    ['link', { rel: 'manifest', href: `${base}site.webmanifest` }],
    ['meta', { name: 'theme-color', content: '#4f46e5' }],
    ...(noindex ? [['meta', { name: 'robots', content: 'noindex, nofollow' }] as [string, Record<string, string>]] : []),
  ],

  lastUpdated: true,
  markdown: {
    languageAlias: { latte: 'html', neon: 'yaml' },
  },

  sitemap: {
    hostname: siteUrl,
  },

  transformHead({ pageData, siteConfig }) {
    return seoHead(pageData, siteConfig.srcDir, pageData.relativePath.startsWith('sk/') ? descriptionSk : descriptionEn)
  },


  // English is the default locale at the site root; Slovak mirrors it under /sk/. See guide/versioning.
  locales: {
    root: {
      label: 'English',
      lang: 'en',
      description: descriptionEn,
    },
    sk: {
      label: 'Slovenčina',
      lang: 'sk',
      link: '/sk/',
      description: descriptionSk,
      themeConfig: {
        nav: navSk,
        sidebar: {
          '/sk/': sidebarSk,
        },
        outline: {
          label: 'Na tejto stránke',
        },
        docFooter: {
          prev: 'Predchádzajúca stránka',
          next: 'Ďalšia stránka',
        },
        editLink: {
          pattern: 'https://github.com/tomaj/nette-api/edit/master/docs/:path',
          text: 'Upraviť túto stránku na GitHube',
        },
        lastUpdated: {
          text: 'Posledná aktualizácia',
        },
        darkModeSwitchLabel: 'Vzhľad',
        lightModeSwitchTitle: 'Prepnúť na svetlý režim',
        darkModeSwitchTitle: 'Prepnúť na tmavý režim',
        sidebarMenuLabel: 'Ponuka',
        returnToTopLabel: 'Návrat na začiatok',
        langMenuLabel: 'Zmeniť jazyk',
        skipToContentLabel: 'Preskočiť na obsah',
        footer: {
          message: 'Vydané pod licenciou MIT.',
          copyright: 'Copyright © 2015-2026 Tomas Majer',
        },
        notFound: {
          code: '404',
          title: 'STRÁNKA SA NENAŠLA',
          quote: 'Ak nezmeníte smer a budete hľadať ďalej, môžete doraziť práve tam, kam mierite.',
          linkLabel: 'prejsť na úvod',
          linkText: 'Späť na úvod',
        },
      },
    },
  },

  themeConfig: {
    logo: { light: '/logo.svg', dark: '/logo-dark.svg', alt: 'Nette API' },

    nav: navEn,

    sidebar: {
      '/': sidebarEn,
    },

    socialLinks: [
      { icon: 'github', link: 'https://github.com/tomaj/nette-api' },
    ],

    editLink: {
      pattern: 'https://github.com/tomaj/nette-api/edit/master/docs/:path',
      text: 'Edit this page on GitHub',
    },

    search: {
      provider: 'local',
      options: {
        locales: {
          sk: {
            translations: {
              button: {
                buttonText: 'Hľadať',
                buttonAriaLabel: 'Hľadať',
              },
              modal: {
                displayDetails: 'Zobraziť podrobný zoznam',
                resetButtonTitle: 'Vymazať hľadanie',
                backButtonTitle: 'Zavrieť hľadanie',
                noResultsText: 'Žiadne výsledky pre',
                footer: {
                  selectText: 'vybrať',
                  selectKeyAriaLabel: 'enter',
                  navigateText: 'prejsť',
                  navigateUpKeyAriaLabel: 'šípka hore',
                  navigateDownKeyAriaLabel: 'šípka dole',
                  closeText: 'zavrieť',
                  closeKeyAriaLabel: 'escape',
                },
              },
            },
          },
        },
      },
    },

    footer: {
      message: 'Released under the MIT License.',
      copyright: 'Copyright © 2015-2026 Tomas Majer',
    },
  },
})
