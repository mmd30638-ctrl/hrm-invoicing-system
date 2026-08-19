# API Documentation - HRM & Invoicing System

## Base URL
```
https://hrm.aponshop.site/api/v1
```

## Authentication

All endpoints require JWT token in header:
```
Authorization: Bearer <token>
```

## Endpoints

### Authentication

#### Login
```
POST /auth/login
Body: { email, password }
Response: { token, user }
```

#### Register
```
POST /auth/register
Body: { firstName, lastName, email, password }
```

#### Logout
```
POST /auth/logout
```

### Companies

#### Get All Companies
```
GET /companies
Query: page, limit, search
Response: { data, total, page, limit }
```

#### Get Company
```
GET /companies/:id
```

#### Create Company
```
POST /companies
Body: { name, email, phone, address, city, country, industry }
```

#### Update Company
```
PUT /companies/:id
Body: { name, email, phone, ... }
```

#### Delete Company
```
DELETE /companies/:id
```

### Stores

#### Get All Stores
```
GET /stores
Query: page, limit, companyId
```

#### Get Store
```
GET /stores/:id
```

#### Create Store
```
POST /stores
Body: { companyId, name, manager, address, city }
```

#### Update Store
```
PUT /stores/:id
Body: { name, manager, address, city }
```

### Users

#### Get All Users
```
GET /users
Query: page, limit, companyId, role
```

#### Get User
```
GET /users/:id
```

#### Create User
```
POST /users
Body: { firstName, lastName, email, password, role, companyId }
```

#### Update User
```
PUT /users/:id
Body: { firstName, lastName, email, role }
```

### Invoices

#### Get All Invoices
```
GET /invoices
Query: page, limit, status, companyId
```

#### Get Invoice
```
GET /invoices/:id
```

#### Create Invoice
```
POST /invoices
Body: {
  companyId,
  storeId,
  customerName,
  customerEmail,
  items: [{ description, quantity, unitPrice }],
  totalAmount
}
```

#### Update Invoice
```
PUT /invoices/:id
Body: { customerName, customerEmail, status }
```

#### Get Invoice PDF
```
GET /invoices/:id/pdf
```

#### Get Invoice Image
```
GET /invoices/:id/image
```

### Sharing

#### Share Invoice with PIN
```
POST /sharing/share
Body: { invoiceId, recipientEmail }
Response: { pinCode, shareId }
```

#### Verify PIN & Access Invoice
```
POST /sharing/verify-pin
Body: { shareId, pinCode }
Response: { access, invoiceData }
```

### Dashboard

#### Get Dashboard Data
```
GET /dashboard
Response: { stats, recentInvoices, charts }
```

## Response Format

### Success
```json
{
  "success": true,
  "data": { ... },
  "message": "Operation successful"
}
```

### Error
```json
{
  "success": false,
  "message": "Error description",
  "status": 400
}
```

## Status Codes
- 200: Success
- 201: Created
- 400: Bad Request
- 401: Unauthorized
- 403: Forbidden
- 404: Not Found
- 500: Server Error

## Rate Limiting
- 100 requests per 15 minutes per IP

## Error Codes
- INVALID_CREDENTIALS: 401
- TOKEN_EXPIRED: 401
- INSUFFICIENT_PERMISSIONS: 403
- RESOURCE_NOT_FOUND: 404
- VALIDATION_ERROR: 400
- SERVER_ERROR: 500

## Examples

### Login
```bash
curl -X POST https://hrm.aponshop.site/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"mmd30638@gmail.com","password":"Mdmamun+221"}'
```

### Get Invoices
```bash
curl -X GET https://hrm.aponshop.site/api/v1/invoices \
  -H "Authorization: Bearer <token>"
```

## Support

Email: contact@ma-engineering.com
