# Deployment Guide - HRM & Invoicing System

## Deployment to cPanel/Shared Hosting

### Step 1: FTP Upload
1. Connect via FTP using cPanel credentials
2. Navigate to `public_html`
3. Upload all files

### Step 2: SSH Access
```bash
ssh username@145.79.210.246
cd public_html
```

### Step 3: Install Dependencies
```bash
npm install --production
```

### Step 4: Configure Environment
```bash
cp .env.example .env
# Edit .env with production values
```

### Step 5: Start with PM2
```bash
npm install -g pm2
pm2 start ecosystem.config.js
pm2 startup
pm2 save
```

### Step 6: Configure Subdomain
In cPanel:
1. Go to "Addon Domains" or "Subdomains"
2. Add: `hrm.aponshop.site`
3. Root: `public_html`

### Step 7: SSL Certificate
1. cPanel → AutoSSL
2. Ensure HTTPS is enabled

### Step 8: Verify
```
https://hrm.aponshop.site/health
```

## Production Checklist

- [ ] Database backups configured
- [ ] SSL certificate installed
- [ ] PM2 startup configured
- [ ] Environment variables set correctly
- [ ] Logs directory created
- [ ] Storage directory permissions
- [ ] Rate limiting enabled
- [ ] Monitoring setup
- [ ] Error logging configured
- [ ] Database password changed
- [ ] JWT secret changed
- [ ] Admin email verified

## Updates

To update code:
```bash
cd public_html
git pull origin main
npm install
pm2 restart hrm-api
```

## Monitoring

```bash
pm2 logs hrm-api
pm2 monit
```

## Backups

### Database Backup
```bash
mysqldump -u Adminmamun -p aponshop_hrm > backup.sql
```

### Restore
```bash
mysql -u Adminmamun -p aponshop_hrm < backup.sql
```

## Troubleshooting

### Application won't start
1. Check logs: `pm2 logs hrm-api`
2. Check database connection
3. Verify .env file
4. Check Node.js version: `node --version`

### Database connection issues
1. Verify credentials
2. Check MySQL is running
3. Test connection: `mysql -u Adminmamun -p`

### Port conflicts
```bash
lsof -i :3000
kill -9 <PID>
pm2 restart hrm-api
```

## Support

Email: contact@ma-engineering.com
