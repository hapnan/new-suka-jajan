#!/bin/sh
set -e

cd /var/www/html

# 1. Ensure writable and upload directories exist and are owned by www-data
mkdir -p \
    writable/cache \
    writable/logs \
    writable/session \
    writable/uploads \
    writable/debugbar \
    public/uploads/kuliner \
    public/uploads/thumbnails

chown -R www-data:www-data writable public/uploads
chmod -R 775 writable public/uploads

# 2. Ensure .env file exists (CodeIgniter 4 reads both .env and container environment variables)
if [ ! -f .env ] && [ -f env ]; then
    cp env .env
    chown www-data:www-data .env
fi

# 3. Normalize migration files inside the container if needed so fresh migrations succeed
MIGRATIONS_DIR="/var/www/html/app/Database/Migrations"
if [ -f "${MIGRATIONS_DIR}/2026-07-06-000007_CreateFavoritesTable.php" ] && [ -f "${MIGRATIONS_DIR}/2026-05-01-000007_CreateFavoritesTable.php" ]; then
    rm -f "${MIGRATIONS_DIR}/2026-07-06-000007_CreateFavoritesTable.php"
fi

if [ -f "${MIGRATIONS_DIR}/2026-07-06-000009_CreateOrdersTable.php" ]; then
    # Match users.id and kuliners.id (INT UNSIGNED) for InnoDB foreign key compatibility
    php -r '
        $f = "app/Database/Migrations/2026-07-06-000009_CreateOrdersTable.php";
        $c = file_get_contents($f);
        $c = preg_replace("/(\x27(user_id|kuliner_id)\x27\s*=>\s*\[\s*\x27type\x27\s*=>\s*)\x27BIGINT\x27,\s*\x27constraint\x27\s*=>\s*20,/s", "$1\x27INT\x27,", $c);
        file_put_contents($f, $c);
    '
fi

if [ -f "${MIGRATIONS_DIR}/2026-06-13-000010_CreateTransactionsTable.php" ]; then
    mv "${MIGRATIONS_DIR}/2026-06-13-000010_CreateTransactionsTable.php" \
       "${MIGRATIONS_DIR}/2026-07-06-000010_CreateTransactionsTable.php"
fi

# 4. Wait for MySQL database to be ready
if [ "${SKIP_DB_WAIT:-false}" != "true" ]; then
    echo "Waiting for MySQL database connection..."
    MAX_TRIES=30
    COUNT=0
    until php -r '
        $host = getenv("database_default_hostname") ?: getenv("database.default.hostname") ?: "db";
        $user = getenv("database_default_username") ?: getenv("database.default.username") ?: "sukajajan";
        $pass = getenv("database_default_password") ?: getenv("database.default.password") ?: "sukajajan_secret";
        $db   = getenv("database_default_database") ?: getenv("database.default.database") ?: "sukajajan";
        $port = (int)(getenv("database_default_port") ?: getenv("database.default.port") ?: 3306);
        mysqli_report(MYSQLI_REPORT_OFF);
        $conn = @new mysqli($host, $user, $pass, $db, $port);
        if ($conn->connect_errno) {
            exit(1);
        }
        $conn->close();
        exit(0);
    '; do
        COUNT=$((COUNT + 1))
        if [ "$COUNT" -ge "$MAX_TRIES" ]; then
            echo "Error: MySQL did not become ready in time."
            exit 1
        fi
        echo "MySQL unavailable - sleeping 2s (${COUNT}/${MAX_TRIES})..."
        sleep 2
    done
    echo "MySQL is up and accepting connections."

    # 5. Run database migrations
    echo "Running database migrations..."
    php spark migrate --all

    # 6. Seed initial data only if the users table is empty
    USER_COUNT=$(php -r '
        $host = getenv("database_default_hostname") ?: getenv("database.default.hostname") ?: "db";
        $user = getenv("database_default_username") ?: getenv("database.default.username") ?: "sukajajan";
        $pass = getenv("database_default_password") ?: getenv("database.default.password") ?: "sukajajan_secret";
        $db   = getenv("database_default_database") ?: getenv("database.default.database") ?: "sukajajan";
        $port = (int)(getenv("database_default_port") ?: getenv("database.default.port") ?: 3306);
        $conn = new mysqli($host, $user, $pass, $db, $port);
        $res = $conn->query("SELECT COUNT(*) AS cnt FROM users");
        $row = $res ? $res->fetch_assoc() : ["cnt" => 0];
        echo (int)$row["cnt"];
        $conn->close();
    ')

    if [ "${USER_COUNT}" -eq "0" ]; then
        echo "Database is empty. Running InitialSeeder..."
        php spark db:seed InitialSeeder
    else
        echo "Database already contains ${USER_COUNT} user(s); skipping InitialSeeder."
    fi

    # Fix permissions on any files created in writable/ during CLI migration/seeding
    chown -R www-data:www-data writable
fi

echo "Starting Apache..."
exec apache2-foreground
