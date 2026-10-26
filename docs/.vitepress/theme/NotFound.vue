<script setup lang="ts">
// Shown for every missing URL; GitHub Pages serves one 404.html for both locales, so it lists EN and SK links.
import { withBase } from 'vitepress'

const t = {
  en: { h: 'Page not found', p: 'This page does not exist or has moved. Try one of these:', l: [['Home', '/'], ['Getting started', '/guide/getting-started'], ['Examples', '/examples/'], ['Reference', '/api/reference'], ['FAQ', '/faq']] },
  sk: { h: 'Stránka sa nenašla', p: 'Táto stránka neexistuje alebo sa presunula. Skúste niektorú z týchto:', l: [['Úvod', '/sk/'], ['Začíname', '/sk/guide/getting-started'], ['Príklady', '/sk/examples/'], ['Referencia', '/sk/api/reference'], ['Časté otázky', '/sk/faq']] },
}
</script>

<template>
  <div class="na-404">
    <p class="code">404</p>
    <template v-for="(x, k) in t" :key="k">
      <section :lang="k">
        <h1>{{ x.h }}</h1>
        <p>{{ x.p }}</p>
        <ul><li v-for="[n, u] in x.l" :key="u"><a :href="withBase(u)">{{ n }}</a></li></ul>
      </section>
    </template>
  </div>
</template>

<style>
.na-404 { max-width: 640px; margin: 0 auto; padding: 64px 24px 96px; text-align: center; }
.na-404 .code { font-size: 64px; font-weight: 700; color: var(--vp-c-brand-1); line-height: 1; }
.na-404 h1 { margin: 24px 0 8px; font-size: 24px; font-weight: 600; }
.na-404 ul { list-style: none; padding: 0; display: flex; flex-wrap: wrap; gap: 8px 16px; justify-content: center; }
.na-404 a { color: var(--vp-c-brand-1); font-weight: 500; }
.na-404 section + section { margin-top: 32px; padding-top: 24px; border-top: 1px solid var(--vp-c-divider); }
</style>
