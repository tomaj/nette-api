<script setup lang="ts">
// Pre-release notice. Remove this file and its line in Layout.vue when 3.6.0 is released.
import { useData, withBase } from 'vitepress'
const { lang } = useData()
</script>

<template>
  <div class="release-banner" role="note">
    <p v-if="lang.startsWith('sk')">
      Dokumentácia popisuje nevydanú <strong>3.6.0</strong>. Posledné vydanie je
      <a href="https://github.com/tomaj/nette-api/releases/tag/3.5.0">3.5.0</a>,
      funkcie „Od verzie 3.6.0“ v ňom nie sú (<a :href="withBase('/sk/guide/versioning')">viac</a>).
    </p>
    <p v-else>
      These docs cover the unreleased <strong>3.6.0</strong>. The latest release is
      <a href="https://github.com/tomaj/nette-api/releases/tag/3.5.0">3.5.0</a>;
      "Since 3.6.0" features are not in it (<a :href="withBase('/guide/versioning')">more</a>).
    </p>
  </div>
</template>

<style>
:root { --vp-layout-top-height: 40px; }
@media (max-width: 640px) { :root { --vp-layout-top-height: 56px; } }
.release-banner {
  position: fixed; top: 0; left: 0; right: 0; z-index: var(--vp-z-index-layout-top);
  height: var(--vp-layout-top-height); display: flex; align-items: center; justify-content: center;
  padding: 0 16px; font-size: 13px; line-height: 1.35;
  background: var(--vp-c-warning-soft); color: var(--vp-c-text-1); border-bottom: 1px solid var(--vp-c-divider);
}
.release-banner p { margin: 0; text-align: center; }
.release-banner a { color: var(--vp-c-brand-1); text-decoration: underline; }
</style>
