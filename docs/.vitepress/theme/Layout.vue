<script setup lang="ts">
import DefaultTheme from 'vitepress/theme'
import HeroFlow from './HeroFlow.vue'
import NotFound from './NotFound.vue'
import ReleaseBanner from './ReleaseBanner.vue' // remove at the 3.6.0 release
</script>

<template>
  <DefaultTheme.Layout>
    <template #layout-top>
      <ReleaseBanner />
    </template>
    <template #home-hero-image>
      <HeroFlow />
    </template>
    <template #not-found>
      <NotFound />
    </template>
  </DefaultTheme.Layout>
</template>
