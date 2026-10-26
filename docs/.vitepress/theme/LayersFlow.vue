<script setup lang="ts">
import { withBase } from 'vitepress'
import { useStrings } from './i18n'

const t = useStrings()
</script>

<!--
  One request through ApiPresenter, then one stopped at the authorization (401) and one at the params (400).
  The drawing is docs/diagrams/layers-flow.json; the moving parts share its 700x400 coordinates.
-->
<template>
  <figure class="layers-flow" role="img" :aria-label="t.flowAria">
    <img class="layers-flow-art layers-flow-light" :src="withBase('/diagrams/layers-flow-light.svg')" alt="" width="700" height="400">
    <img class="layers-flow-art layers-flow-dark" :src="withBase('/diagrams/layers-flow-dark.svg')" alt="" width="700" height="400">
    <svg class="layers-flow-motion" viewBox="0 0 700 400" aria-hidden="true">
      <rect class="nh nh-req" x="15" y="60" width="140" height="60" rx="10" />
      <rect class="nh nh-dec" x="190" y="60" width="140" height="60" rx="10" />
      <rect class="nh nh-auth" x="365" y="60" width="140" height="60" rx="10" />
      <rect class="nh nh-par" x="540" y="60" width="140" height="60" rx="10" />
      <rect class="nh nh-h" x="540" y="250" width="140" height="60" rx="10" />
      <rect class="nh nh-out" x="365" y="250" width="140" height="60" rx="10" />
      <rect class="nh nh-res" x="190" y="250" width="140" height="60" rx="10" />
      <rect class="nh nh-log" x="15" y="250" width="140" height="60" rx="10" />
      <rect class="nh nh-e401" x="395" y="162" width="80" height="34" rx="8" />
      <rect class="nh nh-e400" x="540" y="162" width="80" height="34" rx="8" />
      <g class="np np-a"><circle r="11" /><text y="5">200</text></g>
      <g class="np np-b np-bad"><circle r="11" /><text y="5">?</text></g>
      <g class="np np-c np-bad"><circle r="11" /><text y="5">!</text></g>
      <text class="nc nc-0" x="350" y="392">{{ t.flowSteps[0] }}</text>
      <text class="nc nc-1" x="350" y="392">{{ t.flowSteps[1] }}</text>
      <text class="nc nc-2" x="350" y="392">{{ t.flowSteps[2] }}</text>
      <text class="nc nc-3" x="350" y="392">{{ t.flowSteps[3] }}</text>
      <text class="nc nc-4" x="350" y="392">{{ t.flowSteps[4] }}</text>
      <text class="nc nc-5" x="350" y="392">{{ t.flowSteps[5] }}</text>
      <text class="nc nc-6" x="350" y="392">{{ t.flowSteps[6] }}</text>
      <text class="nc nc-7" x="350" y="392">{{ t.flowSteps[7] }}</text>
      <text class="nc nc-8" x="350" y="392">{{ t.flowSteps[8] }}</text>
      <text class="nc nc-9" x="350" y="392">{{ t.flowSteps[9] }}</text>
      <text class="nc-static" x="350" y="392">{{ t.flowStatic }}</text>
    </svg>
  </figure>
</template>

<style scoped>
.layers-flow {
  position: relative;
  width: min(100%, 700px);
  margin: 24px auto !important;
  margin: 0;
  color: var(--vp-c-text-1);
}
.layers-flow-art { display: block; width: 100%; height: auto; }
.layers-flow-dark { display: none; }
.dark .layers-flow-light { display: none; }
.dark .layers-flow-dark { display: block; }

.layers-flow-motion {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  font-family: 'Excalifont', 'Comic Sans MS', cursive;
}
.layers-flow-motion text { fill: currentColor; font-size: 15px; text-anchor: middle; }

.nh { fill: none; stroke: var(--vp-c-brand-1); stroke-width: 3; opacity: 0; }
.nh-e401, .nh-e400 { stroke: var(--vp-c-danger-1); }
.np circle { fill: var(--vp-c-brand-1); }
.np text { fill: var(--vp-c-bg) !important; font-size: 10px !important; font-family: var(--vp-font-family-mono); font-weight: 700; }
.np-bad circle { fill: var(--vp-c-danger-1); }
.nc { opacity: 0; }
.nc-static { display: none; }

/* One loop is 16 seconds: a request that passes, one without a token, one with wrong input. */
.np, .nh, .nc { animation-duration: 16s; animation-iteration-count: infinite; animation-timing-function: ease-in-out; }
.np { opacity: 0; }
.np-a { animation-name: np-a; }
.np-b { animation-name: np-b; }
.np-c { animation-name: np-c; }
.nh-req { animation-name: nh-req; }
.nh-dec { animation-name: nh-dec; }
.nh-auth { animation-name: nh-auth; }
.nh-par { animation-name: nh-par; }
.nh-h { animation-name: nh-h; }
.nh-out { animation-name: nh-out; }
.nh-res { animation-name: nh-res; }
.nh-log { animation-name: nh-log; }
.nh-e401 { animation-name: nh-e401; }
.nh-e400 { animation-name: nh-e400; }
.nc-0 { animation-name: nc-0; }
.nc-1 { animation-name: nc-1; }
.nc-2 { animation-name: nc-2; }
.nc-3 { animation-name: nc-3; }
.nc-4 { animation-name: nc-4; }
.nc-5 { animation-name: nc-5; }
.nc-6 { animation-name: nc-6; }
.nc-7 { animation-name: nc-7; }
.nc-8 { animation-name: nc-8; }
.nc-9 { animation-name: nc-9; }

@keyframes np-a {
  0%, 2% { transform: translate(85px, 90px); opacity: 1; }
  7%, 9% { transform: translate(260px, 90px); opacity: 1; }
  14%, 16% { transform: translate(435px, 90px); opacity: 1; }
  21%, 23% { transform: translate(610px, 90px); opacity: 1; }
  28%, 30% { transform: translate(610px, 280px); opacity: 1; }
  35%, 37% { transform: translate(435px, 280px); opacity: 1; }
  42%, 44% { transform: translate(260px, 280px); opacity: 1; }
  49%, 51% { transform: translate(85px, 280px); opacity: 1; }
  54%, 100% { transform: translate(85px, 280px); opacity: 0; }
}
@keyframes np-b {
  0%, 55.5% { transform: translate(85px, 90px); opacity: 0; }
  56%, 58% { transform: translate(85px, 90px); opacity: 1; }
  60%, 62% { transform: translate(260px, 90px); opacity: 1; }
  64%, 66% { transform: translate(435px, 90px); opacity: 1; }
  69%, 71% { transform: translate(435px, 179px); opacity: 1; }
  76%, 100% { transform: translate(435px, 179px); opacity: 0; }
}
@keyframes np-c {
  0%, 76.5% { transform: translate(85px, 90px); opacity: 0; }
  77%, 79% { transform: translate(85px, 90px); opacity: 1; }
  80%, 82% { transform: translate(260px, 90px); opacity: 1; }
  83%, 85% { transform: translate(435px, 90px); opacity: 1; }
  86%, 88% { transform: translate(610px, 90px); opacity: 1; }
  91%, 93% { transform: translate(580px, 179px); opacity: 1; }
  98%, 100% { transform: translate(580px, 179px); opacity: 0; }
}
@keyframes nh-req {
  0% { opacity: 0; }
  -0.5% { opacity: 0; }
  0%, 3% { opacity: 1; }
  4% { opacity: 0; }
  55.5% { opacity: 0; }
  56%, 59% { opacity: 1; }
  60% { opacity: 0; }
  76.5% { opacity: 0; }
  77%, 80% { opacity: 1; }
  81% { opacity: 0; }
  100% { opacity: 0; }
}
@keyframes nh-dec {
  0% { opacity: 0; }
  6.5% { opacity: 0; }
  7%, 10% { opacity: 1; }
  11% { opacity: 0; }
  59.5% { opacity: 0; }
  60%, 63% { opacity: 1; }
  64% { opacity: 0; }
  79.5% { opacity: 0; }
  80%, 83% { opacity: 1; }
  84% { opacity: 0; }
  100% { opacity: 0; }
}
@keyframes nh-auth {
  0% { opacity: 0; }
  13.5% { opacity: 0; }
  14%, 17% { opacity: 1; }
  18% { opacity: 0; }
  63.5% { opacity: 0; }
  64%, 67% { opacity: 1; }
  68% { opacity: 0; }
  82.5% { opacity: 0; }
  83%, 86% { opacity: 1; }
  87% { opacity: 0; }
  100% { opacity: 0; }
}
@keyframes nh-par {
  0% { opacity: 0; }
  20.5% { opacity: 0; }
  21%, 24% { opacity: 1; }
  25% { opacity: 0; }
  85.5% { opacity: 0; }
  86%, 89% { opacity: 1; }
  90% { opacity: 0; }
  100% { opacity: 0; }
}
@keyframes nh-h {
  0% { opacity: 0; }
  27.5% { opacity: 0; }
  28%, 31% { opacity: 1; }
  32% { opacity: 0; }
  100% { opacity: 0; }
}
@keyframes nh-out {
  0% { opacity: 0; }
  34.5% { opacity: 0; }
  35%, 38% { opacity: 1; }
  39% { opacity: 0; }
  100% { opacity: 0; }
}
@keyframes nh-res {
  0% { opacity: 0; }
  41.5% { opacity: 0; }
  42%, 45% { opacity: 1; }
  46% { opacity: 0; }
  100% { opacity: 0; }
}
@keyframes nh-log {
  0% { opacity: 0; }
  48.5% { opacity: 0; }
  49%, 52% { opacity: 1; }
  53% { opacity: 0; }
  100% { opacity: 0; }
}
@keyframes nh-e401 {
  0% { opacity: 0; }
  68.5% { opacity: 0; }
  69%, 76% { opacity: 1; }
  77% { opacity: 0; }
  100% { opacity: 0; }
}
@keyframes nh-e400 {
  0% { opacity: 0; }
  90.5% { opacity: 0; }
  91%, 98% { opacity: 1; }
  99% { opacity: 0; }
  100% { opacity: 0; }
}
@keyframes nc-0 {
  0% { opacity: 0; }
  -0.5% { opacity: 0; }
  0%, 6.5% { opacity: 1; }
  7.5% { opacity: 0; }
  100% { opacity: 0; }
}
@keyframes nc-1 {
  0% { opacity: 0; }
  6.5% { opacity: 0; }
  7%, 13.5% { opacity: 1; }
  14.5% { opacity: 0; }
  100% { opacity: 0; }
}
@keyframes nc-2 {
  0% { opacity: 0; }
  13.5% { opacity: 0; }
  14%, 20.5% { opacity: 1; }
  21.5% { opacity: 0; }
  100% { opacity: 0; }
}
@keyframes nc-3 {
  0% { opacity: 0; }
  20.5% { opacity: 0; }
  21%, 27.5% { opacity: 1; }
  28.5% { opacity: 0; }
  100% { opacity: 0; }
}
@keyframes nc-4 {
  0% { opacity: 0; }
  27.5% { opacity: 0; }
  28%, 34.5% { opacity: 1; }
  35.5% { opacity: 0; }
  100% { opacity: 0; }
}
@keyframes nc-5 {
  0% { opacity: 0; }
  34.5% { opacity: 0; }
  35%, 41.5% { opacity: 1; }
  42.5% { opacity: 0; }
  100% { opacity: 0; }
}
@keyframes nc-6 {
  0% { opacity: 0; }
  41.5% { opacity: 0; }
  42%, 48.5% { opacity: 1; }
  49.5% { opacity: 0; }
  100% { opacity: 0; }
}
@keyframes nc-7 {
  0% { opacity: 0; }
  48.5% { opacity: 0; }
  49%, 55% { opacity: 1; }
  56% { opacity: 0; }
  100% { opacity: 0; }
}
@keyframes nc-8 {
  0% { opacity: 0; }
  55.5% { opacity: 0; }
  56%, 76% { opacity: 1; }
  77% { opacity: 0; }
  100% { opacity: 0; }
}
@keyframes nc-9 {
  0% { opacity: 0; }
  76.5% { opacity: 0; }
  77%, 98% { opacity: 1; }
  99% { opacity: 0; }
  100% { opacity: 0; }
}

@media (prefers-reduced-motion: reduce) {
  .layers-flow-motion * { animation: none !important; }
  .np, .nh, .nc { display: none; }
  .nc-static { display: inline; }
}
</style>
