# Ticket Creation API Documentation

## Overview
This API allows authenticated users to create tickets within projects they have access to. The API includes comprehensive validation, business logic checks, and proper relationship management.

## Base URL
```
http://localhost:8000/api/v1/tickets
```

## Authentication
- **Method**: JWT Token
- **Header**: `Authorization: Bearer {jwt_token}`
- **Required**: User must be authenticated and have access to the project

### Getting JWT Token
First authenticate using the login endpoint to get your JWT token:

```http
POST /api/v1/auth/login
Content-Type: application/json

{
  "email": "user@example.com",
  "password": "your_password"
}
```

Response:
```json
{
  "success": true,
  "data": {
    "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
    "token_type": "bearer",
    "expires_in": 3600,
    "user": {
      "id": 1,
      "name": "John Doe",
      "email": "user@example.com"
    }
  }
}
```

---

## Create Ticket

### Endpoint
```
POST /api/v1/tickets
```

### Request Headers
```http
Content-Type: application/json
Authorization: Bearer {jwt_token}
```

### Request Body

#### Required Fields
| Field | Type | Description | Example |
|-------|------|-------------|---------|
| `title` | string | Ticket title (max 255 chars) | `"Fix login bug"` |
| `type` | string | Ticket type | `"task"`, `"bug"`, `"story"`, `"epic"`, `"subtask"`, `"improvement"` |
| `priority` | string | Priority level | `"lowest"`, `"low"`, `"medium"`, `"high"`, `"highest"` |
| `project_id` | integer | Project ID | `1` |

#### Optional Fields
| Field | Type | Description | Example |
|-------|------|-------------|---------|
| `description` | string | Detailed description (max 10,000 chars) | `"Users cannot login with correct credentials"` |
| `assignee_id` | integer | User ID to assign ticket to | `5` |
| `sprint_id` | integer | Sprint ID (must belong to same project) | `3` |
| `parent_id` | integer | Parent ticket ID (for epics/stories) | `10` |
| `story_points` | decimal | Story points for agile planning | `3.5` |
| `original_estimate_minutes` | integer | Time estimate in minutes | `240` |
| `due_date` | string | Due date (YYYY-MM-DD) | `"2026-03-15"` |
| `start_date` | string | Start date (YYYY-MM-DD) | `"2026-03-01"` |
| `labels` | array | Array of label IDs | `[1, 2, 3]` |
| `watchers` | array | Array of user IDs to watch ticket | `[5, 7]` |

---

## Example Requests

### Basic Ticket Creation
```json
{
  "title": "Fix login authentication bug",
  "description": "Users are unable to login even with correct credentials. The error message is not helpful for debugging.",
  "type": "bug",
  "priority": "high",
  "project_id": 1,
  "assignee_id": 5,
  "due_date": "2026-03-15"
}
```

### Story with Story Points
```json
{
  "title": "Implement user profile page",
  "description": "Create a user profile page where users can view and edit their personal information, avatar, and preferences.",
  "type": "story",
  "priority": "medium",
  "project_id": 1,
  "assignee_id": 7,
  "story_points": 5.0,
  "original_estimate_minutes": 480,
  "labels": [1, 3],
  "watchers": [5, 7, 8]
}
```

### Epic with Subtasks
```json
{
  "title": "Mobile App Development",
  "description": "Complete mobile application development for iOS and Android platforms",
  "type": "epic",
  "priority": "high",
  "project_id": 1,
  "labels": [2, 4],
  "watchers": [5, 6, 7, 8]
}
```

### Subtask
```json
{
  "title": "Design mobile app UI mockups",
  "description": "Create detailed UI mockups for all mobile app screens",
  "type": "subtask",
  "priority": "medium",
  "project_id": 1,
  "parent_id": 15,
  "assignee_id": 9,
  "story_points": 2.0,
  "due_date": "2026-03-10"
}
```

---

## Response Format

### Success Response (201 Created)
```json
{
  "success": true,
  "message": "Ticket created successfully",
  "data": {
    "ticket": {
      "id": 123,
      "uuid": "550e8400-e29b-41d4-a716-446655440000",
      "key": "PROJ-123",
      "key_sequence": 123,
      "title": "Fix login authentication bug",
      "description": "Users are unable to login even with correct credentials...",
      "type": "bug",
      "priority": "high",
      "resolution_status": null,
      "status_id": 1,
      "project_id": 1,
      "reporter_id": 5,
      "assignee_id": 5,
      "sprint_id": null,
      "parent_id": null,
      "story_points": null,
      "original_estimate_minutes": null,
      "remaining_estimate_minutes": null,
      "due_date": "2026-03-15",
      "start_date": null,
      "resolved_at": null,
      "closed_at": null,
      "resolution_note": null,
      "environment": null,
      "position": 124.0,
      "is_archived": false,
      "created_at": "2026-02-22T14:30:00.000000Z",
      "updated_at": "2026-02-22T14:30:00.000000Z",
      "deleted_at": null,
      "project": {
        "id": 1,
        "name": "Project Alpha",
        "key": "PROJ"
      },
      "status": {
        "id": 1,
        "name": "To Do",
        "color": "#6B7280"
      },
      "reporter": {
        "id": 5,
        "name": "John Doe",
        "email": "john@example.com"
      },
      "assignee": {
        "id": 5,
        "name": "John Doe",
        "email": "john@example.com"
      },
      "sprint": null,
      "parent": null,
      "labels": [
        {
          "id": 1,
          "name": "Bug",
          "color": "#EF4444"
        }
      ],
      "watchers": [
        {
          "id": 5,
          "name": "John Doe",
          "email": "john@example.com"
        }
      ]
    }
  }
}
```

### Error Responses

#### Validation Error (422 Unprocessable Entity)
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "title": ["Ticket title is required"],
    "project_id": ["Project is required"],
    "type": ["Invalid ticket type"]
  }
}
```

#### Authorization Error (403 Forbidden)
```json
{
  "success": false,
  "message": "You do not have access to this project"
}
```

#### Not Found Error (404 Not Found)
```json
{
  "success": false,
  "message": "Project not found"
}
```

#### Server Error (500 Internal Server Error)
```json
{
  "success": false,
  "message": "Failed to create ticket",
  "error": "Database connection failed"
}
```

---

## Business Logic & Validations

### Automatic Validations
1. **Project Access**: User must be a member of the project
2. **Sprint Validation**: Sprint must belong to the same project
3. **Parent Validation**: Parent ticket must belong to the same project
4. **Circular Reference**: Prevents circular parent-child relationships
5. **Assignee Access**: Assignee must have access to the project
6. **Watcher Access**: All watchers must have access to the project

### Automatic Processing
1. **Ticket Key Generation**: Automatically generates project key + sequence (e.g., "PROJ-123")
2. **Default Status**: Sets ticket status to "To Do" by default
3. **Position**: Sets ticket position for ordering
4. **Reporter as Watcher**: Automatically adds the reporter as a watcher
5. **History Entry**: Creates audit trail entry for ticket creation

### Date Validations
- `due_date`: Cannot be in the past
- `start_date`: Must be before or equal to due date
- Both dates are optional

### Numeric Validations
- `story_points`: Must be between 0 and 999.9
- `original_estimate_minutes`: Must be non-negative integer

---

## Ideal Flow Example

### Step 1: Get Available Projects
```http
GET /api/v1/projects
Authorization: Bearer {token}
```

### Step 2: Get Project Members (for assignee/watcher selection)
```http
GET /api/v1/projects/{project_id}/members
Authorization: Bearer {token}
```

### Step 3: Get Available Labels
```http
GET /api/v1/labels
Authorization: Bearer {token}
```

### Step 4: Get Available Sprints (optional)
```http
GET /api/v1/projects/{project_id}/sprints
Authorization: Bearer {token}
```

### Step 5: Create Ticket
```http
POST /api/v1/tickets
Authorization: Bearer {token}
Content-Type: application/json

{
  "title": "Implement user authentication",
  "description": "Add JWT-based authentication system",
  "type": "story",
  "priority": "high",
  "project_id": 1,
  "assignee_id": 5,
  "story_points": 8.0,
  "original_estimate_minutes": 480,
  "labels": [1, 2],
  "watchers": [5, 6]
}
```

### Step 6: Verify Creation
```http
GET /api/v1/tickets/{ticket_id}
Authorization: Bearer {token}
```

---

## Testing with cURL

### Create a Test Ticket
```bash
curl -X POST http://localhost:8000/api/v1/tickets \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN_HERE" \
  -d '{
    "title": "Test Ticket Creation",
    "description": "This is a test ticket created via API",
    "type": "task",
    "priority": "medium",
    "project_id": 1,
    "assignee_id": 5
  }'
```

---

## Frontend Integration Example

### React/TypeScript Example
```typescript
interface CreateTicketRequest {
  title: string;
  description?: string;
  type: 'task' | 'bug' | 'story' | 'epic' | 'subtask' | 'improvement';
  priority: 'lowest' | 'low' | 'medium' | 'high' | 'highest';
  project_id: number;
  assignee_id?: number;
  sprint_id?: number;
  parent_id?: number;
  story_points?: number;
  original_estimate_minutes?: number;
  due_date?: string;
  start_date?: string;
  labels?: number[];
  watchers?: number[];
}

const createTicket = async (ticketData: CreateTicketRequest) => {
  try {
    const response = await fetch('/api/v1/tickets', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${localStorage.getItem('jwt_token')}`,
      },
      body: JSON.stringify(ticketData),
    });

    const result = await response.json();

    if (result.success) {
      console.log('Ticket created:', result.data.ticket);
      return result.data.ticket;
    } else {
      console.error('Validation errors:', result.errors);
      throw new Error(result.message);
    }
  } catch (error) {
    console.error('Failed to create ticket:', error);
    throw error;
  }
};
```

---

## Rate Limiting & Security

- **Authentication**: Required for all endpoints
- **Authorization**: Project-level access control
- **Rate Limiting**: Configurable per user/IP
- **Input Sanitization**: All inputs are validated and sanitized
- **SQL Injection Protection**: Uses Laravel's ORM with parameter binding
- **XSS Protection**: Output is properly escaped

---

## Error Handling Best Practices

1. **Client-Side**: Validate inputs before sending to API
2. **Network Errors**: Implement retry logic with exponential backoff
3. **Validation Errors**: Display specific field errors to users
4. **Authorization Errors**: Redirect to login or show access denied message
5. **Server Errors**: Show generic error message and log details for debugging
