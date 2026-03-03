# Project Tickets API Documentation

## Overview
This API provides a comprehensive list of all tickets within a specific project, with advanced filtering, sorting, and pagination capabilities.

## Base URL
```
http://localhost:8000/api/v1/projects/{projectId}/tickets
```

## Authentication
- **Method**: JWT Token
- **Header**: `Authorization: Bearer {jwt_token}`
- **Required**: User must be authenticated and have access to the project

---

## Get Project Tickets

### Endpoint
```
GET /api/v1/projects/{projectId}/tickets
```

### Parameters
| Parameter | Type | Description | Example |
|-----------|------|-------------|---------|
| `projectId` | integer | The ID of the project | `1` |

### Query Parameters (Optional)
| Parameter | Type | Description | Default | Example |
|-----------|------|-------------|---------|---------|
| `status` | string | Filter by ticket status (slug or ID) | - | `todo`, `in_progress` |
| `type` | string | Filter by ticket type | - | `bug`, `task`, `story` |
| `priority` | string | Filter by priority level | - | `high`, `medium`, `low` |
| `assignee` | integer | Filter by assignee user ID | - | `5` |
| `sprint` | integer/string | Filter by sprint ID or "backlog" | - | `3`, `backlog` |
| `search` | string | Search in title, description, or key | - | `login` |
| `per_page` | integer | Number of items per page | `20` | `50` |
| `page` | integer | Page number | `1` | `2` |
| `sort_by` | string | Sort field | `created_at` | `priority`, `title` |
| `sort_order` | string | Sort direction | `desc` | `asc`, `desc` |
| `include_archived` | boolean | Include archived tickets | `false` | `true` |

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
  "message": "Tickets retrieved successfully",
  "data": {
    "tickets": [
      {
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
        "status": {
          "id": 1,
          "name": "To Do",
          "slug": "todo",
          "color": "#6B7280",
          "category": "todo"
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
    ],
    
    "pagination": {
      "current_page": 1,
      "per_page": 20,
      "total": 45,
      "last_page": 3,
      "from": 1,
      "to": 20,
      "has_more_pages": true
    },
    
    "filters": {
      "status": "todo",
      "type": null,
      "priority": null,
      "assignee": null,
      "sprint": null,
      "search": null,
      "include_archived": false,
      "sort_by": "created_at",
      "sort_order": "desc"
    },
    
    "metrics": {
      "total_count": 45,
      "active_count": 42,
      "archived_count": 3,
      "by_status": {
        "To Do": 15,
        "In Progress": 12,
        "Done": 10,
        "Blocked": 5
      },
      "by_type": {
        "task": 20,
        "bug": 15,
        "story": 8,
        "epic": 2
      },
      "by_priority": {
        "high": 8,
        "medium": 25,
        "low": 9,
        "highest": 3
      }
    }
  }
}
```

### Error Responses

#### Not Found (404)
```json
{
  "success": false,
  "message": "Project not found"
}
```

#### Access Denied (403)
```json
{
  "success": false,
  "message": "You do not have access to this project"
}
```

#### Validation Error (422)
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "per_page": ["The per page must be between 1 and 100."],
    "sort_by": ["The selected sort by is invalid."]
  }
}
```

#### Server Error (500)
```json
{
  "success": false,
  "message": "Failed to retrieve tickets",
  "error": "Internal server error"
}
```

---

## Usage Examples

### Basic Usage
```bash
# Get all tickets for project 1
curl -X GET "http://localhost:8000/api/v1/projects/1/tickets" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

### Filtering Examples
```bash
# Filter by status
curl -X GET "http://localhost:8000/api/v1/projects/1/tickets?status=todo" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"

# Filter by type and priority
curl -X GET "http://localhost:8000/api/v1/projects/1/tickets?type=bug&priority=high" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"

# Search tickets
curl -X GET "http://localhost:8000/api/v1/projects/1/tickets?search=login" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"

# Get backlog tickets
curl -X GET "http://localhost:8000/api/v1/projects/1/tickets?sprint=backlog" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

### Pagination Examples
```bash
# Get page 2 with 10 items per page
curl -X GET "http://localhost:8000/api/v1/projects/1/tickets?page=2&per_page=10" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"

# Sort by priority ascending
curl -X GET "http://localhost:8000/api/v1/projects/1/tickets?sort_by=priority&sort_order=asc" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

### Complex Filtering
```bash
# Get high priority bugs assigned to user 5, sorted by creation date
curl -X GET "http://localhost:8000/api/v1/projects/1/tickets?type=bug&priority=high&assignee=5&sort_by=created_at&sort_order=desc" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

---

## Frontend Integration

### TypeScript Example
```typescript
import { ticketService, ProjectTicketsFilters } from '@/services/ticketService';

// Get all tickets for project
const getTickets = async (projectId: number) => {
  try {
    const response = await ticketService.getProjectTickets(projectId);
    console.log('Tickets:', response.data.tickets);
    console.log('Pagination:', response.data.pagination);
    console.log('Metrics:', response.data.metrics);
    return response.data;
  } catch (error) {
    console.error('Failed to fetch tickets:', error);
  }
};

// Get filtered tickets
const getFilteredTickets = async (projectId: number) => {
  const filters: ProjectTicketsFilters = {
    status: 'todo',
    type: 'bug',
    priority: 'high',
    per_page: 50,
    sort_by: 'priority',
    sort_order: 'desc'
  };

  try {
    const response = await ticketService.getProjectTickets(projectId, filters);
    return response.data;
  } catch (error) {
    console.error('Failed to fetch filtered tickets:', error);
  }
};
```

### React Component Example
```typescript
import { useState, useEffect } from 'react';
import { ticketService, ProjectTicketsFilters, ProjectTicketsResponse } from '@/services/ticketService';

function ProjectTickets({ projectId }: { projectId: number }) {
  const [tickets, setTickets] = useState<ProjectTicketsResponse | null>(null);
  const [loading, setLoading] = useState(true);
  const [filters, setFilters] = useState<ProjectTicketsFilters>({
    per_page: 20,
    sort_by: 'created_at',
    sort_order: 'desc'
  });

  useEffect(() => {
    const fetchTickets = async () => {
      try {
        setLoading(true);
        const data = await ticketService.getProjectTickets(projectId, filters);
        setTickets(data);
      } catch (error) {
        console.error('Failed to fetch tickets:', error);
      } finally {
        setLoading(false);
      }
    };

    fetchTickets();
  }, [projectId, filters]);

  const handleFilterChange = (newFilters: Partial<ProjectTicketsFilters>) => {
    setFilters(prev => ({ ...prev, ...newFilters, page: 1 }));
  };

  const handlePageChange = (page: number) => {
    setFilters(prev => ({ ...prev, page }));
  };

  if (loading) return <div>Loading...</div>;
  if (!tickets) return <div>Failed to load tickets</div>;

  return (
    <div>
      <h2>Project Tickets ({tickets.data.metrics.total_count})</h2>
      
      {/* Filters */}
      <div className="filters">
        <select onChange={(e) => handleFilterChange({ status: e.target.value })}>
          <option value="">All Status</option>
          <option value="todo">To Do</option>
          <option value="in_progress">In Progress</option>
          <option value="done">Done</option>
        </select>
        
        <select onChange={(e) => handleFilterChange({ type: e.target.value })}>
          <option value="">All Types</option>
          <option value="task">Task</option>
          <option value="bug">Bug</option>
          <option value="story">Story</option>
        </select>
        
        <input
          type="text"
          placeholder="Search..."
          onChange={(e) => handleFilterChange({ search: e.target.value })}
        />
      </div>
      
      {/* Metrics */}
      <div className="metrics">
        <div>Active: {tickets.data.metrics.active_count}</div>
        <div>Archived: {tickets.data.metrics.archived_count}</div>
        <div>By Status: {JSON.stringify(tickets.data.metrics.by_status)}</div>
      </div>
      
      {/* Tickets List */}
      <div className="tickets-list">
        {tickets.data.tickets.map(ticket => (
          <div key={ticket.id} className="ticket-item">
            <h3>{ticket.key} - {ticket.title}</h3>
            <p>Status: {ticket.status.name}</p>
            <p>Assignee: {ticket.assignee?.name || 'Unassigned'}</p>
            <p>Priority: {ticket.priority}</p>
          </div>
        ))}
      </div>
      
      {/* Pagination */}
      <div className="pagination">
        <button
          disabled={tickets.data.pagination.current_page === 1}
          onClick={() => handlePageChange(tickets.data.pagination.current_page - 1)}
        >
          Previous
        </button>
        
        <span>
          Page {tickets.data.pagination.current_page} of {tickets.data.pagination.last_page}
        </span>
        
        <button
          disabled={!tickets.data.pagination.has_more_pages}
          onClick={() => handlePageChange(tickets.data.pagination.current_page + 1)}
        >
          Next
        </button>
      </div>
    </div>
  );
}
```

---

## Available Sort Fields

| Field | Description | Example |
|-------|-------------|---------|
| `created_at` | Sort by creation date | `sort_by=created_at` |
| `updated_at` | Sort by last update | `sort_by=updated_at` |
| `title` | Sort by ticket title | `sort_by=title` |
| `priority` | Sort by priority | `sort_by=priority` |
| `type` | Sort by ticket type | `sort_by=type` |
| `position` | Sort by board position | `sort_by=position` |
| `key` | Sort by ticket key | `sort_by=key` |

---

## Available Status Slugs

| Slug | Display Name |
|------|--------------|
| `todo` | To Do |
| `in_progress` | In Progress |
| `done` | Done |
| `blocked` | Blocked |

---

## Available Ticket Types

| Type | Description |
|------|-------------|
| `task` | Regular task |
| `bug` | Bug report |
| `story` | User story |
| `epic` | Epic |
| `subtask` | Subtask |
| `improvement` | Improvement |

---

## Available Priority Levels

| Priority | Level |
|----------|-------|
| `lowest` | Lowest |
| `low` | Low |
| `medium` | Medium |
| `high` | High |
| `highest` | Highest |

---

## Performance Considerations

### Pagination
- Use pagination for large projects (default: 20 items per page)
- Maximum per_page: 100 items
- Consider using filters to reduce result size

### Indexing
- The database is indexed on commonly queried fields:
  - `project_id`, `status_id` for status filtering
  - `project_id`, `sprint_id`, `position` for board queries
  - `assignee_id`, `status_id` for assignment queries
  - `project_id`, `is_archived` for archive filtering

### Search Performance
- Search uses `LIKE` queries on title, description, and key
- Consider adding full-text search for large datasets
- Search is case-insensitive

---

## Rate Limiting

- **Recommended**: 200 requests per minute per user
- **Burst**: 400 requests per minute for short periods
- **Window**: 1 minute sliding window

---

## Security

### Access Control
- Users can only view tickets from projects they have access to
- All filters are validated to prevent SQL injection
- Search queries are properly escaped

### Data Exposure
- Sensitive information is filtered based on user permissions
- Archived tickets are excluded by default
- User information is limited to name and email

---

## Complete Integration

The project tickets API provides a comprehensive solution for listing and filtering tickets with:

✅ **Advanced Filtering**: Status, type, priority, assignee, sprint, search
✅ **Pagination**: Efficient handling of large datasets
✅ **Sorting**: Multiple sort options with direction control
✅ **Metrics**: Real-time statistics and counts
✅ **Search**: Full-text search across ticket fields
✅ **Security**: Project-based access control
✅ **Performance**: Optimized queries and indexing
