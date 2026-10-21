# NCHire automated testing

The test harness uses a dedicated `nchire_e2e` database. `tests/setup_e2e_database.php` refuses a target database whose name does not end in `_e2e`, clones schema only from `nchire`, and seeds synthetic data. Application database connections keep `nchire` as their normal default and use `NCHIRE_DB_NAME` only when explicitly set by the test server.

## Prerequisites

- XAMPP MySQL/MariaDB running with the source `nchire` schema available.
- PHP on `PATH` (the XAMPP PHP 8.3 runtime was used).
- Node.js/npm.

Install once:

```powershell
npm ci
npx playwright install chromium firefox webkit
```

Run the complete browser/device suite:

```powershell
npm run test:e2e
```

Run the detailed Chromium regression only:

```powershell
npm run test:e2e:core
```

Run the cross-browser/responsive matrix only:

```powershell
npm run test:e2e:matrix
```

Run the isolated PHP integration suite:

```powershell
npm run test:php
```

Run the external Groq connectivity probe separately:

```powershell
npm run test:groq
```

Open the last Playwright HTML report:

```powershell
npm run test:e2e:report
```

## Synthetic accounts

| Role/fixture | Email | Password |
|---|---|---|
| Applicant | `applicant.e2e@example.test` | `ApplicantPass1!` |
| Pipeline applicant | `pipeline.e2e@example.test` | `ApplicantPass1!` |
| Incomplete applicant | `incomplete.e2e@example.test` | `ApplicantPass1!` |
| Restricted applicant | `restricted.e2e@example.test` | `ApplicantPass1!` |
| Administrator | `admin.e2e@example.test` | `AdminPass1!` |
| Secretary | `secretary.e2e@example.test` | `AdminPass1!` |
| Security-test Secretary | `security.secretary.e2e@example.test` | `AdminPass1!` |
| Computing Studies Dean | `dean.cs.e2e@example.test` | `AdminPass1!` |
| Education Dean | `dean.edu.e2e@example.test` | `AdminPass1!` |
| Inactive staff | `inactive.e2e@example.test` | `AdminPass1!` |

Test mode suppresses outbound account email and uses deterministic verification/reset values. Do not enable `NCHIRE_TEST_MODE` for a deployed application.

## Result interpretation

Known confirmed defects are encoded with Playwright's expected-failure annotation. A successful run therefore means the working behavior passed and the documented defects were still reproduced. Review `docs/testing/NCHIRE_SYSTEM_TEST_REPORT.md` for the ordinary-pass versus expected-failure breakdown and remediation list.
