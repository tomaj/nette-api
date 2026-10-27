// Navigation and sidebars. Version 3.x is the current one and lives at the site root.
// When 4.x ships, the 3.x pages move under /3.x/ and get their own sidebar here (see guide/versioning).
import type { DefaultTheme } from 'vitepress'

export const currentVersion = '3.x'

type Labels = Record<string, string>

const en: Labels = {
  guide: 'Guide', examples: 'Examples', reference: 'Reference', faq: 'FAQ', changelog: 'Changelog', version: '3.x',
  start: 'Getting started', basics: 'Basics', concepts: 'Concepts', security: 'Security and traffic', docs: 'Documentation tools', more: 'More',
  'getting-started': 'Getting started', architecture: 'Architecture', handlers: 'Handlers', params: 'Input params', outputs: 'Outputs and responses',
  authorization: 'Authorization', 'rate-limit': 'Rate limiting', cors: 'CORS', logging: 'Logging', errors: 'Error handling',
  openapi: 'OpenAPI', console: 'Web console', links: 'Links and versions', versioning: 'Versions of this documentation',
  upgrade: 'Upgrade guide', contributing: 'Contributing', overview: 'Overview',
  'hello-world': 'Example: hello world', 'params-validation': 'Example: params and validation', auth: 'Example: authorization and rate limit', 'openapi-console': 'Example: OpenAPI and console', 'logging-errors': 'Example: logging and errors', 'cors-ex': 'Example: CORS',
  layers: 'Layers', 'api-decider': 'ApiDecider and endpoints', presenter: 'ApiPresenter pipeline',
  'custom-authorization': 'Example: custom authorization', 'json-body': 'Example: JSON and raw body', 'file-upload': 'Example: file upload', pagination: 'Example: pagination', versions: 'Example: API versions', 'rate-limit-ex': 'Example: rate limit', testing: 'Example: testing',
}
const sk: Labels = {
  guide: 'Príručka', examples: 'Príklady', reference: 'Referencia', faq: 'Časté otázky', changelog: 'Changelog', version: '3.x',
  start: 'Začíname', basics: 'Základy', concepts: 'Koncepty', security: 'Bezpečnosť a prevádzka', docs: 'Dokumentačné nástroje', more: 'Ďalšie',
  'getting-started': 'Začíname', architecture: 'Architektúra', handlers: 'Handlery', params: 'Vstupné parametre', outputs: 'Výstupy a odpovede',
  authorization: 'Autorizácia', 'rate-limit': 'Obmedzenie požiadaviek', cors: 'CORS', logging: 'Logovanie', errors: 'Spracovanie chýb',
  openapi: 'OpenAPI', console: 'Webová konzola', links: 'Odkazy a verzie', versioning: 'Verzie tejto dokumentácie',
  upgrade: 'Návod na upgrade', contributing: 'Prispievanie', overview: 'Prehľad',
  'hello-world': 'Príklad: hello world', 'params-validation': 'Príklad: parametre a validácia', auth: 'Príklad: autorizácia a obmedzenie požiadaviek', 'openapi-console': 'Príklad: OpenAPI a konzola', 'logging-errors': 'Príklad: logovanie a chyby', 'cors-ex': 'Príklad: CORS',
  layers: 'Vrstvy', 'api-decider': 'ApiDecider a endpointy', presenter: 'Spracovanie v ApiPresenteri',
  'custom-authorization': 'Príklad: vlastná autorizácia', 'json-body': 'Príklad: JSON a surové telo', 'file-upload': 'Príklad: nahrávanie súborov', pagination: 'Príklad: stránkovanie', versions: 'Príklad: verzie API', 'rate-limit-ex': 'Príklad: obmedzenie požiadaviek', testing: 'Príklad: testovanie',
}

function nav(l: Labels, p: string): DefaultTheme.NavItem[] {
  return [
    { text: l.guide, link: `${p}/guide/getting-started`, activeMatch: `^${p}/guide/` },
    { text: l.examples, link: `${p}/examples/`, activeMatch: `^${p}/examples/` },
    { text: l.reference, link: `${p}/api/reference` },
    { text: l.faq, link: `${p}/faq` },
    {
      text: l.version,
      items: [
        { text: l.changelog, link: `${p}/changelog` },
        { text: l.upgrade, link: `${p}/upgrade` },
        { text: l.versioning, link: `${p}/guide/versioning` },
      ],
    },
  ]
}

function sidebar(l: Labels, p: string): DefaultTheme.SidebarItem[] {
  const g = (k: string) => ({ text: l[k], link: `${p}/guide/${k}` })
  const e = (k: string, label = k) => ({ text: l[label], link: `${p}/examples/${k}` })
  return [
    { text: l.start, items: [g('getting-started')] },
    { text: l.basics, items: [g('handlers'), g('params'), g('outputs'), g('errors')] },
    { text: l.concepts, items: [g('architecture'), g('layers'), g('api-decider'), g('presenter')] },
    { text: l.security, items: [g('authorization'), g('rate-limit'), g('cors'), g('logging')] },
    { text: l.docs, items: [g('openapi'), g('console'), g('links')] },
    {
      text: l.examples,
      items: [
        { text: l.overview, link: `${p}/examples/` },
        e('hello-world'), e('params-validation'), e('auth'), e('cors', 'cors-ex'), e('openapi-console'), e('logging-errors'),
        e('custom-authorization'), e('json-body'), e('file-upload'), e('pagination'), e('versions'), e('rate-limit', 'rate-limit-ex'), e('testing'),
      ],
    },
    {
      text: l.more,
      items: [
        { text: l.reference, link: `${p}/api/reference` },
        { text: l.faq, link: `${p}/faq` },
        { text: l.changelog, link: `${p}/changelog` },
        { text: l.upgrade, link: `${p}/upgrade` },
        g('versioning'),
        { text: l.contributing, link: `${p}/contributing` },
      ],
    },
  ]
}

export const navEn = nav(en, '')
export const navSk = nav(sk, '/sk')
export const sidebarEn = sidebar(en, '')
export const sidebarSk = sidebar(sk, '/sk')
