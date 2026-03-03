# Ticket Details API Documentation

## Overview
This API provides comprehensive details about a specific ticket, including all related data such as comments, attachments, time logs, history, and linked tickets.

## Base URL
```
http://localhost:8000/api/v1/tickets/{uuid}
```

## Authentication
- **Method**: JWT Token
- **Header**: `Authorization: Bearer {jwt_token}`
- **Required**: User must be authenticated and have access to the ticket's project

---

## Get Ticket Details

### Endpoint
```
GET /api/v1/tickets/{uuid}
```

### Parameters
| Parameter | Type | Description | Example |
|-----------|------|-------------|---------|
| `uuid` | string | The UUID of the ticket | `550e8400-e29b-41d4-a716-446655440000` |

### Request Headers
```http
Content-Type: application/json
Authorization: Bearer {jwt_token}
```

---

## Response Format

### Success Response (200 OK)
```json
{
  "success": true,
  "message": "Ticket retrieved successfully",
  "data": {
    "ticket": {
      "id": 123,
      "uuid": "550e8400-e29b-41d4-a716-446655440000",
      "key": "PROJ-123",
      "key_sequence": 123,
      "title": "Fix login authentication bug",
      "description": "Users cannot login with correct credentials",
      "type": "bug",
      "priority": "high",
      "resolution_status": null,
      "status_id": 1,
      "project_id": 1,
      "reporter_id": 5,
      "assignee_id": 7,
      "sprint_id": 3,
      "parent_id": null,
      "story_points": 3.0,
      "original_estimate_minutes": 240,
      "remaining_estimate_minutes": 120,
      "due_date": "2026-03-15",
      "start_date": "2026-03-01",
      "resolved_at": null,
      "closed_at": null,
      "resolution_note": null,
      "environment": "production",
      "position": 124.0,
      "is_archived": false,
      "created_at": "2026-02-22T14:30:00.000000Z",
      "updated_at": "2026-02-22T14:30:00.000000Z",
      "deleted_at": null,
      
      // Relationships
      "project": {
        "id": 1,
        "name": "Project Alpha",
        "key": "PROJ"
      },
      "status": {
        "id": 1,
        "name": "To Do",
        "slug": "todo",
        "color": "#6B7280",
        "category": "todo"
      },
      "reporter": {
        "id": 5,
        "name": "John Doe",
        "email": "john@example.com"
      },
      "assignee": {
        "id": 7,
        "name": "Jane Smith",
        "email": "jane@example.com"
      },
      "sprint": {
        "id": 3,
        "name": "Sprint 2"
      },
      "parent": null,
      "children": [
        {
          "id": 124,
          "title": "Investigate login issue",
          "key": "PROJ-124",
          "status": {
            "name": "In Progress",
            "color": "#3B82F6"
          }
        }
      ],
      "labels": [
        {
          "id": 1,
          "name": "Bug",
          "color": "#EF4444"
        },
        {
          "id": 2,
          "name": "Urgent",
          "color": "#DC2626"
        }
      ],
      "watchers": [
        {
          "id": 5,
          "name": "John Doe",
          "email": "john@example.com"
        },
        {
          "id": 7,
          "name": "Jane Smith",
          "email": "jane@example.com"
        }
      ],
      
      // Time Logs
      "timeLogs": [
        {
          "id": 1,
          "minutes": 120,
          "description": "Initial investigation",
          "date": "2026-03-01",
          "user": {
            "id": 7,
            "name": "Jane Smith",
            "email": "jane@example.com"
          },
          "created_at": "2026-03-01T10:00:00.000000Z"
        },
        {
          "id": 2,
          "minutes": 60,
          "description": "Fixed authentication logic",
          "date": "2026-03-02",
          "user": {
            "id": 7,
            "name": "Jane Smith",
            "email": "jane@example.com"
          },
          "created_at": "2026-03-02T15:30:00.000000Z"
        }
      ],
      
      // Comments
      "comments": [
        {
          "id": 1,
          "content": "I've reproduced the issue on my machine. The error occurs when users have special characters in their password.",
          "user": {
            "id": 7,
            "name": "Jane Smith",
            "email": "jane@example.com"
          },
          "created_at": "2026-03-01T11:00:00.000000Z"
        },
        {
          "id": 2,
          "content": "Thanks for the update. I've tested with the latest build and it's working now.",
          "user": {
            "id": 5,
            "name": "John Doe",
            "email": "john@example.com"
          },
          "created_at": "2026-03-02T16:00:00.000000Z"
        }
      ],
      
      // Attachments
      "attachments": [
        {
          "id": 1,
          "filename": "screenshot_20260301.png",
          "original_filename": "login_error.png",
          "mime_type": "image/png",
          "size": 245760,
          "path": "/uploads/tickets/1/screenshot_20260301.png",
          "uploader": {
            "id": 7,
            "name": "Jane Smith",
            "email": "jane@example.com"
          },
          "created_at": "2026-03-01T10:15:00.000000Z"
        }
      ],
      
      // History
      "history": [
        {
          "id": 1,
          "field_name": "created",
          "old_value": null,
          "new_value": "Fix login authentication bug",
          "change_type": "created",
          "changed_at": "2026-02-22T14:30:00.000000Z",
          "user": {
            "id": 5,
            "name": "John Doe",
            "email": "john@example.com"
          }
        },
        {
          "id": 2,
          "field_name": "status",
          "old_value": "To Do",
          "new_value": "In Progress",
          "change_type": "updated",
          "changed_at": "2026-03-01T10:00:00.000000Z",
          "user": {
            "id": 7,
            "name": "Jane Smith",
            "email": "jane@example.com"
          }
        },
        {
          "id": 3,
          "field_name": "assignee_id",
          "old_value": "5",
          "new_value": "7",
          "change_type": "updated",
          "changed_at": "2026-03-01T10:05:00.000000Z",
          "user": {
            "id": 5,
            "name": "John Doe",
            "email": "john@example.com"
          }
        }
      ],
      
      // Linked Tickets
      "linkedTickets": [
        {
          "id": 125,
          "title": "Update password validation",
          "key": "PROJ-125",
          "pivot": {
            "type": "relates_to",
            "created_by": 7
          }
        }
      ],
      
      // Calculated Metrics
      "time_spent_minutes": 180,
      "time_spent_hours": 3.0,
      "comments_count": 2,
      "attachments_count": 1,
      "watchers_count": 2,
      "subtasks_count": 1
    },
    
    // Related Tickets
    "related_tickets": {
      "parent": null,
      "siblings": [],
      "subtasks": [
        {
          "id": 124,
          "title": "Investigate login issue",
          "key": "PROJ-124",
          "status": {
            "name": "In Progress",
            "color": "#3B82F6"
          },
          "assignee": {
            "id": 8,
            "name": "Bob Wilson",
            "email": "bob@example.com"
          }
        }
      ]
    },
    
    // Metrics Summary
    "metrics": {
      "time_spent_minutes": 180,
      "time_spent_hours": 3.0,
      "comments_count": 2,
      "attachments_count": 1,
      "watchers_count": 2,
      "subtasks_count": 1
    }
  }
}
```

### Error Responses

#### Not Found (404)
```json
{
  "success": false,
  "message": "Ticket not found"
}
```

#### Access Denied (403)
```json
{
  "success": false,
  "message": "You do not have access to this ticket"
}
```

#### Server Error (500)
```json
{
  "success": false,
  "message": "Failed to retrieve ticket",
  "error": "Internal server error"
}
```

---

## Data Relationships Explained

### Core Ticket Information
- **Basic Fields**: title, description, type, priority, status
- **Project**: Project details (name, key)
- **Users**: Reporter, assignee, watchers
- **Sprint**: Sprint information if assigned
- **Hierarchy**: Parent/child relationships for epics and subtasks

### Activity Data
- **Time Logs**: All time entries with user and description
- **Comments**: Discussion thread with user information
- **Attachments**: File uploads with metadata
- **History**: Complete audit trail of all changes

### Relationships
- **Labels**: Categorization tags
- **Linked Tickets**: Related tickets with link types
- **Related Tickets**: Parent, siblings, and subtasks

### Metrics
- **Time Tracking**: Total time spent in minutes and hours
- **Activity Counts**: Comments, attachments, watchers, subtasks

---

## Usage Examples

### Frontend Integration
```typescript
import { ticketService } from '@/services/ticketService';

// Get ticket details
const getTicketDetails = async (ticketUuid: string) => {
  try {
    const response = await ticketService.getTicket(ticketUuid);
    
    console.log('Ticket:', response.data.ticket);
    console.log('Related tickets:', response.data.related_tickets);
    console.log('Metrics:', response.data.metrics);
    
    return response.data;
  } catch (error) {
    console.error('Failed to fetch ticket:', error);
  }
};
```

### React Component Example
```typescript
import { useState, useEffect } from 'react';
import { ticketService, TicketDetailResponse } from '@/services/ticketService';

function TicketDetail({ ticketUuid }: { ticketUuid: string }) {
  const [ticket, setTicket] = useState<TicketDetailResponse | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    const fetchTicket = async () => {
      try {
        const data = await ticketService.getTicket(ticketUuid);
        setTicket(data);
      } catch (err: any) {
        setError(err.message);
      } finally {
        setLoading(false);
      }
    };

    fetchTicket();
  }, [ticketUuid]);

  if (loading) return <div>Loading...</div>;
  if (error) return <div>Error: {error}</div>;
  if (!ticket) return <div>Ticket not found</div>;

  return (
    <div>
      <h1>{ticket.data.ticket.title}</h1>
      <p>Status: {ticket.data.ticket.status.name}</p>
      <p>Assignee: {ticket.data.ticket.assignee?.name}</p>
      
      {/* Comments */}
      <div>
        <h3>Comments ({ticket.data.metrics.comments_count})</h3>
        {ticket.data.ticket.comments.map(comment => (
          <div key={comment.id}>
            <strong>{comment.user.name}:</strong> {comment.content}
          </div>
        ))}
      </div>
      
      {/* Time Logs */}
      <div>
        <h3>Time Logged: {ticket.data.metrics.time_spent_hours}h</h3>
        {ticket.data.ticket.timeLogs.map(log => (
          <div key={log.id}>
            {log.user.name}: {log.minutes}min - {log.description}
          </div>
        ))}
      </div>
    </div>
  );
}
```

---

## Performance Considerations

### Efficient Loading
- All related data is loaded in a single query using eager loading
- Time logs, comments, and attachments are ordered by creation date
- Only users with project access can view ticket details

### Caching Strategy
- Consider caching ticket details for frequently accessed tickets
- Implement cache invalidation when ticket is updated
- Use ETags for conditional requests

### Pagination
- For tickets with many comments or time logs, consider implementing pagination
- Add `page` and `limit` parameters for large datasets

---

## Security

### Access Control
- Users can only view tickets from projects they have access to
- All relationships are validated to prevent unauthorized data access
- UUIDs are used to prevent sequential ID enumeration

### Data Sanitization
- All user-generated content is properly escaped
- File attachments are validated for type and size
- HTML content in comments is sanitized

---

## Rate Limiting

- **Recommended**: 100 requests per minute per user
- **Burst**: 200 requests per minute for short periods
- **Window**: 1 minute sliding window

---

## Testing with cURL

### Get Ticket Details
```bash
curl -X GET http://localhost:8000/api/v1/tickets/550e8400-e29b-41d4-a716-446655440000 \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN_HERE"
```

### Response
```json
{
  "success": true,
  "message": "Ticket retrieved successfully",
  "data": {
    "ticket": { ... },
    "related_tickets": { ... },
    "metrics": { ... }
  }
}
```

---

## Frontend Integration Complete

The ticket details API provides comprehensive information for building rich ticket detail views, including:

✅ **Complete Ticket Information**: All fields and relationships
✅ **Activity History**: Comments, time logs, attachments
✅ **Related Data**: Parent/child tickets, linked tickets
✅ **Metrics**: Time tracking, activity counts
✅ **Security**: Project-based access control
✅ **Performance**: Optimized queries with eager loading
