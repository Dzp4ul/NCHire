$ErrorActionPreference = 'Stop'

$projectRoot = Split-Path -Parent $PSScriptRoot
Set-Location -LiteralPath $projectRoot

# The setup script refuses names without the _e2e suffix.
$env:NCHIRE_DB_NAME = 'nchire_e2e'
& php 'tests/setup_e2e_database.php'
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

$directTests = @(
    'tests/SalaryProjectionTest.php',
    'tests/DynamicApplicationRequirementsTest.php',
    'tests/ApplicationDocumentReuseTest.php',
    'tests/TeachingLoadWorkflowTest.php',
    'tests/ChatbotDataServiceTest.php',
    'tests/ChatbotServiceTest.php'
)

foreach ($testFile in $directTests) {
    & php $testFile
    if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }
}

$env:NCHIRE_TEST_MODE = '1'
$env:NCHIRE_TEST_BASE_URL = 'http://127.0.0.1:4174/FinalResearch%20-%20Copy'
$server = Start-Process -FilePath 'php' `
    -ArgumentList @('-S', '127.0.0.1:4174', '-t', '..') `
    -WorkingDirectory $projectRoot `
    -WindowStyle Hidden `
    -PassThru

try {
    $ready = $false
    for ($attempt = 0; $attempt -lt 30; $attempt++) {
        try {
            $response = Invoke-WebRequest -UseBasicParsing -Uri "$env:NCHIRE_TEST_BASE_URL/public/index.php" -TimeoutSec 2
            if ($response.StatusCode -eq 200) {
                $ready = $true
                break
            }
        } catch {
            Start-Sleep -Milliseconds 200
        }
    }
    if (-not $ready) { throw 'The isolated PHP test server did not become ready.' }

    & php 'tests/ProfileEducationCrudTest.php'
    if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }
} finally {
    if ($server -and -not $server.HasExited) {
        Stop-Process -Id $server.Id
    }
}
