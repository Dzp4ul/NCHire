import { spawn, spawnSync } from 'node:child_process';
import http from 'node:http';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const projectRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const phpEnv = { ...process.env, NCHIRE_DB_NAME: 'nchire_e2e' };
const port = Number(process.env.NCHIRE_TEST_PORT || 4174);
const baseUrl = `http://127.0.0.1:${port}`;

function run(command, args, env = phpEnv) {
  const result = spawnSync(command, args, { cwd: projectRoot, env, stdio: 'inherit' });
  if (result.status !== 0) process.exit(result.status ?? 1);
}

run('php', ['tests/setup_e2e_database.php']);

for (const testFile of [
  'tests/SalaryProjectionTest.php',
  'tests/DynamicApplicationRequirementsTest.php',
  'tests/ApplicationDocumentReuseTest.php',
  'tests/TeachingLoadWorkflowTest.php',
  'tests/ChatbotDataServiceTest.php',
  'tests/ChatbotServiceTest.php',
]) {
  run('php', [testFile]);
}

const server = spawn('php', ['-S', `127.0.0.1:${port}`, '-t', projectRoot], {
  cwd: projectRoot,
  env: { ...phpEnv, NCHIRE_TEST_MODE: '1' },
  stdio: 'ignore',
});

function waitForServer(attempt = 0) {
  return new Promise((resolve, reject) => {
    const request = http.get(`${baseUrl}/public/index.php`, response => {
      response.resume();
      if (response.statusCode === 200) resolve();
      else retry();
    });
    request.on('error', retry);
    request.setTimeout(1000, () => request.destroy());
    function retry() {
      if (attempt >= 30) reject(new Error('The isolated PHP test server did not become ready.'));
      else setTimeout(() => waitForServer(attempt + 1).then(resolve, reject), 200);
    }
  });
}

try {
  await waitForServer();
  run('php', ['tests/ProfileEducationCrudTest.php'], {
    ...phpEnv,
    NCHIRE_TEST_MODE: '1',
    NCHIRE_TEST_BASE_URL: baseUrl,
  });
} finally {
  server.kill('SIGTERM');
}
