Laravel Queue Management System — Final Implementation Instructions
1. Project Goal

Build a complete, production-oriented Queue Management System in my existing blank Laravel project.

The application must support:

A public homepage where customers click to collect and print a queue ticket.
A dedicated public waiting-room display with large ticket numbers and audible announcements.
Multiple service desks, each assigned to a staff member.
Staff requesting the next customer when idle or finished with their current customer.
Atomic ticket assignment so two staff members can never receive the same active ticket.
Admin-controlled queue sessions, including a single End Session action.
Staff profiles, password changes, and SMS OTP password recovery.
Configurable SMS integration with either Hubtel or Nalo.

Inspect the existing Laravel version and installed packages before implementing anything. Preserve the existing project structure and use the project's current frontend stack when practical.

Build functional database-backed features, not static mockups.

2. User Roles

Implement three interfaces.

Public ticket collection homepage

Route: /

Accessible without authentication.

Display:

Organization name and logo.
Large "CLICK TO COLLECT NUMBER" button.
Current queue session status.
Latest called ticket and service desk, if appropriate.
Clear messaging when ticket collection is unavailable.

When a customer clicks the button:

Verify that the queue session is open.
Atomically generate the next ticket number.
Store the ticket in the database with waiting status.
Display a print-friendly ticket.
Trigger the browser print dialog.
Prevent accidental duplicate submissions.
Show a friendly error if the session closes during the request.

Use a configurable prefix and sequence, such as A001, A002, A003. Ticket numbering must be generated using a database-backed sequence scoped to the active queue session, not by counting existing tickets.

The printed ticket should contain:

Organization name.
Ticket number in large text.
Date and time issued.
A short instruction telling the customer to wait for their number to appear on the waiting-room display.

Do not assign a service desk when a ticket is collected. A desk is assigned when a staff member claims the ticket.

Staff dashboard

Route: /staff

Requires authentication and the staff role.

Display:

Staff member's name.
Assigned service desk.
Current session status.
Current ticket and ticket status.
Waiting customer count.
Customers served during the current session.
Clear staff availability status.

Provide these actions:

Request Next

Only allow the action when the staff member has no active ticket and the queue session is open.

Atomically claim the oldest eligible waiting ticket, assign it to the staff member and their service desk, and change its status to called.

If no ticket is available, show a helpful message without creating an assignment.

Accept / Start Serving

When the staff member accepts the called ticket:

Change its status to serving.
Record the acceptance timestamp.
Keep the ticket assigned to that staff member and desk.
Update the public display to show that the customer is being served, if the display includes this status.

Skip

When a staff member skips a ticket:

Record the skip event and reason where applicable.
Preserve the ticket and its history.
Release the assignment only after validating the current ticket state.
Apply the configured requeue policy.
Allow the staff member to claim the next available ticket if the session remains open.

A skipped ticket must not be immediately claimed by another staff member while its original assignment remains active.

Complete Customer

When the customer interaction finishes:

Mark the ticket completed.
Record the completion timestamp.
Clear the staff member's active assignment.
Update statistics.
Enable the Request Next action.

Staff must not be assigned a second active ticket while already handling one.

Admin dashboard

Route: /admin

Requires authentication and the admin role.

Include:

Queue session management.
Service desk management.
Staff account management.
Live queue monitoring.
Ticket history and reports.
Organization and ticket configuration.
SMS provider settings.
Audit logs.

Admins can create, edit, activate, deactivate, and assign staff to service desks.

Allow the admin to configure the number and names of desks, for example:

Desk 1
Desk 2
Desk 3
Desk 4

A staff member should have one active desk assignment at a time unless the application is explicitly configured to support another arrangement.

Prevent two active staff members from occupying the same exclusive desk assignment unless the admin deliberately enables shared desks.

3. Service Desk Management

Create a service_desks table.

Suggested fields:

id
name
code, nullable
is_active
created_at
updated_at

Associate staff with service desks using a service_desk_id on the user record or an appropriate assignment table.

Implement these rules:

Only active service desks can receive new tickets.
Staff must have an active desk assignment to request tickets.
Every called ticket must have a service desk and assigned staff member.
Desk reassignment must not silently change an existing active ticket's assignment.
When a staff member becomes inactive or loses their desk assignment, handle any active ticket using an explicit recovery process.
The admin can see which desks are idle, calling, or serving customers.

Display the desk prominently in the staff dashboard and on the public waiting-room screen.

4. Dedicated Public Waiting-Room Display

Create a dedicated display route, such as /display, intended for a television, monitor, or computer connected to speakers in the waiting area.

This page must be separate from the ticket collection homepage.

It must not require staff authentication. Use an appropriate read-only display access policy so the public can see queue information without gaining access to administrative functions.

Design the display for full-screen use on a television, with:

Organization logo and name.
Large current ticket number.
Large service desk number or name.
A prominent "NOW SERVING" heading.
Recent called tickets and their desks.
Current waiting queue count.
A clear message when no ticket is being called.
A queue-closed screen when the session has ended.

Example layout:

NOW SERVING

A012

PLEASE PROCEED TO

DESK 2

Recent calls:

A011 — Desk 1
A010 — Desk 3
A009 — Desk 2

Use a clean, high-contrast layout readable from across a waiting room. The display should scale to a large television without requiring scrolling.

Public announcement audio

The public display is the authoritative announcement screen.

When a ticket enters the called state, announce it through the display's speakers using the browser's Web Speech API or another suitable text-to-speech solution.

Example announcement:

"Ticket A zero one two, please proceed to Desk Two."

Requirements:

Announce the ticket number and its assigned desk.
Use a configurable language and voice where supported.
Provide a visible mute/unmute control.
Provide a way to test the speakers and voice.
Display the ticket visually even when audio is unavailable.
Do not play the same announcement repeatedly because of polling or reconnections.
Do not announce a ticket until its assignment has been successfully committed to the database.
Do not announce a ticket twice because multiple staff browsers received the same event.
Include a configurable announcement volume where the browser or operating system supports it.

Browser audio often requires an initial user interaction. Provide a clear "Enable Sound" button when the display first loads. Once clicked, initialize the speech system and retain the enabled state for the current display session where practical.

If sound is blocked or unavailable, continue updating the visual display.

Announcement delivery architecture

Implement a centralized announcement event.

When a staff member claims a ticket:

Complete the database transaction.
Confirm the ticket is assigned to the staff member and desk.
Publish a TicketCalled event.
Deliver the event to the public display.
Record a unique event identifier so the display can avoid repeating announcements.
Update the displayed current and recent calls.

Use Laravel broadcasting and a compatible WebSocket service if practical. If the project does not have a working broadcasting setup, use authenticated or read-only polling endpoints with an event cursor or unique announcement IDs.

The backend must be the source of truth.

Do not let each staff browser independently announce the call through its own speakers. Only the dedicated public display should produce the authoritative customer-facing announcement.

If the display disconnects, it must be able to reconnect and recover the current queue state. Reconnecting must not cause the entire call history to be announced again.

Treat a new call event differently from an ordinary status update. A ticket changing from called to serving must not trigger another "please proceed" announcement.

5. Critical Concurrent Assignment Rules

Multiple staff members may click Request Next at the same time. The system must guarantee that the same active ticket cannot be assigned to two staff members.

Implement ticket claiming in a dedicated backend service using:

Database transactions.
Row-level locking, such as lockForUpdate(), on the production database.
A stable ordering by sequence number and creation time.
A conditional status transition from waiting to called.
Validation that the session is open.
Validation that the staff member has an active service desk.
Validation that the staff member has no active ticket.
Validation that the service desk is eligible to receive work.

Perform ticket selection, assignment, status update, and desk association within the same transaction.

Publish events only after the transaction commits.

If two staff members request tickets simultaneously, each must receive a different ticket. If there is only one waiting ticket, only one staff member may claim it.

Use MySQL/MariaDB with InnoDB or another production database with suitable row-locking support. Do not rely on SQLite for production concurrency guarantees.

Add appropriate indexes and unique constraints. Handle database deadlocks and transient errors safely.

A staff member who submits repeated requests must not receive multiple active tickets.

6. Ticket Lifecycle

Implement explicit ticket states:

waiting
called
serving
completed
skipped

Enforce valid transitions on the server.

Suggested lifecycle:

waiting → called → serving → completed

A skipped ticket follows an explicitly configured policy:

Record the skip event.
Release the previous assignment.
Requeue the ticket or retain it for manual recall, depending on the selected policy.

Maintain a ticket activity history containing the staff member, desk, event type, timestamp, and relevant metadata.

Do not delete historical tickets.

Do not silently requeue an actively served customer when a staff member disconnects. Provide an explicit admin recovery workflow for abandoned calls or interrupted sessions.

7. Queue Session Management

Implement one active queue session at a time.

Use clear states such as open and ended, with a closed state before a session begins.

When an admin starts a session:

Create the session record.
Initialize the ticket sequence.
Enable public ticket collection.
Allow eligible staff to request tickets.
Start publishing queue updates.

When an admin clicks End Session:

Ask for confirmation.
Prevent new ticket collection.
Prevent new ticket assignments.
Notify the staff dashboard and public display.
Preserve all historical tickets.
Stop new ticket announcements.
Show a session-ended message on both public pages.
Handle tickets that are still waiting, called, or serving using a documented finalization policy.

Ending a session must not silently erase active assignments or report data.

Make the session state check part of every ticket issuance and assignment transaction to avoid race conditions when the admin ends a session while staff are requesting customers.

8. Staff Profiles and SMS OTP Password Recovery

Create staff profile settings for:

Viewing account details.
Updating permitted profile fields.
Changing a password with current-password verification.
Logging out securely.

Implement a Forgot Password flow using SMS OTP.

The admin must be able to select Hubtel or Nalo in the SMS configuration screen.

Requirements:

Secure OTP generation and hashed storage.
Five-minute default expiry.
Attempt limits and resend throttling.
One-time OTP use.
Rate limiting.
Generic responses that do not disclose account existence.
Safe error handling for provider failures.
Invalidation of the OTP after successful use.

Implement a common SmsProviderInterface with separate Hubtel and Nalo adapters.

Use official provider APIs and current documentation. Store secrets encrypted at rest or in environment configuration. Never expose provider credentials or OTPs in frontend responses or logs.

Provide an admin-only test SMS function.

9. Suggested Database Tables

Create appropriate migrations, relationships, indexes, and foreign keys for:

users

Account details, hashed password, role, active status, phone number, service desk assignment.

queue_sessions

Session label, status, start/end timestamps, and responsible administrators.

service_desks

Desk name, code, active status, timestamps.

tickets

Session ID, ticket number, sequence number, status, assigned staff, service desk, issue/call/accept/completion timestamps.

ticket_events

Ticket ID, staff ID, service desk ID, event type, event timestamp, metadata.

announcements

Unique event identifier, ticket ID, desk ID, event type, creation timestamp, and delivery metadata if needed.

sms_settings

Selected provider, encrypted credentials, sender ID, provider configuration, timestamps.

password_reset_otps

User reference, OTP hash, expiration, attempts, consumed timestamp.

audit_logs

Administrative changes, session actions, desk assignments, and other important system events.

Add a unique constraint for ticket numbers and sequence numbers within a session.

Design announcement delivery to support reconnecting displays without repeatedly announcing old calls.

10. Live Updates

Update the public display and staff dashboards automatically when:

A ticket is issued.
A ticket is called.
A ticket is accepted.
A ticket is skipped.
A customer is completed.
A service desk becomes unavailable.
A staff member changes availability.
The queue session starts or ends.

Prefer Laravel broadcasting when the required infrastructure is configured. Otherwise, use efficient polling with sensible intervals and cursor-based event retrieval.

Do not let clients directly determine ticket status or assignment.

11. Security and Authorization
Enforce authentication and role checks on protected routes.
Verify ticket ownership for staff actions.
Restrict administrative operations to admins.
Validate all state transitions on the backend.
Rate-limit login and OTP endpoints.
Protect state-changing requests against CSRF.
Encrypt SMS provider secrets.
Avoid exposing sensitive information through public APIs.
Keep the public display read-only.
Use HTTPS in production.
Log sensitive workflow changes without storing passwords, OTPs, or API secrets.
12. Testing Requirements

Create automated tests for:

Ticket collection and print-page generation.
Closed-session behavior.
Ticket number uniqueness.
Simultaneous staff assignment requests.
Prevention of multiple active tickets per staff member.
Service desk assignment rules.
Ticket ownership and authorization.
Correct ticket status transitions.
Skip and requeue behavior.
Session ending during concurrent requests.
Correct public display data.
Unique announcement event handling.
Reconnection without replaying old announcements.
OTP expiry, rate limits, and single-use behavior.
Hubtel/Nalo provider selection.
SMS failure handling.

Run concurrency tests against the actual production database engine where possible.

13. Implementation Phases
Inspect the Laravel project and configure authentication and roles.
Create migrations, models, and relationships.
Implement sessions, ticket issuance, and printable tickets.
Implement service desk management and atomic staff assignments.
Implement accept, skip, and complete workflows.
Build the dedicated public display and audible announcement system.
Build the admin dashboard and staff profiles.
Implement Hubtel/Nalo SMS and OTP password recovery.
Add automated tests, fix defects, and document deployment.

Complete and test each phase before moving on.

14. Final Acceptance Criteria

The application is complete when:

A customer collects a ticket and can print it.
Staff members at different desks can claim different customers concurrently.
Each called ticket identifies its assigned service desk.
A dedicated public screen visibly displays and audibly announces each new call.
Reconnecting the public display does not repeat old announcements.
Staff can accept, skip, and complete tickets.
Admins can configure desks and assign staff.
One End Session action stops ticket collection and new assignments.
Staff can securely reset passwords through Hubtel or Nalo SMS OTP.
Automated tests verify the critical workflow and concurrency rules.

Prioritize data integrity, reliable announcements, clear service desk assignments, and a working end-to-end queue before adding unnecessary features.

and finally, 
add a clear sound-test button to /display, because browsers may block automatic speech until someone interacts with the page. Once that is configured, the system can run continuously on a dedicated waiting-room screen.