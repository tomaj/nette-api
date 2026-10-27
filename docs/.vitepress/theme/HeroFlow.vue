<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue'
import { withBase } from 'vitepress'
import { useStrings } from './i18n'

const t = useStrings()
const formats = [0, 1, 2, 3]
const LOOP = 10000

// CSS animations start when an element is first shown, so switching between the wide and the narrow
// drawing would leave the packets out of step with the captions. Put every part on one clock.
const root = ref<HTMLElement | null>(null)
let observer: ResizeObserver | undefined
const sync = () => {
  const now = performance.now() % LOOP
  root.value?.getAnimations({ subtree: true }).forEach((a) => { a.currentTime = now })
}
onMounted(() => {
  requestAnimationFrame(sync)
  if ('ResizeObserver' in window && root.value) {
    observer = new ResizeObserver(() => requestAnimationFrame(sync))
    observer.observe(root.value)
  }
})
onBeforeUnmount(() => observer?.disconnect())
</script>

<!--
  The homepage picture: a request enters the Nette application, nette-api checks it and the handler
  answers with JSON, XML, a Latte template or another format.
  Two drawings share the same story: docs/diagrams/home-hero.json (700x400, wide screens) and
  docs/diagrams/home-hero-mobile.json (360x540, vertical, narrow screens). Each overlay uses the
  coordinates of its drawing. The captions are HTML below the picture, so they never cover it.
  The detailed pipeline is LayersFlow.vue on the Layers page.
-->
<template>
  <figure ref="root" class="hero-flow" role="img" :aria-label="t.heroAria">
    <div class="hf-pic hf-wide" aria-hidden="true">
      <img class="hf-art hf-light" :src="withBase('/diagrams/home-hero-light.svg')" alt="" width="700" height="400">
      <img class="hf-art hf-dark" :src="withBase('/diagrams/home-hero-dark.svg')" alt="" width="700" height="400">
      <svg class="hf-motion" viewBox="0 0 700 400">
        <rect class="hh hh-app" x="165" y="16" width="519" height="368" rx="16" />
        <rect class="hh hh-auth" x="220" y="153" width="80" height="34" rx="8" />
        <rect class="hh hh-valid" x="328" y="153" width="80" height="34" rx="8" />
        <rect class="hh hh-handler" x="468" y="110" width="172" height="170" rx="14" />
        <rect class="hh hh-client" x="20" y="140" width="110" height="130" rx="12" />
        <rect class="hh hh-fmt-0" x="204" y="268" width="70" height="28" rx="8" />
        <rect class="hh hh-fmt-1" x="282" y="268" width="60" height="28" rx="8" />
        <rect class="hh hh-fmt-2" x="350" y="268" width="56" height="28" rx="8" />
        <rect class="hh hh-fmt-3" x="414" y="268" width="34" height="28" rx="8" />
        <g class="hp hp-req"><rect x="-22" y="-11" width="44" height="22" rx="11" /><text y="4">GET</text></g>
        <g class="hp hp-res"><rect x="-22" y="-11" width="44" height="22" rx="11" /><text y="4">200</text></g>
      </svg>
    </div>
    <div class="hf-pic hf-narrow" aria-hidden="true">
      <img class="hf-art hf-light" :src="withBase('/diagrams/home-hero-mobile-light.svg')" alt="" width="360" height="540">
      <img class="hf-art hf-dark" :src="withBase('/diagrams/home-hero-mobile-dark.svg')" alt="" width="360" height="540">
      <svg class="hf-motion" viewBox="0 0 360 540">
        <rect class="hh hh-app" x="16" y="100" width="328" height="424" rx="16" />
        <rect class="hh hh-auth" x="100" y="170" width="80" height="34" rx="8" />
        <rect class="hh hh-valid" x="100" y="228" width="80" height="34" rx="8" />
        <rect class="hh hh-handler" x="56" y="318" width="248" height="110" rx="14" />
        <rect class="hh hh-client" x="40" y="12" width="290" height="64" rx="12" />
        <rect v-for="i in formats" :key="i" :class="['hh', `hh-fmt-${i}`]" x="188" :y="168 + i * 34" width="84" height="28" rx="8" />
        <g class="hp hp-req-m"><rect x="-22" y="-11" width="44" height="22" rx="11" /><text y="4">GET</text></g>
        <g class="hp hp-res-m"><rect x="-22" y="-11" width="44" height="22" rx="11" /><text y="4">200</text></g>
      </svg>
    </div>
    <figcaption class="hf-captions" aria-hidden="true">
      <span v-for="(step, i) in t.heroSteps" :key="i" :class="['hc', `hc-${i}`]">{{ step }}</span>
      <span class="hc-static">{{ t.heroStatic }}</span>
    </figcaption>
  </figure>
</template>

<style scoped>
.hero-flow {
  container-type: inline-size;
  width: 100%;
  max-width: 560px;
  margin: 0 auto;
  color: var(--vp-c-text-1);
}
.hf-pic { position: relative; }
.hf-art {
  display: block;
  width: 100%;
  height: auto;
  filter: drop-shadow(0 8px 20px rgba(15, 23, 42, 0.08));
}
.dark .hf-art { filter: drop-shadow(0 8px 24px rgba(0, 0, 0, 0.45)); }
.hf-dark { display: none; }
.dark .hf-light { display: none; }
.dark .hf-dark { display: block; }
.hf-narrow { display: none; max-width: 340px; margin: 0 auto; }

.hf-motion { z-index: 1; position: absolute; inset: 0; width: 100%; height: 100%; overflow: visible; }

.hf-captions {
  display: grid;
  min-height: 3em;
  margin-top: 10px;
  padding: 0 8px;
  font-family: 'Excalifont', 'Comic Sans MS', cursive;
  font-size: 15px;
  line-height: 1.4;
  text-align: center;
  color: var(--vp-c-text-2);
}
.hc, .hc-static { grid-area: 1 / 1; align-self: start; }

.hh { fill: none; stroke: var(--vp-c-brand-1); stroke-width: 4; opacity: 0; }
.hp rect { fill: var(--vp-c-brand-1); }
.hp text { fill: var(--vp-c-bg); font-size: 11px; font-family: var(--vp-font-family-mono); font-weight: 700; text-anchor: middle; }
.hp-res rect, .hp-res-m rect { fill: var(--vp-c-green-1, #10b981); }
.hc, .hp { opacity: 0; }
.hc-static { display: none; }

/* One loop is 10 seconds. */
.hh, .hp, .hc { animation-duration: 10s; animation-iteration-count: infinite; animation-timing-function: ease-in-out; }
.hh-app { animation-name: hh-app; }
.hh-auth { animation-name: hh-auth; }
.hh-valid { animation-name: hh-valid; }
.hh-handler { animation-name: hh-handler; }
.hh-client { animation-name: hh-client; }
.hh-fmt-0 { animation-name: hh-fmt-0; }
.hh-fmt-1 { animation-name: hh-fmt-1; }
.hh-fmt-2 { animation-name: hh-fmt-2; }
.hh-fmt-3 { animation-name: hh-fmt-3; }
.hp-req { animation-name: hp-req; }
.hp-res { animation-name: hp-res; }
.hp-req-m { animation-name: hp-req-m; }
.hp-res-m { animation-name: hp-res-m; }
.hc-0 { animation-name: hc-0; }
.hc-1 { animation-name: hc-1; }
.hc-2 { animation-name: hc-2; }
.hc-3 { animation-name: hc-3; }

@keyframes hp-req {
  0% { transform: translate(75px, 170px); opacity: 0; }
  3%, 8% { transform: translate(75px, 170px); opacity: 1; }
  17%, 23% { transform: translate(260px, 170px); opacity: 1; }
  28%, 34% { transform: translate(368px, 170px); opacity: 1; }
  43%, 52% { transform: translate(554px, 220px); opacity: 1; }
  55%, 100% { transform: translate(554px, 220px); opacity: 0; }
}
@keyframes hp-res {
  0%, 54% { transform: translate(500px, 250px); opacity: 0; }
  57% { transform: translate(500px, 250px); opacity: 1; }
  88%, 92% { transform: translate(75px, 250px); opacity: 1; }
  95%, 100% { transform: translate(75px, 250px); opacity: 0; }
}
@keyframes hp-req-m {
  0% { transform: translate(140px, 44px); opacity: 0; }
  3%, 8% { transform: translate(140px, 44px); opacity: 1; }
  17%, 23% { transform: translate(140px, 187px); opacity: 1; }
  28%, 34% { transform: translate(140px, 245px); opacity: 1; }
  43%, 52% { transform: translate(180px, 395px); opacity: 1; }
  55%, 100% { transform: translate(180px, 395px); opacity: 0; }
}
@keyframes hp-res-m {
  0%, 54% { transform: translate(302px, 300px); opacity: 0; }
  57% { transform: translate(302px, 300px); opacity: 1; }
  88%, 92% { transform: translate(302px, 44px); opacity: 1; }
  95%, 100% { transform: translate(302px, 44px); opacity: 0; }
}
@keyframes hh-app { 0%, 2% { opacity: 0; } 5%, 13% { opacity: 1; } 16%, 100% { opacity: 0; } }
@keyframes hh-auth { 0%, 16% { opacity: 0; } 18%, 24% { opacity: 1; } 26%, 100% { opacity: 0; } }
@keyframes hh-valid { 0%, 27% { opacity: 0; } 29%, 35% { opacity: 1; } 37%, 100% { opacity: 0; } }
@keyframes hh-handler { 0%, 42% { opacity: 0; } 44%, 55% { opacity: 1; } 58%, 100% { opacity: 0; } }
@keyframes hh-client { 0%, 87% { opacity: 0; } 89%, 93% { opacity: 1; } 96%, 100% { opacity: 0; } }
/* While the response travels, the formats light up in turn: JSON, XML, Latte, anything else. */
@keyframes hh-fmt-0 { 0%, 57% { opacity: 0; } 58.5%, 64% { opacity: 1; } 65.5%, 100% { opacity: 0; } }
@keyframes hh-fmt-1 { 0%, 65.5% { opacity: 0; } 67%, 72% { opacity: 1; } 73.5%, 100% { opacity: 0; } }
@keyframes hh-fmt-2 { 0%, 73.5% { opacity: 0; } 75%, 80% { opacity: 1; } 81.5%, 100% { opacity: 0; } }
@keyframes hh-fmt-3 { 0%, 81.5% { opacity: 0; } 83%, 88% { opacity: 1; } 89.5%, 100% { opacity: 0; } }
@keyframes hc-0 { 0%, 1% { opacity: 0; } 3%, 14% { opacity: 1; } 16%, 100% { opacity: 0; } }
@keyframes hc-1 { 0%, 15% { opacity: 0; } 17%, 39% { opacity: 1; } 41%, 100% { opacity: 0; } }
@keyframes hc-2 { 0%, 40% { opacity: 0; } 42%, 54% { opacity: 1; } 56%, 100% { opacity: 0; } }
@keyframes hc-3 { 0%, 55% { opacity: 0; } 57%, 93% { opacity: 1; } 95%, 100% { opacity: 0; } }

/* Below ~440px of width the wide drawing gets too small to read, so the vertical one takes over. */
@container (max-width: 439px) {
  .hf-wide { display: none; }
  .hf-narrow { display: block; }
}

@media (prefers-reduced-motion: reduce) {
  .hero-flow * { animation: none !important; }
  .hh, .hp, .hc { display: none; }
  .hc-static { display: block; }
}
</style>

<style>
/* The default hero image slot is a fixed square pulled up under the title; give this picture its natural size. */
.VPHero .image:has(.hero-flow) { margin: 0 0 8px; }
.VPHero .image-container:has(.hero-flow) { width: 100%; height: auto; transform: none; padding: 0 4px; }
@media (min-width: 960px) {
  .VPHero .image:has(.hero-flow) { margin: 0; }
  .VPHero .image-container:has(.hero-flow) { height: 100%; }
}
</style>
