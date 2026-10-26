// Build the social card public/og.png, 1200 × 630: the logo and name on top, the hand-drawn
// flow (docs/diagrams/og-card.json) below. Run after `npm run diagrams`: node scripts/og-image.mjs
import { readFileSync } from 'node:fs'
import puppeteer from 'puppeteer-core'

const read = (path) => readFileSync(new URL(path, import.meta.url), 'utf8')
const logo = read('../public/logo.svg').replace('<svg', '<svg width="120" height="120"')
const flow = read('../public/diagrams/og-card-light.svg').replace('<svg', '<svg style="width:1100px;height:auto"')

const browser = await puppeteer.launch({ executablePath: process.env.CHROME_PATH || '/usr/bin/google-chrome', args: ['--no-sandbox'] })
const page = await browser.newPage()
await page.setViewport({ width: 1200, height: 630 })
await page.setContent(`<html><body style="margin:0;width:1200px;height:630px;background:#fff;font-family:Inter,Helvetica,Arial,sans-serif;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:28px">
  <div style="display:flex;align-items:center;gap:28px">${logo}
    <div><div style="font-size:84px;font-weight:800;color:#1e1b4b;line-height:1">Nette API</div>
    <div style="font-size:30px;color:#475569;margin-top:10px">Robust REST APIs inside your Nette application</div></div></div>
  ${flow}</body></html>`)
await page.screenshot({ path: new URL('../public/og.png', import.meta.url).pathname })
await browser.close()
console.log('public/og.png written')
