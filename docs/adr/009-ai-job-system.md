# ADR 009: AI Background Job System

## Status

Accepted

## Date

2026-01-04

## Context

AI operations in DanaVision (product identification, price search, smart fill) can take 5-30+ seconds to complete. Running these synchronously blocks the user interface and creates a poor user experience.

We needed a solution that:
1. Runs AI operations asynchronously
2. Provides real-time status updates to users
3. Allows users to cancel long-running jobs
4. Tracks all AI operations for debugging and auditing
5. Integrates with the existing Laravel/Inertia stack

## Decision

We implemented a comprehensive AI background job system with the following components:

### Database Schema

1. **`ai_jobs` table**: Tracks all AI background jobs with status, progress, input/output data, and relationships to items/lists.

2. **`ai_request_logs` table**: Logs all AI API requests and responses for transparency and debugging.

### Backend Architecture

1. **`BaseAIJob`**: Abstract base class for all AI jobs that handles:
   - Status updates (pending → processing → completed/failed/cancelled)
   - Progress tracking
   - Cancellation checks
   - Error handling
   - Integration with `AILoggingService`

2. **Job Classes**:
   - `ProductIdentificationJob`: Image/text product identification
   - `PriceSearchJob`: SERP API + AI price search
   - `SmartFillJob`: AI-enhanced item details
   - `PriceRefreshJob`: Single item price refresh

3. **`AILoggingService`**: Wraps all AI calls to automatically log:
   - Request data (prompts, options)
   - Response data
   - Token usage
   - Duration
   - Errors
   - SERP API data (for price aggregation)

4. **Controllers**:
   - `AIJobController`: Job management endpoints
   - `AIRequestLogController`: Log viewing endpoints

### Frontend Architecture

1. **`useJobPolling` hook**: Polls for job status with configurable intervals, triggers callbacks on status changes.

2. **`useJobNotifications` hook**: Manages toast notification state.

3. **`JobNotifications` component**: Global toast notifications for job status (started, completed, failed).

4. **Settings Tabs**: Jobs and AI Logs tabs for viewing job history and API logs.

### API Endpoints

Jobs:
- `GET /api/ai-jobs` - List jobs
- `GET /api/ai-jobs/active` - Active jobs only
- `GET /api/ai-jobs/{id}` - Single job details
- `POST /api/ai-jobs` - Create new job
- `POST /api/ai-jobs/{id}/cancel` - Cancel job
- `DELETE /api/ai-jobs/{id}` - Delete job

Logs:
- `GET /api/ai-logs` - List logs (paginated)
- `GET /api/ai-logs/{id}` - Log details
- `GET /api/ai-logs/stats` - Usage statistics
- `DELETE /api/ai-logs/{id}` - Delete log
- `DELETE /api/ai-logs/all` - Clear all logs

### Real-time Updates

We chose **polling** over WebSockets/SSE for simplicity:
- Frontend polls `/api/ai-jobs/active` every 3 seconds
- Automatically stops when all jobs complete
- Triggers toast notifications on status changes

## Alternatives Considered

### WebSockets/Laravel Broadcasting
- **Pros**: True real-time updates
- **Cons**: Requires additional infrastructure (Redis, Pusher, or Soketi), more complex setup
- **Why rejected**: Polling is simpler and sufficient for our use case where updates happen every few seconds

### Server-Sent Events (SSE)
- **Pros**: Simpler than WebSockets, no bidirectional communication needed
- **Cons**: Requires keeping connections open, potential scaling issues
- **Why rejected**: Polling is more reliable and easier to implement with Inertia

### Synchronous with Progress
- **Pros**: Simpler implementation
- **Cons**: Blocks the UI, users can't navigate away, poor UX
- **Why rejected**: Does not meet user experience requirements

## Consequences

### Positive

1. **Improved UX**: Users can navigate freely while jobs run
2. **Transparency**: All AI operations are logged and visible
3. **Debugging**: Full request/response logs help troubleshoot issues
4. **Control**: Users can cancel long-running jobs
5. **Scalability**: Queue workers can scale independently

### Negative

1. **Complexity**: More code to maintain
2. **Polling overhead**: Additional HTTP requests for status checks
3. **Storage**: Logs can grow large over time (mitigated by clear function)

## Implementation Notes

1. Queue worker is configured in `docker/supervisord.conf` with 2 processes
2. Jobs timeout after 5 minutes (`$timeout = 300`)
3. Jobs retry up to 2 times with 30-second backoff
4. Large prompts/responses are truncated for storage (50KB limit)
5. SERP API data is stored for price aggregation logs

## Related ADRs

- ADR 002: AI Provider Abstraction
- ADR 010: SERP API + AI Aggregation Architecture
