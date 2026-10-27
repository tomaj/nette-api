// Strings that the custom theme components render. Pages carry their own text;
// these are the labels inside components shared by the English and Slovak trees.
import { computed } from 'vue'
import { useData } from 'vitepress'

const en = {
  enlarge: 'Enlarge diagram',
  fullSize: 'Open the diagram at full size',
  close: 'Close diagram',
  runnableProject: 'Runnable project',
  recipeProject: (name: string) => `Every file below is imported from <code>examples/${name}</code>. Clone the repository, <code>cd examples/${name}</code>, run <code>composer install</code> and then <code>./e2e.sh</code>, which starts the PHP built-in server and checks every response shown on this page.`,
  keyFiles: 'Key files',
  viewSource: 'View source on GitHub',
  heroAria: 'A request from a client enters your Nette application. nette-api checks the token and the input, your handler runs and JSON, XML, a Latte template or another format goes back.',
  heroSteps: [
    'A request enters your Nette application',
    'nette-api checks the token and the input',
    'Your handler runs',
    'JSON, XML, Latte or another format goes back',
  ],
  heroStatic: 'client → Nette app → nette-api → handler → JSON · XML · Latte · …',
  flowAria: 'A request goes through ApiDecider, the authorization and the params to the handler, its output is checked, sent and logged. A request without a token stops at 401, one with wrong input at 400.',
  flowSteps: [
    'The request arrives, CORS headers go out first',
    'ApiDecider picks the handler by method, version and package',
    'Rate limit, then the authorization decides who may call',
    'Params are read and validated',
    'The handler runs your logic, gets clean values',
    'The output is checked against its JSON schema',
    'The response goes back to the client',
    'The API logger records the request',
    'No valid token: 401, the handler never runs',
    'Wrong input: 400 with the errors of each param',
  ],
  flowStatic: 'request, ApiDecider, authorization, params, handler, output, response, logger',
}

export type Strings = typeof en

const sk: Strings = {
  enlarge: 'Zväčšiť diagram',
  fullSize: 'Otvoriť diagram v plnej veľkosti',
  close: 'Zavrieť diagram',
  runnableProject: 'Spustiteľný projekt',
  recipeProject: (name) => `Každý súbor nižšie sa načítava z <code>examples/${name}</code>. Naklonujte repozitár, spustite <code>cd examples/${name}</code>, <code>composer install</code> a potom <code>./e2e.sh</code>, ktorý spustí vstavaný PHP server a overí každú odpoveď z tejto stránky.`,
  keyFiles: 'Hlavné súbory',
  viewSource: 'Zdrojový kód na GitHube',
  heroAria: 'Požiadavka od klienta príde do vašej Nette aplikácie. nette-api overí token a vstup, spustí sa váš handler a späť ide JSON, XML, Latte šablóna alebo iný formát.',
  heroSteps: [
    'Požiadavka príde do vašej Nette aplikácie',
    'nette-api overí token a vstup',
    'Spustí sa váš handler',
    'Klientovi sa vráti JSON, XML, Latte alebo iný formát',
  ],
  heroStatic: 'klient → Nette aplikácia → nette-api → handler → JSON · XML · Latte · …',
  flowAria: 'Požiadavka prejde cez ApiDecider, autorizáciu a parametre k handleru, jeho výstup sa overí, odošle a zaloguje. Požiadavka bez tokenu skončí na 401, so zlým vstupom na 400.',
  flowSteps: [
    'Príde požiadavka, najprv odídu CORS hlavičky',
    'ApiDecider vyberie handler podľa metódy, verzie a balíka',
    'Rate limit, potom autorizácia rozhodne, kto smie volať',
    'Parametre sa načítajú a overia',
    'Handler spustí vašu logiku s čistými hodnotami',
    'Výstup sa overí voči JSON schéme',
    'Odpoveď ide späť klientovi',
    'API logger zapíše požiadavku',
    'Bez platného tokenu: 401, handler sa nespustí',
    'Zlý vstup: 400 s chybami každého parametra',
  ],
  flowStatic: 'požiadavka, ApiDecider, autorizácia, parametre, handler, výstup, odpoveď, logger',
}

export function stringsFor(lang: string): Strings {
  return lang.startsWith('sk') ? sk : en
}

export function useStrings() {
  const { lang } = useData()
  return computed(() => stringsFor(lang.value))
}
