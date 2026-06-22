# local_rhssosync — RHSSO user synchronisation for Moodle 4.1

`local_rhssosync` synchronises the **direct members of a single RHSSO (Red Hat
Single Sign-On 7.6.2 / Keycloak) group** into Moodle 4.1. Users authenticate
through the existing Moodle **`oauth2`** authentication plugin; this plugin only
provisions, updates, suspends and reactivates the matching Moodle accounts.

It is designed for a containerised deployment (OpenShift, PostgreSQL, multiple
pods) and uses the Moodle Lock API so that only one synchronisation runs at a
time across the cluster.

---

## What it does

* Reads RHSSO connection settings from the plugin admin UI (no secrets in code).
* Authenticates to the **RHSSO Admin REST API** using the **Client Credentials**
  grant and a dedicated service-account client.
* Lets an administrator pick the RHSSO group with a live **autocomplete** backed
  by a secured Moodle external (AJAX) function — the browser never talks to
  RHSSO directly.
* Maps RHSSO attributes (`sAMAccountName`, `firstName`, `lastName`, `email`) to
  Moodle user fields. The username source is configurable.
* Creates and updates **managed** Moodle users via the Moodle user API
  (`user_create_user` / `user_update_user`) with `auth = oauth2`. It never writes
  to core user tables directly and never manages local passwords.
* Maintains its own mapping table; a user is **managed only** when an explicit
  mapping row says so. It never assumes that every `oauth2` user is managed.
* **Suspends** managed users who left the group, and **reactivates** them if they
  return — but only if this plugin performed the original suspension.
* Records **conflicts** when an RHSSO username already exists as an unmanaged
  Moodle user. Conflicting users are never modified, adopted or suspended.
* Protects against accidental mass suspension with a configurable **maximum
  suspension percentage** (default 25%) and a **complete-pagination** guard:
  suspensions are skipped entirely if the RHSSO member list could not be
  retrieved in full.
* Stores structured **run reports** and **per-user logs**, viewable in the admin
  UI, with configurable retention.
* Implements the Moodle **Privacy API**.

## What it deliberately does not do

No direct Active Directory access, no subgroup recursion, no cohort or course
enrolment, no user deletion, no password sync, no new auth plugin, no automatic
adoption of existing users, no bidirectional sync, no Moodle core changes.

---

## Requirements

* Moodle 4.1 (2022112800) or later 4.1.x.
* PHP 7.4–8.1 (as supported by Moodle 4.1).
* RHSSO 7.6.2 (Keycloak 18) reachable from the Moodle pods.
* A working Moodle cron.

## Installation

1. Copy the plugin so that it lives at `local/rhssosync` inside your Moodle
   codebase, **or** install the ZIP via *Site administration ▸ Plugins ▸ Install
   plugins*. The ZIP contains a single top-level `rhssosync/` directory that
   unpacks into `local/`.
2. Visit *Site administration ▸ Notifications* (or run
   `php admin/cli/upgrade.php`) to create the database tables.
3. Grant the `local/rhssosync:manage` and `local/rhssosync:viewreports`
   capabilities to the appropriate role (both default to `manager`).

The plugin ships with prebuilt AMD JavaScript in `amd/build`, so **no Node.js or
JavaScript build step is needed in production**.

## RHSSO service-account configuration

In the RHSSO admin console, in the realm that holds your users (default
`ADUsers`):

1. Create a **confidential client** (e.g. `moodle-sync`) with:
   * *Client authentication* **On**.
   * *Standard flow* / *Direct access grants* **Off**.
   * *Service accounts roles* **On** (this enables the Client Credentials grant).
2. On the client's **Service account roles** tab, assign the realm-management
   roles the plugin needs (see below).
3. Copy the **Client ID** and **Client secret** into the plugin connection
   settings.

### Required service-account permissions

The service account needs read access to users and groups through the Admin REST
API. Assign these `realm-management` client roles to the service account:

* `view-users` — required to read group members and user attributes.
* `query-groups` and `query-users` — required for the group search/autocomplete.

`view-realm` may also be granted if your RHSSO version requires it for the group
endpoints. No write roles are required: the plugin never modifies RHSSO.

## Plugin configuration

*Site administration ▸ Plugins ▸ Local plugins ▸ RHSSO user synchronisation*

* **Connection settings** — base URL (do **not** include the realm; include
  `/auth` only if your server uses it), realm, client ID, client secret, TLS
  verification, timeouts, page size.
* **Group selection** — search and select the RHSSO group (stores group id and
  path).
* **Synchronisation settings** — attribute mapping, username source, update
  policies, maximum suspension percentage, log retention.

## Scheduled synchronisation

A scheduled task `local_rhssosync\task\scheduled_sync_task` runs **every 12
hours** by default. Change the schedule in *Site administration ▸ Server ▸
Scheduled tasks*.

A daily `cleanup_task` purges run reports older than the retention period.

### Cron on OpenShift

Moodle requires `php admin/cli/cron.php` to run regularly (at least every
minute). On OpenShift, run cron via a dedicated mechanism rather than relying on
web requests:

* a **Kubernetes/OpenShift CronJob** that executes
  `php /var/www/html/admin/cli/cron.php` every minute against the shared
  database and Moodledata, **or**
* a sidecar/looping container running the same command.

Because the plugin takes a cluster-wide lock, it is safe for cron to fire on
multiple pods; only one synchronisation will run at a time.

## Manual run

* **Sync now** (dashboard) queues an ad-hoc task; the synchronisation runs in
  cron, never inside the browser request.
* **Preview sync** performs a full dry run and shows the intended changes.
* **CLI**: `php local/rhssosync/cli/sync.php [--dry-run]`.

## Recommended first run

1. Install the plugin and run the upgrade.
2. Configure the RHSSO connection settings.
3. Click **Test connection**.
4. Open **Group selection** and pick the group with autocomplete.
5. Run **Preview sync** and review intended creates, suspensions and conflicts.
6. Resolve any conflicts and confirm the intended suspensions look correct.
7. Click **Sync now** and let cron run the ad-hoc task, then review the report.

## Database tables

| Table | Purpose |
|-------|---------|
| `local_rhssosync_users` | Managed-user mapping (RHSSO user id ↔ Moodle user id ↔ group id, managed flag, suspended-by-plugin flag, status). |
| `local_rhssosync_runs` | One row per synchronisation run with counters and status. |
| `local_rhssosync_log` | Per-user actions for each run. |
| `local_rhssosync_conflicts` | Unmanaged Moodle users colliding with an RHSSO username. |

## Security notes

* The client secret, access token and `Authorization` header are never logged,
  displayed in reports, or returned to the browser.
* All admin pages require capabilities, run in the system context and validate
  the session key on state-changing actions.
* The group autocomplete is served by a capability-protected external function.

## Tests

PHPUnit tests live in `tests/` and cover attribute mapping, pagination,
managed-user detection, conflicts, suspension/reactivation eligibility,
partial-sync protection, the suspension threshold and idempotency. Run them from
your Moodle root, for example:

```
vendor/bin/phpunit --filter local_rhssosync
```

## Licence

GNU GPL v3 or later.
