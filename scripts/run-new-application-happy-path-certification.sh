#!/usr/bin/env bash
set -euo pipefail

repository_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
evidence_root="${repository_root}/storage/app/private/lifecycle-scenarios/new-application-happy-path/certification"
temporary_root="$(mktemp -d)"
sqlite_database="${temporary_root}/bpls-scenario-01-fresh.sqlite"
certification_run_token="$(php -r 'echo bin2hex(random_bytes(6));')"
postgres_fresh_database="bpls_scenario_01_cert_fresh_${certification_run_token}"
postgres_persistent_database="bpls_scenario_01_cert_persistent_${certification_run_token}"
postgres_host="${BPLS_CERT_PG_HOST:-127.0.0.1}"
postgres_port="${BPLS_CERT_PG_PORT:-5432}"
postgres_username="${BPLS_CERT_PG_USERNAME:-postgres}"
postgres_password="${BPLS_CERT_PG_PASSWORD:-}"

if [[ "${postgres_host}" != "127.0.0.1" && "${postgres_host}" != "localhost" ]]; then
    echo "Refusing Scenario 01 PostgreSQL certification outside local host." >&2
    exit 1
fi

if [[ "${postgres_port}" != "5432" ]]; then
    echo "Refusing Scenario 01 PostgreSQL certification outside the explicit local DBngin port." >&2
    exit 1
fi

for disposable_database in "${postgres_fresh_database}" "${postgres_persistent_database}"; do
    if [[ ! "${disposable_database}" =~ ^bpls_scenario_01_cert_(fresh|persistent)_[a-f0-9]{12}$ ]]; then
        echo "Refusing destructive database operation for unrecognized target [${disposable_database}]." >&2
        exit 1
    fi
done

mkdir -p "${evidence_root}"
touch "${sqlite_database}"

manage_postgres_database() {
    local operation="$1"
    local database_name="$2"

    CERT_OPERATION="${operation}" \
    CERT_DATABASE="${database_name}" \
    CERT_PG_HOST="${postgres_host}" \
    CERT_PG_PORT="${postgres_port}" \
    CERT_PG_USERNAME="${postgres_username}" \
    CERT_PG_PASSWORD="${postgres_password}" \
    php <<'PHP'
<?php

$operation = getenv('CERT_OPERATION');
$database = getenv('CERT_DATABASE');
$host = getenv('CERT_PG_HOST');
$port = getenv('CERT_PG_PORT');

if (! in_array($host, ['127.0.0.1', 'localhost'], true)
    || $port !== '5432'
    || ! preg_match('/^bpls_scenario_01_cert_(fresh|persistent)_[a-f0-9]{12}$/', $database)
    || ! in_array($operation, ['create', 'drop'], true)) {
    fwrite(STDERR, "Scenario 01 PostgreSQL safety gate refused the requested operation.\n");
    exit(1);
}

$pdo = new PDO(
    "pgsql:host={$host};port={$port};dbname=postgres",
    getenv('CERT_PG_USERNAME'),
    getenv('CERT_PG_PASSWORD'),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
);
$quotedDatabase = '"'.str_replace('"', '""', $database).'"';

if ($operation === 'drop') {
    $statement = $pdo->prepare('SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = ? AND pid <> pg_backend_pid()');
    $statement->execute([$database]);
    $pdo->exec("DROP DATABASE IF EXISTS {$quotedDatabase}");
} else {
    $statement = $pdo->prepare('SELECT 1 FROM pg_database WHERE datname = ?');
    $statement->execute([$database]);
    if ($statement->fetchColumn() !== false) {
        fwrite(STDERR, "Scenario 01 refused to reuse a pre-existing disposable database identity.\n");
        exit(1);
    }
    $pdo->exec("CREATE DATABASE {$quotedDatabase}");
}
PHP
}

cleanup() {
    manage_postgres_database drop "${postgres_fresh_database}" || true
    manage_postgres_database drop "${postgres_persistent_database}" || true
    rm -rf "${temporary_root}"
}
trap cleanup EXIT

run_artisan_sqlite() {
    APP_ENV=testing \
    APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' \
    DB_CONNECTION=sqlite \
    DB_DATABASE="${sqlite_database}" \
    "$@"
}

run_artisan_postgres() {
    local database_name="$1"
    shift

    DB_CONNECTION=pgsql \
    APP_ENV=testing \
    APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' \
    DB_HOST="${postgres_host}" \
    DB_PORT="${postgres_port}" \
    DB_DATABASE="${database_name}" \
    DB_USERNAME="${postgres_username}" \
    DB_PASSWORD="${postgres_password}" \
    "$@"
}

snapshot_postgres() {
    local database_name="$1"
    local output_path="$2"

    CERT_DATABASE="${database_name}" \
    CERT_SNAPSHOT_PATH="${output_path}" \
    CERT_PG_HOST="${postgres_host}" \
    CERT_PG_PORT="${postgres_port}" \
    CERT_PG_USERNAME="${postgres_username}" \
    CERT_PG_PASSWORD="${postgres_password}" \
    php <<'PHP'
<?php

$pdo = new PDO(
    sprintf('pgsql:host=%s;port=%s;dbname=%s', getenv('CERT_PG_HOST'), getenv('CERT_PG_PORT'), getenv('CERT_DATABASE')),
    getenv('CERT_PG_USERNAME'),
    getenv('CERT_PG_PASSWORD'),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
);
$quoteIdentifier = static fn (string $identifier): string => '"'.str_replace('"', '""', $identifier).'"';
$tables = $pdo->query("SELECT tablename FROM pg_catalog.pg_tables WHERE schemaname = 'public' ORDER BY tablename")->fetchAll(PDO::FETCH_COLUMN);
$snapshot = ['tables' => [], 'sequences' => []];

foreach ($tables as $table) {
    $rows = $pdo->query('SELECT * FROM '.$quoteIdentifier($table))->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$row) {
        ksort($row);
    }
    unset($row);
    usort($rows, static fn (array $left, array $right): int => json_encode($left, JSON_THROW_ON_ERROR) <=> json_encode($right, JSON_THROW_ON_ERROR));
    $snapshot['tables'][$table] = $rows;
}

$sequences = $pdo->query("SELECT sequencename FROM pg_catalog.pg_sequences WHERE schemaname = 'public' ORDER BY sequencename")->fetchAll(PDO::FETCH_COLUMN);
foreach ($sequences as $sequence) {
    $snapshot['sequences'][$sequence] = $pdo->query('SELECT last_value, is_called FROM '.$quoteIdentifier($sequence))->fetch(PDO::FETCH_ASSOC);
}

file_put_contents(getenv('CERT_SNAPSHOT_PATH'), json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL);
PHP
}

cd "${repository_root}"

run_artisan_sqlite php artisan migrate --force --no-interaction >/dev/null
run_artisan_sqlite php artisan bpls:install --json >"${evidence_root}/fresh-sqlite-install.json"
run_artisan_sqlite php artisan bpls:install --check --json >"${evidence_root}/fresh-sqlite-check.json"
run_artisan_sqlite php artisan bpls:lifecycle:run new-application-happy-path --json >"${evidence_root}/fresh-sqlite.json"
run_artisan_sqlite php artisan bpls:lifecycle:run new-application-happy-path >"${evidence_root}/fresh-sqlite-cli.txt"

manage_postgres_database create "${postgres_fresh_database}"
run_artisan_postgres "${postgres_fresh_database}" php artisan migrate --force --no-interaction >/dev/null
run_artisan_postgres "${postgres_fresh_database}" php artisan bpls:install --json >"${evidence_root}/fresh-postgresql-install.json"
snapshot_postgres "${postgres_fresh_database}" "${evidence_root}/fresh-postgresql-before-check.json"
run_artisan_postgres "${postgres_fresh_database}" php artisan bpls:install --check --json >"${evidence_root}/fresh-postgresql-check.json"
snapshot_postgres "${postgres_fresh_database}" "${evidence_root}/fresh-postgresql-after-check.json"
cmp "${evidence_root}/fresh-postgresql-before-check.json" "${evidence_root}/fresh-postgresql-after-check.json"
run_artisan_postgres "${postgres_fresh_database}" php artisan bpls:lifecycle:run new-application-happy-path --json >"${evidence_root}/fresh-postgresql.json"
run_artisan_postgres "${postgres_fresh_database}" php artisan bpls:lifecycle:run new-application-happy-path >"${evidence_root}/fresh-postgresql-cli.txt"

manage_postgres_database create "${postgres_persistent_database}"
run_artisan_postgres "${postgres_persistent_database}" php artisan migrate --force --no-interaction >/dev/null
run_artisan_postgres "${postgres_persistent_database}" php artisan bpls:install --json >"${evidence_root}/persistent-postgresql-install-first.json"
run_artisan_postgres "${postgres_persistent_database}" php artisan bpls:install --json >"${evidence_root}/persistent-postgresql-install-rerun.json"
snapshot_postgres "${postgres_persistent_database}" "${evidence_root}/persistent-postgresql-before-check.json"
run_artisan_postgres "${postgres_persistent_database}" php artisan bpls:install --check --json >"${evidence_root}/persistent-postgresql-check.json"
snapshot_postgres "${postgres_persistent_database}" "${evidence_root}/persistent-postgresql-after-check.json"
cmp "${evidence_root}/persistent-postgresql-before-check.json" "${evidence_root}/persistent-postgresql-after-check.json"
run_artisan_postgres "${postgres_persistent_database}" php artisan bpls:lifecycle:run new-application-happy-path --persist --json >"${evidence_root}/persistent-postgresql-first.json"
run_artisan_postgres "${postgres_persistent_database}" php artisan bpls:lifecycle:run new-application-happy-path --persist --json >"${evidence_root}/persistent-postgresql-rerun.json"

cmp "${evidence_root}/persistent-postgresql-first.json" "${evidence_root}/persistent-postgresql-rerun.json"

php -r '
$sqliteInstall = json_decode(file_get_contents($argv[1]), true, flags: JSON_THROW_ON_ERROR);
$postgresInstall = json_decode(file_get_contents($argv[2]), true, flags: JSON_THROW_ON_ERROR);
$persistentInstall = json_decode(file_get_contents($argv[3]), true, flags: JSON_THROW_ON_ERROR);
$persistentRerun = json_decode(file_get_contents($argv[4]), true, flags: JSON_THROW_ON_ERROR);
if (!$sqliteInstall["integrity"]["pass"] || !$postgresInstall["integrity"]["pass"]) { exit(1); }
if (!$sqliteInstall["zero_state"]["is_empty"] || !$postgresInstall["zero_state"]["is_empty"]) { exit(2); }
if ($sqliteInstall["fingerprints"] !== $postgresInstall["fingerprints"]) { exit(3); }
if ($persistentInstall["fingerprints"] !== $persistentRerun["fingerprints"]) { exit(4); }
if ($persistentInstall["zero_state"] !== $persistentRerun["zero_state"]) { exit(5); }
' "${evidence_root}/fresh-sqlite-install.json" "${evidence_root}/fresh-postgresql-install.json" "${evidence_root}/persistent-postgresql-install-first.json" "${evidence_root}/persistent-postgresql-install-rerun.json"

php -r '
$sqlite = json_decode(file_get_contents($argv[1]), true, flags: JSON_THROW_ON_ERROR);
$postgres = json_decode(file_get_contents($argv[2]), true, flags: JSON_THROW_ON_ERROR);
if ($sqlite["status"] !== "passed" || $postgres["status"] !== "passed") { exit(1); }
if ($sqlite["semantic_result_hash"] !== $postgres["semantic_result_hash"]) { exit(2); }
if ($sqlite["evaluation"]["grand_total_amount_cents"] !== 122000 || $postgres["evaluation"]["grand_total_amount_cents"] !== 122000) { exit(3); }
if ($sqlite["treasury_counter_check"]["assessment_id"] !== $sqlite["assessment"]["id"] || $postgres["treasury_counter_check"]["assessment_id"] !== $postgres["assessment"]["id"]) { exit(4); }
' "${evidence_root}/fresh-sqlite.json" "${evidence_root}/fresh-postgresql.json"

php -r '
$result = json_decode(file_get_contents($argv[1]), true, flags: JSON_THROW_ON_ERROR);
echo "Scenario 01 first-principles certification PASS\n";
echo "Semantic result: {$result["semantic_result_hash"]}\n";
echo "Grand Total: {$result["evaluation"]["grand_total_amount_cents"]} centavos\n";
echo "Evidence: {$argv[2]}\n";
' "${evidence_root}/fresh-postgresql.json" "${evidence_root}"
