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

## Step 4: Verify PostgreSQL is Listening on Port 5432

```bash
sudo netstat -tlnp | grep 5432
# OR
sudo ss -tlnp | grep 5432
# OR
sudo lsof -i :5432
```

You should see PostgreSQL listening on port 5432.

## Step 5: Test Connection

```bash
# Test with pg_isready
sudo -u postgres pg_isready

# Test with psql
sudo -u postgres psql -c "SELECT version();"
```

## Step 6: Verify Laravel Can Connect

From your Laravel project directory:
```bash
php artisan db:show
```

If this works, your application should now be able to connect.

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

## Quick One-Liner Fix

If you know your PostgreSQL version and cluster name (usually `14 main` or `15 main`):

```bash
sudo -u postgres pg_ctlcluster 14 main start && sleep 2 && sudo -u postgres pg_isready && php artisan db:show
```

Replace `14 main` with your actual version and cluster name from `pg_lsclusters`.
