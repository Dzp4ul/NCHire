const fs = require('fs');
const path = require('path');
const { chromium } = require('playwright');

const workspace = path.resolve(__dirname, '..');
const sourceDir = path.join(workspace, 'NCHIRE_DFD_Updated');
const outputDir = path.join(__dirname, 'renders');
fs.mkdirSync(outputDir, { recursive: true });

function diagrams(xml) {
  return [...xml.matchAll(/<diagram\b[^>]*>[\s\S]*?<\/diagram>/g)].map(match => match[0]);
}

const level0 = fs.readFileSync(path.join(sourceDir, 'NCHIRE DFD LVL 0(2).drawio'), 'utf8');
const combined = fs.readFileSync(path.join(sourceDir, 'DFD-LVL-1-AND-2 (1)(2).drawio'), 'utf8');
const combinedPages = diagrams(combined);
const inputs = [
  ['level0', level0],
  ['level1', `<mxfile host="app.diagrams.net">${combinedPages[0]}</mxfile>`],
  ['level2', `<mxfile host="app.diagrams.net">${combinedPages[1]}</mxfile>`],
];

async function render(browser, name, xml) {
  const page = await browser.newPage({ viewport: { width: 1400, height: 900 } });
  await page.setContent(`<!doctype html><html><head><script>
    window.events = [];
    window.addEventListener('message', event => {
      try {
        const data = typeof event.data === 'string' ? JSON.parse(event.data) : event.data;
        if (data && data.event) window.events.push(data);
      } catch (_) {}
    });
  </script></head><body style="margin:0"><iframe id="drawio" style="width:100vw;height:100vh;border:0" src="https://app.diagrams.net/?embed=1&ui=min&spin=1&proto=json"></iframe></body></html>`);
  await page.waitForFunction(() => window.events.some(event => event.event === 'init'), null, { timeout: 45000 });
  await page.evaluate(payload => {
    document.getElementById('drawio').contentWindow.postMessage(JSON.stringify({ action: 'load', xml: payload, autosave: 0 }), '*');
  }, xml);
  await page.waitForFunction(() => window.events.some(event => event.event === 'load'), null, { timeout: 45000 });
  await page.evaluate(() => {
    window.events = window.events.filter(event => event.event !== 'export');
    document.getElementById('drawio').contentWindow.postMessage(JSON.stringify({ action: 'export', format: 'png', scale: 1.25, border: 20, transparent: false }), '*');
  });
  await page.waitForFunction(() => window.events.some(event => event.event === 'export' && typeof event.data === 'string'), null, { timeout: 90000 });
  const dataUrl = await page.evaluate(() => window.events.find(event => event.event === 'export' && typeof event.data === 'string').data);
  const match = /^data:image\/png;base64,(.+)$/s.exec(dataUrl);
  if (!match) throw new Error(`Unexpected export result for ${name}`);
  const output = path.join(outputDir, `${name}.png`);
  fs.writeFileSync(output, Buffer.from(match[1], 'base64'));
  console.log(`${name}: ${fs.statSync(output).size} bytes`);
  await page.close();
}

(async () => {
  const browser = await chromium.launch({ headless: true, executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe' });
  try {
    for (const [name, xml] of inputs) await render(browser, name, xml);
  } finally {
    await browser.close();
  }
})().catch(error => {
  console.error(error.stack || error.message || String(error));
  process.exit(1);
});
