import fs from 'node:fs';

const base = 'http://127.0.0.1:9233';
const target = await fetch(`${base}/json/new?${encodeURIComponent('http://localhost:3000/tmp_wizard_session.php')}`, { method: 'PUT' }).then(r => r.json());
const ws = new WebSocket(target.webSocketDebuggerUrl);
const pending = new Map();
const consoleErrors = [];
const requestErrors = [];
let id = 0;

ws.onmessage = ({ data }) => {
  const message = JSON.parse(data);
  if (message.id && pending.has(message.id)) {
    const { resolve, reject } = pending.get(message.id);
    pending.delete(message.id);
    if (message.error) reject(new Error(message.error.message));
    else resolve(message.result);
    return;
  }
  if (message.method === 'Runtime.exceptionThrown') {
    consoleErrors.push(message.params.exceptionDetails?.exception?.description || message.params.exceptionDetails?.text || 'Runtime exception');
  }
  if (message.method === 'Runtime.consoleAPICalled' && message.params.type === 'error') {
    consoleErrors.push(message.params.args.map(arg => arg.value ?? arg.description ?? '').join(' '));
  }
  if (message.method === 'Network.responseReceived' && message.params.response.status >= 400) {
    requestErrors.push(`${message.params.response.status} ${message.params.response.url}`);
  }
  if (message.method === 'Network.loadingFailed') {
    requestErrors.push(`${message.params.errorText} ${message.params.type}`);
  }
};

await new Promise((resolve, reject) => {
  ws.onopen = resolve;
  ws.onerror = reject;
});

function send(method, params = {}) {
  const messageId = ++id;
  ws.send(JSON.stringify({ id: messageId, method, params }));
  return new Promise((resolve, reject) => pending.set(messageId, { resolve, reject }));
}

const delay = ms => new Promise(resolve => setTimeout(resolve, ms));
async function evaluate(expression) {
  const result = await send('Runtime.evaluate', { expression, awaitPromise: true, returnByValue: true });
  if (result.exceptionDetails) throw new Error(result.exceptionDetails.exception?.description || result.exceptionDetails.text);
  return result.result.value;
}
async function screenshot(path) {
  const result = await send('Page.captureScreenshot', { format: 'png', fromSurface: true, captureBeyondViewport: false });
  fs.writeFileSync(path, Buffer.from(result.data, 'base64'));
}
async function metrics(label) {
  return evaluate(`(() => {
    const wizard = document.getElementById('applicationWizard');
    const body = wizard?.querySelector('.ui-wizard-body');
    const sidebar = wizard?.querySelector('.ui-wizard-sidebar');
    const content = wizard?.querySelector('.ui-wizard-content');
    const visibleSteps = [...document.querySelectorAll('.wizard-step')].filter(el => getComputedStyle(el).display !== 'none').map(el => el.id);
    const fileNames = [...document.querySelectorAll('#requirementsForm input[type="file"]')].map(el => el.name);
    const documentStatusCounts = [...document.querySelectorAll('#step1 .ui-document-card')].map(card => ({
      field: card.dataset.documentField,
      uploaded: card.querySelectorAll('.approved-file').length,
      resubmission: card.querySelectorAll('.resubmission-file-notice').length
    }));
    return {
      label: ${JSON.stringify(label)},
      href: location.href,
      readyState: document.readyState,
      wizardDisplay: wizard ? getComputedStyle(wizard).display : null,
      visibleSteps,
      gridColumns: body ? getComputedStyle(body).gridTemplateColumns : null,
      sidebar: sidebar ? { x: sidebar.getBoundingClientRect().x, width: sidebar.getBoundingClientRect().width } : null,
      content: content ? { x: content.getBoundingClientRect().x, width: content.getBoundingClientRect().width } : null,
      viewportWidth: innerWidth,
      documentOverflowX: document.documentElement.scrollWidth > document.documentElement.clientWidth,
      wizardOverflowX: wizard ? wizard.scrollWidth > wizard.clientWidth : null,
      title: document.querySelector('#wizardJobTitle span')?.textContent?.trim(),
      stepLabel: document.getElementById('wizardStepLabel')?.textContent?.trim(),
      formPresent: Boolean(document.getElementById('requirementsForm')),
      fileNames,
      documentStatusCounts,
      duplicateStatusCards: documentStatusCounts.filter(status => status.uploaded > 1 || status.resubmission > 1),
      reusableDisplayCount: document.querySelectorAll('#step1 .reusable-document-display').length,
      draftDisplayCount: document.querySelectorAll('#step1 .draft-file-display').length,
      functions: {
        start: typeof window.startApplicationWizard,
        view: typeof window.viewExistingApplication,
        show: typeof window.showWizard
      }
    };
  })()`);
}

await send('Page.enable');
await send('Runtime.enable');
await send('Network.enable');
await send('Emulation.setDeviceMetricsOverride', { width: 1920, height: 1080, deviceScaleFactor: 1, mobile: false });
await delay(2500);

await evaluate(`window.startApplicationWizard(84, 'CC 102 - Object Oriented Programming')`);
await delay(2200);
const desktopStep1 = await metrics('desktop-step-1');
await screenshot('C:/xampp/tmp/wizard-desktop-step1.png');

await evaluate(`window.viewExistingApplication(271)`);
await delay(2500);
const desktopExisting = await metrics('desktop-existing-stage');
await screenshot('C:/xampp/tmp/wizard-desktop-existing.png');

// The UI can receive the same application data from multiple workflow entry
// points. Rendering it again must update, not duplicate, each document status.
await evaluate(`window.currentApplicationData && window.addFileIndicatorsForApplication(window.currentApplicationData)`);
await delay(350);
const desktopRepeatedRender = await metrics('desktop-repeated-render');

await evaluate(`setStep(1)`);
await delay(400);
const desktopRequirementsReview = await metrics('desktop-requirements-review');
await screenshot('C:/xampp/tmp/wizard-desktop-requirements-review.png');

await send('Emulation.setDeviceMetricsOverride', { width: 390, height: 844, deviceScaleFactor: 1, mobile: true });
await delay(900);
const mobileExisting = await metrics('mobile-existing-stage');
await screenshot('C:/xampp/tmp/wizard-mobile-existing.png');

const result = {
  desktopStep1,
  desktopExisting,
  desktopRepeatedRender,
  desktopRequirementsReview,
  mobileExisting,
  consoleErrors: [...new Set(consoleErrors)],
  requestErrors: [...new Set(requestErrors)]
};
process.stdout.write(JSON.stringify(result, null, 2));
await fetch(`${base}/json/close/${target.id}`);
ws.close();
