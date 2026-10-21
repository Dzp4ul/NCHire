import { execFileSync } from 'node:child_process';
import path from 'node:path';

export default async function globalSetup(): Promise<void> {
  const projectRoot = path.resolve(__dirname, '..', '..');
  execFileSync('php', [path.join(projectRoot, 'tests', 'setup_e2e_database.php')], {
    cwd: projectRoot,
    env: {
      ...process.env,
      NCHIRE_SOURCE_DB: 'nchire',
      NCHIRE_DB_NAME: 'nchire_e2e',
    },
    stdio: 'inherit',
  });
}
