const fs = require('fs');
const path = require('path');
const { chromium } = require('playwright');

const workspace = path.resolve(__dirname, '..');
const sourceDir = path.join(workspace, 'NCHIRE_Revised_DFD_Output', '.render_sources');
const pngDir = path.join(workspace, 'NCHIRE_Revised_DFD_Output', 'PNG');
fs.mkdirSync(pngDir, { recursive: true });

const names = {
  dfd_l0: 'NCHIRE_DFD_LEVEL_0_REVISED.png',
  dfd_l1: 'NCHIRE_DFD_LEVEL_1_REVISED.png',
  dfd_l2_p1: 'NCHIRE_DFD_LEVEL_2_1_0_ACCOUNTS_ACCESS.png',
  dfd_l2_p3: 'NCHIRE_DFD_LEVEL_2_3_0_TEACHING_LOADS.png',
  dfd_l2_p4: 'NCHIRE_DFD_LEVEL_2_4_0_APPLICATIONS.png',
  dfd_l2_p5: 'NCHIRE_DFD_LEVEL_2_5_0_SECRETARY_REVIEW.png',
  dfd_l2_p6: 'NCHIRE_DFD_LEVEL_2_6_0_DEAN_WORKFLOW.png',
  dfd_l2_p7: 'NCHIRE_DFD_LEVEL_2_7_0_NOTIFICATIONS.png',
  dfd_l2_p8: 'NCHIRE_DFD_LEVEL_2_8_0_ADMINISTRATION.png',
  dfd_l2_p9: 'NCHIRE_DFD_LEVEL_2_9_0_AI_CHATBOT.png',
};

async function render(browser, id, sourcePath, outputPath) {
  const page = await browser.newPage({ viewport: { width: 1280, height: 900 } });
  page.on('requestfailed', request => console.error('request failed:', request.url(), request.failure()?.errorText));
  page.on('console', message => console.error('browser console:', message.type(), message.text()));
  await page.setContent(`<!doctype html><html><head><script>
    window.__drawioEvents = [];
    window.addEventListener('message', function(event) {
      try {
        const data = typeof event.data === 'string' ? JSON.parse(event.data) : event.data;
        if (data && (data.event || data.action)) window.__drawioEvents.push(data);
      } catch (_) {}
    });
  </script></head><body style="margin:0"><iframe id="drawio" style="width:100vw;height:100vh;border:0" src="https://app.diagrams.net/?embed=1&ui=min&spin=1&proto=json"></iframe></body></html>`);

  try {
    await page.waitForFunction(() => window.__drawioEvents.some(e => e.event === 'init'), null, { timeout: 20000 });
  } catch (error) {
    console.error('drawio events:', await page.evaluate(() => window.__drawioEvents));
    console.error('frames:', page.frames().map(frame => frame.url()));
    const child = page.frames().find(frame => frame.url().includes('diagrams.net'));
    if (child) console.error('frame body:', (await child.locator('body').innerText().catch(() => '')).slice(0, 1000));
    await page.screenshot({ path: path.join(workspace, '.tmp_dfd_audit', `embed_${id}.png`) });
    throw error;
  }
  const xml = fs.readFileSync(sourcePath, 'utf8');
  await page.evaluate((payload) => {
    document.getElementById('drawio').contentWindow.postMessage(JSON.stringify({ action: 'load', xml: payload, autosave: 0 }), '*');
  }, xml);
  await page.waitForFunction(() => window.__drawioEvents.some(e => e.event === 'load'), null, { timeout: 90000 });

  await page.evaluate(() => {
    window.__drawioEvents = window.__drawioEvents.filter(e => e.event !== 'export');
    document.getElementById('drawio').contentWindow.postMessage(JSON.stringify({ action: 'export', format: 'png', scale: 1.5, border: 24, transparent: false }), '*');
  });
  await page.waitForFunction(() => window.__drawioEvents.some(e => e.event === 'export' && typeof e.data === 'string'), null, { timeout: 180000 });
  const dataUrl = await page.evaluate(() => {
    const event = window.__drawioEvents.find(e => e.event === 'export' && typeof e.data === 'string');
    return event.data;
  });
  const match = /^data:image\/png;base64,(.+)$/s.exec(dataUrl);
  if (!match) throw new Error(`Unexpected export response for ${id}`);
  fs.writeFileSync(outputPath, Buffer.from(match[1], 'base64'));
  await page.close();
  return fs.statSync(outputPath).size;
}

(async () => {
  const browser = await chromium.launch({ headless: true, executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe' });
  try {
    for (const [id, outputName] of Object.entries(names)) {
      const sourcePath = path.join(sourceDir, `${id}.drawio`);
      const outputPath = path.join(pngDir, outputName);
      const bytes = await render(browser, id, sourcePath, outputPath);
      console.log(`${outputName}\t${bytes}`);
    }
  } finally {
    await browser.close();
  }
})().catch(error => {
  console.error(error.stack || error.message || String(error));
  process.exit(1);
});
