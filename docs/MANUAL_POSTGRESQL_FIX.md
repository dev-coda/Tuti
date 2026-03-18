# Manual PostgreSQL Fix Instructions

If PostgreSQL is not running or not accepting connections, follow these steps:

## Step 1: Check Available PostgreSQL Clusters

```bash
sudo -u postgres pg_lsclusters
```

This will show output like:
```
Ver Cluster Port Status Owner    Data directory              Log file
14  main    5432 down   postgres /var/lib/postgresql/14/main /var/log/postgresql/postgresql-14-main.log
```

Note the **Version** (e.g., `14`) and **Cluster** name (e.g., `main`).

## Step 2: Start PostgreSQL Cluster

Use the version and cluster name from Step 1:

```bash
sudo -u postgres pg_ctlcluster <VERSION> <CLUSTER> start
```

**Example:**
```bash
sudo -u postgres pg_ctlcluster 14 main start
```

## Step 3: Verify PostgreSQL is Running

Check the status:
```bash
sudo -u postgres pg_lsclusters
```

The Status should now show `online` instead of `down`.

## Step 4: Verify PostgreSQL Port and Listening

Check which port PostgreSQL is actually using:

```bash
sudo -u postgres pg_lsclusters
```

Look at the **Port** column. It might be `5432` (default) or `5433` (if another instance is using 5432).

Verify PostgreSQL is listening:
```bash
sudo netstat -tlnp | grep 5432
# OR
sudo ss -tlnp | grep 5432
# OR
sudo lsof -i :5432
```

Or more broadly:
```bash
sudo netstat -tlnp | grep postgres
# OR
sudo ss -tlnp | grep postgres
# OR
sudo lsof -i | grep postgres
```

**IMPORTANT:** If PostgreSQL is on port **5433** but your Laravel `.env` has `DB_PORT=5432`, you need to either:
- Update `.env` to use port 5433, OR
- Change PostgreSQL to use port 5432

## Step 5: Test Connection

```bash
# Test with pg_isready
sudo -u postgres pg_isready

# Test with psql
sudo -u postgres psql -c "SELECT version();"
```

## Step 6: Create Database (if it doesn't exist)

If you get an error like `database "tuti" does not exist`, create it:

```bash
# Connect to PostgreSQL as postgres user
sudo -u postgres psql

# Create the database (replace 'tuti' with your database name from .env)
CREATE DATABASE tuti;

# Create a user if needed (replace with your DB_USERNAME and DB_PASSWORD from .env)
CREATE USER your_username WITH PASSWORD 'your_password';

# Grant privileges
GRANT ALL PRIVILEGES ON DATABASE tuti TO your_username;

# Exit psql
\q
```

Or as a one-liner:
```bash
sudo -u postgres psql -c "CREATE DATABASE tuti;"
```

If you also need to create a user:
```bash
sudo -u postgres psql -c "CREATE USER your_username WITH PASSWORD 'your_password';"
sudo -u postgres psql -c "GRANT ALL PRIVILEGES ON DATABASE tuti TO your_username;"
```

## Step 7: Verify Laravel Can Connect

From your Laravel project directory:
```bash
php artisan db:show
```

If this works, your application should now be able to connect.

**If you need to run migrations:**
```bash
php artisan migrate
```

## Alternative: Using systemctl

If `pg_ctlcluster` doesn't work, try using systemctl with the versioned service:

```bash
# Find available PostgreSQL services
systemctl list-unit-files | grep postgresql

# Start the versioned service (replace with your version/cluster)
sudo systemctl start postgresql@14-main

# Check status
sudo systemctl status postgresql@14-main
```

## Troubleshooting

### If PostgreSQL won't start:

1. **Check logs:**
   ```bash
   sudo tail -f /var/log/postgresql/postgresql-14-main.log
   ```

2. **Check permissions:**
   ```bash
   sudo ls -la /var/lib/postgresql/14/main
   ```

3. **Check if another process is using port 5432:**
   ```bash
   sudo lsof -i :5432
   ```

### If connection still fails after starting:

1. **Check PostgreSQL configuration:**
   ```bash
   sudo -u postgres cat /etc/postgresql/14/main/postgresql.conf | grep listen_addresses
   ```
   
   Should show: `listen_addresses = '*'` or `listen_addresses = 'localhost'`

2. **Check pg_hba.conf for authentication:**
   ```bash
   sudo -u postgres cat /etc/postgresql/14/main/pg_hba.conf | grep -v "^#"
   ```

3. **Restart PostgreSQL:**
   ```bash
   sudo -u postgres pg_ctlcluster 14 main restart
   ```

## Database Disappears After Reboot (Data Not Persisting)

If your database exists but disappears after each reboot, PostgreSQL data is not persisting. This usually means:

1. **PostgreSQL data directory is on a temporary filesystem**
2. **PostgreSQL service is not starting automatically**
3. **Multiple PostgreSQL instances or misconfigured cluster**

### Diagnose the Issue

```bash
# 1. Check where PostgreSQL data is stored
sudo -u postgres pg_lsclusters

# Look at the "Data directory" column. It should be something like:
# /var/lib/postgresql/14/main
# NOT something like /tmp/... or /dev/...

# 2. Check if data directory is on a persistent filesystem
df -h /var/lib/postgresql

# Should show a real filesystem (ext4, xfs, etc.), not tmpfs

# 3. Check if PostgreSQL starts on boot
systemctl is-enabled postgresql
systemctl is-enabled postgresql@14-main  # Replace with your version

# Should show "enabled"

# 4. Check PostgreSQL service status
systemctl status postgresql
systemctl status postgresql@14-main
```

### Fix: Ensure PostgreSQL Starts on Boot

```bash
# Enable PostgreSQL to start on boot
sudo systemctl enable postgresql
sudo systemctl enable postgresql@14-main  # Replace with your version

# Verify
systemctl is-enabled postgresql
systemctl is-enabled postgresql@14-main
```

### Fix: Check for Multiple PostgreSQL Instances

```bash
# List all PostgreSQL clusters
sudo -u postgres pg_lsclusters

# If you see multiple clusters or clusters on different ports, you might be connecting to the wrong one
# Make sure your .env DB_PORT matches the Port column from pg_lsclusters
```

### Fix: Verify Data Directory Persistence

```bash
# Check if data directory exists and has data
sudo ls -la /var/lib/postgresql/14/main

# Should show many files including PG_VERSION, base/, global/, etc.

# Check filesystem type
df -T /var/lib/postgresql/14/main

# Should NOT be "tmpfs" - if it is, your data is in RAM and will be lost on reboot
```

### Fix: Recreate Database and Ensure Persistence

If data directory is correct but database still disappears:

```bash
# 1. Make sure PostgreSQL starts on boot
sudo systemctl enable postgresql@14-main

# 2. Create database
sudo -u postgres psql -c "CREATE DATABASE tuti;"

# 3. Verify it exists
sudo -u postgres psql -l | grep tuti

# 4. Restart PostgreSQL to test
sudo systemctl restart postgresql@14-main

# 5. Check database still exists after restart
sudo -u postgres psql -l | grep tuti

# 6. If it's gone, check logs
sudo tail -50 /var/log/postgresql/postgresql-14-main.log
```

### If Data Directory is on tmpfs (Temporary Filesystem)

If `df -T` shows `tmpfs` for the PostgreSQL data directory, you need to:

1. **Stop PostgreSQL:**
   ```bash
   sudo systemctl stop postgresql@14-main
   ```

2. **Move data to persistent location** (if not already there):
   ```bash
   # This is usually already in /var/lib/postgresql which should be persistent
   # But verify with: df -h /var/lib/postgresql
   ```

3. **Check if /var/lib is mounted correctly:**
   ```bash
   mount | grep /var/lib
   ```

If `/var/lib` is on tmpfs, you have a system configuration issue that needs to be fixed at the OS level.

## Fix Port Mismatch (PostgreSQL on 5433, Laravel expects 5432)

If `pg_isready` shows PostgreSQL is on port **5433** but Laravel is trying to connect to **5432**, you have two options:

### Option 1: Update Laravel .env to use port 5433 (Recommended)

```bash
# Edit .env file
nano .env

# Find DB_PORT and change it:
DB_PORT=5433

# Clear config cache
php artisan config:clear

# Test connection
php artisan db:show
```

### Option 2: Change PostgreSQL to use port 5432

```bash
# Find your PostgreSQL version and cluster
sudo -u postgres pg_lsclusters

# Edit PostgreSQL config (replace 14 with your version)
sudo nano /etc/postgresql/14/main/postgresql.conf

# Find and change:
port = 5432

# Restart PostgreSQL
sudo -u postgres pg_ctlcluster 14 main restart

# Verify
sudo -u postgres pg_isready
```

## Quick One-Liner Fix

If you know your PostgreSQL version and cluster name (usually `14 main` or `15 main`):

```bash
sudo -u postgres pg_ctlcluster 14 main start && sleep 2 && sudo -u postgres pg_isready && php artisan db:show
```

Replace `14 main` with your actual version and cluster name from `pg_lsclusters`.

**If PostgreSQL is on port 5433, update your .env first:**
```bash
sed -i 's/DB_PORT=5432/DB_PORT=5433/' .env && php artisan config:clear && php artisan db:show
```

**If database doesn't exist, create it:**
```bash
# Get database name from .env
DB_NAME=$(grep DB_DATABASE .env | cut -d '=' -f2)
DB_USER=$(grep DB_USERNAME .env | cut -d '=' -f2)
DB_PASS=$(grep DB_PASSWORD .env | cut -d '=' -f2)

# Create database
sudo -u postgres psql -c "CREATE DATABASE $DB_NAME;"

# Create user if needed
sudo -u postgres psql -c "CREATE USER $DB_USER WITH PASSWORD '$DB_PASS';" 2>/dev/null || true

# Grant privileges
sudo -u postgres psql -c "GRANT ALL PRIVILEGES ON DATABASE $DB_NAME TO $DB_USER;"

# Test connection
php artisan config:clear && php artisan db:show
```
