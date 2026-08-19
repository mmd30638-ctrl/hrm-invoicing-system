# Installation Guide - HRM & Invoicing System

## Prerequisites
- Node.js >= 18.0.0
- MySQL 8.0+
- NPM >= 9.0.0
- Git

## Step 1: Clone Repository

```bash
cd public_html
git clone https://github.com/mmd30638-ctrl/hrm-invoicing-system.git .
```

## Step 2: Install Dependencies

```bash
npm install
```

## Step 3: Configure Environment

```bash
cp .env.example .env
```

Edit `.env` with your database credentials:
```env
DB_HOST=localhost
DB_USER=Adminmamun
DB_PASSWORD=Mdmamun@221
DB_NAME=aponshop_hrm
```

## Step 4: Database Setup

### Via phpMyAdmin (cPanel)
1. Open phpMyAdmin
2. Create new database: `aponshop_hrm`
3. Create database user: `Adminmamun` with password `Mdmamun@221`
4. Grant all privileges

### Via MySQL CLI
```sql
CREATE DATABASE aponshop_hrm;
CREATE USER 'Adminmamun'@'localhost' IDENTIFIED BY 'Mdmamun@221';
GRANT ALL PRIVILEGES ON aponshop_hrm.* TO 'Adminmamun'@'localhost';
FLUSH PRIVILEGES;
```

## Step 5: Start Server

### Development
```bash
npm run dev
```

### Production (PM2)
```bash
npm install -g pm2
pm2 start ecosystem.config.js
pm2 save
```

## Step 6: Verify Installation

Test API:
```
http://localhost:3000/health
```

You should see:
```json
{
  "status": "OK",
  "timestamp": "2024-01-01T10:00:00.000Z"
}
```

## Troubleshooting

### Port already in use
```bash
lsof -i :3000
kill -9 <PID>
```

### Database connection error
- Check database credentials in `.env`
- Verify database is running
- Check user privileges

### Module not found
```bash
rm -rf node_modules package-lock.json
npm install
```

## Default Admin Credentials
- **Email**: mmd30638@gmail.com
- **Password**: Mdmamun+221
- **Role**: Admin

## API Endpoints

- Health Check: `GET /health`
- API Base: `GET /api/v1/`
- Auth: `POST /api/v1/auth/login`

## Support

For issues, contact: contact@ma-engineering.com
