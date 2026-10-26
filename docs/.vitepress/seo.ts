// Per-page SEO: canonical URL, Open Graph and Twitter tags, and JSON-LD.
// Used by transformHead in config.mts.
import { existsSync, readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import type { HeadConfig, PageData } from 'vitepress'

const base = process.env.DOCS_BASE || '/nette-api/'
export const isPreview = base !== '/nette-api/'
export const siteUrl = `https://tomaj.github.io${base}`
export const ogImage = `${siteUrl}og.png`
const siteName = 'Nette API'
const repoUrl = 'https://github.com/tomaj/nette-api'

export const person = {
  '@type': 'Person',
  '@id': 'https://github.com/tomaj#person',
  name: 'Tomas Majer',
  url: 'https://github.com/tomaj',
}

export function pageUrl(relativePath: string): string {
  const path = relativePath.replace(/(^|\/)index\.md$/, '$1').replace(/\.md$/, '.html')
  return siteUrl + path
}

// English pages live at the site root, Slovak pages mirror them under sk/.
export function localeOf(relativePath: string): { prefix: string, lang: string, ogLocale: string } {
  return relativePath.startsWith('sk/')
    ? { prefix: 'sk/', lang: 'sk', ogLocale: 'sk_SK' }
    : { prefix: '', lang: 'en', ogLocale: 'en_US' }
}

// Section names for the breadcrumb, from the first path segment after the locale.
const sections: Record<string, Record<string, [string, string]>> = {
  '': {
    guide: ['Guide', 'guide/getting-started.html'],
    examples: ['Examples', 'examples/'],
    api: ['Reference', 'api/reference.html'],
  },
  'sk/': {
    guide: ['Príručka', 'guide/getting-started.html'],
    examples: ['Príklady', 'examples/'],
    api: ['Referencia', 'api/reference.html'],
  },
}

// The same page in the other locale, when it exists, for hreflang links.
export function alternates(relativePath: string, srcDir: string): { lang: string, href: string }[] {
  const { prefix } = localeOf(relativePath)
  const base = relativePath.slice(prefix.length)
  const pairs: [string, string][] = [['en', base], ['sk', `sk/${base}`]]
  const found = pairs.filter(([, path]) => existsSync(resolve(srcDir, path)))
  if (found.length < 2) return []
  return [
    ...found.map(([lang, path]) => ({ lang, href: pageUrl(path) })),
    { lang: 'x-default', href: pageUrl(base) },
  ]
}

// Same slugs as VitePress: accents dropped, other characters collapsed to dashes.
export function slug(text: string): string {
  return text.normalize('NFKD').replace(/[\u0300-\u036f]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '')
}

function breadcrumb(page: PageData, url: string) {
  const { prefix } = localeOf(page.relativePath)
  const home = siteUrl + prefix
  const names = sections[prefix]
  const path = page.relativePath.slice(prefix.length)
  const items: { name: string, item: string }[] = [{ name: siteName, item: home }]
  const section = names[path.split('/')[0]]
  if (section && home + section[1] !== url) items.push({ name: section[0], item: home + section[1] })
  if (url !== home) items.push({ name: page.title, item: url })
  return {
    '@type': 'BreadcrumbList',
    itemListElement: items.map((entry, i) => ({ '@type': 'ListItem', position: i + 1, name: entry.name, item: entry.item })),
  }
}

// Markdown to plain text, enough for JSON-LD answers.
export function plain(markdown: string): string {
  const text = markdown
    .replace(/```[\s\S]*?```/g, '')
    .replace(/^<<< .*$/gm, '')
    .replace(/!\[[^\]]*\]\([^)]*\)/g, '')
    .replace(/\[([^\]]+)\]\([^)]*\)/g, '$1')
  // Inline code is shown as is; only the text around it loses HTML tags, links and emphasis.
  return text
    .split(/(`[^`]*`)/)
    .map((part, i) => i % 2
      ? part.slice(1, -1)
      : part
        .replace(/<[^>]+>/g, '')
        .replace(/\*\*([^*]+)\*\*/g, '$1')
        .replace(/(^|\W)[*_]([^*_]+)[*_](?=\W|$)/g, '$1$2'))
    .join('')
    .replace(/\s+/g, ' ')
    .trim()
}

// Questions are the `###` headings under `## FAQ` (`## Časté otázky` in Slovak),
// or every `##` heading on faq.md.
export function faqEntries(source: string, relativePath: string): { q: string, a: string }[] {
  let section: string
  let level: string
  if (relativePath === 'faq.md' || relativePath === 'sk/faq.md') {
    section = source
    level = '## '
  } else {
    const heading = ['\n## FAQ\n', '\n## Časté otázky\n'].find((h) => source.includes(h))
    if (heading === undefined) return []
    section = source.slice(source.indexOf(heading) + heading.length)
    const next = section.search(/\n## /)
    if (next >= 0) section = section.slice(0, next)
    level = '### '
  }
  const parts = ('\n' + section).split('\n' + level).slice(1)
  return parts
    .map((part) => {
      const [q, ...rest] = part.split('\n')
      const body = rest.join('\n').split(/\n#{2,3} /)[0]
      return { q: plain(q), a: plain(body) }
    })
    .filter((e) => e.q && e.a)
}

// Numbered `## 1. Title` headings become HowTo steps.
function howToSteps(source: string, url: string) {
  const steps: { name: string, text: string, url: string }[] = []
  const re = /\n## (\d+)\. ([^\n]+)\n([\s\S]*?)(?=\n## |$)/g
  let match: RegExpExecArray | null
  while ((match = re.exec(source)) !== null) {
    const text = plain(match[3]).slice(0, 300)
    steps.push({ name: match[2].trim(), text: text || match[2].trim(), url: `${url}#_${match[1]}-${slug(match[2])}` })
  }
  return steps
}

const notFoundDescription = 'This Nette API documentation page does not exist. Go to Getting started, the examples, the reference or the FAQ, in English or Slovak.'

export function seoHead(page: PageData, srcDir: string, siteDescription: string): HeadConfig[] {
  if (page.isNotFound || page.relativePath === '404.md') {
    return [
      ['meta', { name: 'robots', content: 'noindex' }],
      ['meta', { name: 'description', content: notFoundDescription }],
    ]
  }
  if (!page.relativePath) return []
  const url = pageUrl(page.relativePath)
  const locale = localeOf(page.relativePath)
  const sk = locale.prefix === 'sk/'
  const isHome = page.frontmatter.layout === 'home'
  const homeTitle = sk ? 'Nette API – REST API knižnica pre Nette' : 'Nette API – REST API library for Nette'
  const title = isHome ? homeTitle : `${page.title} | ${siteName}`
  const description = page.description || page.frontmatter.description || siteDescription
  const source = readFileSync(resolve(srcDir, page.relativePath), 'utf8')
  const modified = page.lastUpdated ? new Date(page.lastUpdated).toISOString() : undefined

  const head: HeadConfig[] = [
    ['link', { rel: 'canonical', href: url }],
    ['meta', { property: 'og:type', content: isHome ? 'website' : 'article' }],
    ['meta', { property: 'og:site_name', content: siteName }],
    ['meta', { property: 'og:locale', content: locale.ogLocale }],
    ['meta', { property: 'og:locale:alternate', content: sk ? 'en_US' : 'sk_SK' }],
    ['meta', { property: 'og:title', content: title }],
    ['meta', { property: 'og:description', content: description }],
    ['meta', { property: 'og:url', content: url }],
    ['meta', { property: 'og:image', content: ogImage }],
    ['meta', { property: 'og:image:width', content: '1200' }],
    ['meta', { property: 'og:image:height', content: '630' }],
    ['meta', { property: 'og:image:alt', content: 'Nette API: request, ApiDecider, handler, JSON response' }],
    ['meta', { name: 'twitter:card', content: 'summary_large_image' }],
    ['meta', { name: 'twitter:title', content: title }],
    ['meta', { name: 'twitter:description', content: description }],
    ['meta', { name: 'twitter:image', content: ogImage }],
    ['meta', { name: 'author', content: person.name }],
  ]
  if (isPreview) head.push(['meta', { name: 'robots', content: 'noindex, nofollow' }])
  for (const alt of alternates(page.relativePath, srcDir)) {
    head.push(['link', { rel: 'alternate', hreflang: alt.lang, href: alt.href }])
  }
  if (modified && !isHome) head.push(['meta', { property: 'article:modified_time', content: modified }])

  const logo = { '@type': 'ImageObject', '@id': `${siteUrl}#logo`, url: `${siteUrl}icon-512.png`, contentUrl: `${siteUrl}icon-512.png`, width: 512, height: 512, caption: siteName }
  const website = { '@type': 'WebSite', image: { '@id': logo['@id'] }, '@id': `${siteUrl}#website`, name: siteName, url: siteUrl, inLanguage: ['en', 'sk'], publisher: { '@id': person['@id'] } }
  const graph: Record<string, unknown>[] = [person, logo, website]

  if (isHome) {
    graph.push({
      '@type': ['SoftwareSourceCode', 'SoftwareApplication'],
      '@id': `${siteUrl}#software`,
      name: 'tomaj/nette-api',
      description: siteDescription,
      url: siteUrl,
      codeRepository: repoUrl,
      programmingLanguage: { '@type': 'ComputerLanguage', name: 'PHP' },
      runtimePlatform: 'PHP 8.3+',
      applicationCategory: 'DeveloperApplication',
      operatingSystem: 'Linux, macOS, Windows',
      license: 'https://opensource.org/licenses/MIT',
      softwareVersion: '3.x',
      author: { '@id': person['@id'] },
      maintainer: { '@id': person['@id'] },
      image: [{ '@id': `${siteUrl}#logo` }, ogImage],
      offers: { '@type': 'Offer', price: '0', priceCurrency: 'USD' },
    })
  } else {
    graph.push({
      '@type': 'TechArticle',
      '@id': `${url}#article`,
      headline: page.title,
      description,
      url,
      mainEntityOfPage: url,
      inLanguage: locale.lang,
      image: ogImage,
      author: { '@id': person['@id'] },
      publisher: { '@id': person['@id'] },
      isPartOf: { '@id': `${siteUrl}#website` },
      about: { '@type': 'SoftwareSourceCode', name: 'tomaj/nette-api', codeRepository: repoUrl },
      proficiencyLevel: page.relativePath.slice(locale.prefix.length).startsWith('examples/') ? 'Beginner' : 'Expert',
      ...(modified ? { dateModified: modified } : {}),
    })
    graph.push(breadcrumb(page, url))
  }

  const faq = faqEntries(source, page.relativePath)
  if (faq.length > 0) {
    graph.push({
      '@type': 'FAQPage',
      '@id': `${url}#faq`,
      url,
      mainEntity: faq.map((e) => ({ '@type': 'Question', name: e.q, acceptedAnswer: { '@type': 'Answer', text: e.a } })),
    })
  }

  const steps = page.frontmatter.howto ? howToSteps(source, url) : []
  if (steps.length > 0) {
    graph.push({
      '@type': 'HowTo',
      '@id': `${url}#howto`,
      name: page.title,
      description,
      totalTime: page.frontmatter.howto.totalTime,
      tool: (page.frontmatter.howto.tools ?? []).map((name: string) => ({ '@type': 'HowToTool', name })),
      step: steps.map((s, i) => ({ '@type': 'HowToStep', position: i + 1, name: s.name, text: s.text, url: s.url })),
    })
  }


  head.push(['script', { type: 'application/ld+json' }, JSON.stringify({ '@context': 'https://schema.org', '@graph': graph })])
  return head
}
