# AHT Talent – Job API

**Base URL:** `https://t.arrowhitech.com/wp-json/aht/v1`

---

## Authentication

All requests require the header:

```
X-API-Key: <your-api-key>
```

### Generate API Key (one-time, run on server)

```bash
ssh -p 22 a85f1195_1@9233cb4159.nxcli.io
cd /home/a85f1195/public_html
wp eval "
\$key = bin2hex(random_bytes(32));
update_option('aht_api_key', \$key);
echo 'API Key: ' . \$key . PHP_EOL;
"
```

Save the key — it cannot be retrieved later. To reset:

```bash
wp option update aht_api_key "your-new-key-here"
```

---

## Departments

| Value (use in API)    |
|-----------------------|
| `IT`                  |
| `BFSI`                |
| `Sales/Marketing`     |
| `BackOffice`          |
| `Akdemy`              |
| `Remote/Hybrid/Expat` |

---

## Endpoints

### POST /jobs — Create a job

```http
POST https://t.arrowhitech.com/wp-json/aht/v1/jobs
X-API-Key: your-api-key
Content-Type: application/json
```

**Request body:**

| Field        | Required | Type                | Description                               |
|-------------|----------|---------------------|-------------------------------------------|
| `title`      | ✅       | string              | Job title                                 |
| `content`    |          | string (HTML)       | Job description — HTML tags allowed       |
| `department` |          | string              | One of the department values above        |
| `salary`     |          | string              | e.g. `"$2000 - $3000"` or `"Negotiable"` |
| `location`   |          | string              | Work location                             |
| `deadline`   |          | string `DD/MM/YYYY` | Application deadline                      |
| `apply_url`  |          | URL                 | External application link (if any)        |
| `status`     |          | `publish`\|`draft`  | Default: `publish`                        |

**Example:**

```json
{
  "title": "Senior Backend Engineer",
  "content": "<p>Job description...</p>",
  "department": "IT",
  "salary": "$2000 - $3000",
  "location": "Hanoi or Remote",
  "deadline": "31/07/2026",
  "apply_url": "https://hr.arrowhitech.com/apply/123",
  "status": "publish"
}
```

**Response 201:**

```json
{
  "id": 42,
  "title": "Senior Backend Engineer",
  "content": "<p>Job description...</p>",
  "status": "publish",
  "slug": "senior-backend-engineer",
  "url": "https://t.arrowhitech.com/jobs/senior-backend-engineer/",
  "created_at": "2026-06-19T10:00:00",
  "updated_at": "2026-06-19T10:00:00",
  "department": "IT",
  "salary": "$2000 - $3000",
  "location": "Hanoi or Remote",
  "deadline": "31/07/2026",
  "apply_url": "https://hr.arrowhitech.com/apply/123"
}
```

---

### GET /jobs — List jobs

```http
GET https://t.arrowhitech.com/wp-json/aht/v1/jobs
X-API-Key: your-api-key
```

**Query parameters:**

| Param        | Default   | Description          |
|-------------|-----------|----------------------|
| `per_page`   | `20`      | Results per page     |
| `page`       | `1`       | Page number          |
| `department` | _(all)_   | Filter by department |
| `status`     | `publish` | `publish` or `draft` |

**Response headers:**

```
X-Total: 45
X-Total-Pages: 5
```

**Response 200:** Array of job objects (same shape as POST response).

---

### GET /jobs/{id} — Get a job

```http
GET https://t.arrowhitech.com/wp-json/aht/v1/jobs/42
X-API-Key: your-api-key
```

**Response 200:** Single job object.

---

### PUT /jobs/{id} — Update a job

Send only the fields to change — partial updates are supported.

```http
PUT https://t.arrowhitech.com/wp-json/aht/v1/jobs/42
X-API-Key: your-api-key
Content-Type: application/json

{
  "salary": "Negotiable",
  "status": "draft"
}
```

**Response 200:** Updated job object.

---

### DELETE /jobs/{id} — Delete a job

```http
DELETE https://t.arrowhitech.com/wp-json/aht/v1/jobs/42
X-API-Key: your-api-key
```

**Response 200:**

```json
{
  "deleted": true,
  "id": 42
}
```

---

## Error Codes

| HTTP | Code              | Description                      |
|------|------------------|----------------------------------|
| 401  | `missing_api_key` | `X-API-Key` header missing       |
| 403  | `invalid_api_key` | API key incorrect                |
| 404  | `not_found`       | Job not found                    |
| 500  | `insert_failed`   | Failed to create job             |
| 500  | `update_failed`   | Failed to update job             |
| 500  | `no_api_key`      | API key not configured on server |

**Error response shape:**

```json
{
  "code": "invalid_api_key",
  "message": "API key not valid.",
  "data": { "status": 403 }
}
```

---

## Code Examples

### cURL

```bash
# Create
curl -X POST https://t.arrowhitech.com/wp-json/aht/v1/jobs \
  -H "X-API-Key: your-api-key" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Frontend Developer",
    "department": "IT",
    "salary": "$1500 - $2500",
    "location": "Remote",
    "deadline": "30/08/2026"
  }'

# List
curl -H "X-API-Key: your-api-key" \
  "https://t.arrowhitech.com/wp-json/aht/v1/jobs?department=IT&per_page=10"

# Update
curl -X PUT https://t.arrowhitech.com/wp-json/aht/v1/jobs/42 \
  -H "X-API-Key: your-api-key" \
  -H "Content-Type: application/json" \
  -d '{"status": "draft"}'

# Delete
curl -X DELETE https://t.arrowhitech.com/wp-json/aht/v1/jobs/42 \
  -H "X-API-Key: your-api-key"
```

### Python

```python
import requests

BASE = "https://t.arrowhitech.com/wp-json/aht/v1"
H    = {"X-API-Key": "your-api-key", "Content-Type": "application/json"}

# Create
r = requests.post(f"{BASE}/jobs", headers=H, json={
    "title":      "Frontend Developer",
    "department": "IT",
    "salary":     "$1500 - $2500",
    "location":   "Remote",
    "deadline":   "30/08/2026",
})
job = r.json()
print(job["id"], job["url"])

# List
jobs = requests.get(f"{BASE}/jobs", headers=H, params={"department": "IT"}).json()

# Update
requests.put(f"{BASE}/jobs/{job['id']}", headers=H, json={"status": "draft"})

# Delete
requests.delete(f"{BASE}/jobs/{job['id']}", headers=H)
```

### Node.js

```js
const BASE = "https://t.arrowhitech.com/wp-json/aht/v1";
const H    = { "X-API-Key": "your-api-key", "Content-Type": "application/json" };

// Create
const res = await fetch(`${BASE}/jobs`, {
  method: "POST",
  headers: H,
  body: JSON.stringify({
    title:      "DevOps Engineer",
    department: "IT",
    salary:     "Negotiable",
    deadline:   "30/09/2026",
  }),
});
const job = await res.json();
console.log(job.id, job.url);

// List
const list = await fetch(`${BASE}/jobs?department=IT`, { headers: H })
  .then(r => r.json());

// Update
await fetch(`${BASE}/jobs/${job.id}`, {
  method: "PUT", headers: H,
  body: JSON.stringify({ status: "draft" }),
});

// Delete
await fetch(`${BASE}/jobs/${job.id}`, { method: "DELETE", headers: H });
```
