import DefaultTheme from 'vitepress/theme'
import { inBrowser, useRoute } from 'vitepress'
import { nextTick, onMounted, watch } from 'vue'
import Layout from './Layout.vue'
import Diagram from './Diagram.vue'
import ProjectSource from './ProjectSource.vue'
import LinkCards from './LinkCards.vue'
import LayersFlow from './LayersFlow.vue'
import { enhanceTables } from './table-cards'
import './custom.css'
import { currentVersion } from '../../site-nav'

// An archived tree such as docs/2.x/ shows its own version; everything else is the current one.
function versionLabel(path: string): string {
  return path.match(/\/(\d+\.x)\//)?.[1] ?? currentVersion
}

function showVersion(path: string): void {
  const label = versionLabel(path)
  document.querySelectorAll('.VPNavBarMenu .text, .VPNavBarExtra .text').forEach((node) => {
    const text = node.textContent?.trim()
    if (text !== undefined && /^\d+\.x$/.test(text)) {
      node.textContent = label
    }
  })
}

export default {
  extends: DefaultTheme,
  Layout,
  enhanceApp({ app, router }) {
    app.component('Diagram', Diagram)
    app.component('ProjectSource', ProjectSource)
    app.component('LinkCards', LinkCards)
    app.component('LayersFlow', LayersFlow)
    if (!inBrowser) {
      return
    }
    router.onAfterRouteChanged = (path: string) => showVersion(path)
  },
  setup() {
    const route = useRoute()
    const apply = () =>
      nextTick(() => {
        showVersion(route.path)
        enhanceTables()
      })
    onMounted(apply)
    watch(() => route.path, apply)
  },
}
