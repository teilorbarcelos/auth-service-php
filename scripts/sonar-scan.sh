#!/usr/bin/env bash
set -uo pipefail

PROJECT_KEY="${1:-auth-service-php}"
PROJECT_NAME="${2:-Auth Service PHP}"
SONAR_TOKEN="${SONAR_TOKEN:-squ_733ffb0b40e059b7a5f353a7f16c2896cb8e7052}"
SONAR_HOST="${SONAR_HOST:-http://localhost:9000}"
SCANNER_BIN="${SCANNER_BIN:-/home/teilor/.sonar/native-sonar-scanner/sonar-scanner-6.2.1.4610-linux-x64/bin/sonar-scanner}"

export LANG="${LANG:-C.UTF-8}"
export LC_ALL="${LC_ALL:-C.UTF-8}"

echo "=========================================="
echo " SonarQube scan"
echo "  Project:    $PROJECT_KEY"
echo "  Name:       $PROJECT_NAME"
echo "=========================================="

rm -rf .sonarqube
rm -f coverage/clover.xml

echo ""
echo ">> Step 1/2: Build + tests with coverage"
./scripts/check-status.sh 2>/dev/null && docker compose exec -T app php -d pcov.enabled=1 vendor/bin/phpunit --coverage-clover coverage/clover.xml || echo "WARN: App not running; skipping tests"
set -e

echo ""
echo ">> Step 2/2: SonarQube scan"
if [ -f "coverage/clover.xml" ]; then
  rm -rf .sonarqube
  "$SCANNER_BIN" \
    -Dsonar.host.url="$SONAR_HOST" \
    -Dsonar.token="$SONAR_TOKEN" \
    -Dsonar.projectKey="$PROJECT_KEY" \
    -Dsonar.projectName="$PROJECT_NAME" \
    -Dsonar.sources="src" \
    -Dsonar.tests="tests" \
    -Dsonar.exclusions="**/vendor/**,**/database/migrations/**,**/storage/**,**/scripts/**,**/infra/**" \
    -Dsonar.php.coverage.reportPaths="coverage/clover.xml" || echo "WARN: scanner failed (exit $?)"
else
  echo "WARN: coverage/clover.xml not found; skipping coverage injection"
fi

echo ""
echo "=========================================="
echo " Done. Dashboard:"
echo "  $SONAR_HOST/dashboard?id=$PROJECT_KEY"
echo "=========================================="
