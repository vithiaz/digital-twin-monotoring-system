# Digital Twin — Architecture & Structure

Date: 2025-10-19

## Overview
This Laravel + Livewire v3 + Volt application implements an OpenAQ v3 demo with SPA-like navigation. It adds a small set of pages to discover air-quality locations (defaulting to Indonesia settings), view a specific location’s details (latest measurements and sensors), inspect hourly series for a sensor, and browse a parameters glossary.

Key highlights:
- Tech: Laravel, Livewire v3, Volt (full-page components), Tailwind (via Vite).
- API: OpenAQ v3 using a small cached HTTP client (`App\\Services\\OpenAQ`).
- Pages: Discover, Location Detail, Parameters; plus a `sensor-chart` Livewire component.
- SPA feel: `wire:navigate` and Volt routes for fast transitions.

---

## Runtime Architecture

- HTTP Layer (Routes)
  - Volt registers full-page components directly as routes in `routes/web.php`:
    - `/` → `pages.discover` (Discover)
    - `/locations/{id}` → `pages.location-show` (Location Detail)
    - `/parameters` → `pages.parameters` (Parameters)
  - Auth-related routes exist for settings and dashboard (Fortify/Volt) but are not required for OpenAQ browsing.

- UI Composition
  - Layout: `resources/views/layouts/app.blade.php` renders global nav and includes `@vite` assets and Livewire directives.
  - Pages (Volt full-page blades, placed under `resources/views/livewire/pages`):
    - `discover.blade.php`
    - `location-show.blade.php`
    - `parameters.blade.php`
  - Component:
    - `resources/views/livewire/sensor-chart.blade.php` (embedded on the location page when `sensor` query param is present)

- Data Access
  - Service: `App\\Services\\OpenAQ`
    - Centralizes HTTP calls to OpenAQ v3.
    - Adds `X-API-Key` header from `config/services.php` (backed by `.env`).
    - Caches responses using `Cache::remember()` for snappy UX and rate-limit friendliness.
    - Endpoints wrapped:
      - `GET /v3/locations` (list)
      - `GET /v3/locations/{id}` (single)
      - `GET /v3/locations/{id}/latest`
      - `GET /v3/locations/{id}/sensors`
      - `GET /v3/sensors/{id}`
      - `GET /v3/sensors/{id}/hours`
      - `GET /v3/parameters`

---

## Project Structure

- app/
  - Services/
    - `OpenAQ.php` — cached client for OpenAQ v3
  - Http/
    - Controllers/ — Default Laravel controllers (not used for Volt pages)
- resources/
  - views/
    - layouts/
      - `app.blade.php` — global navigation + `@vite`/Livewire assets
    - livewire/
      - pages/
        - `discover.blade.php` — Discover stations (filters + results + rate-limit badge)
        - `location-show.blade.php` — Location detail (latest table + sensors list + optional hourly component)
        - `parameters.blade.php` — Glossary of parameters
      - `sensor-chart.blade.php` — Hourly series table for a sensor
    - components/ — Existing layout components from the starter
    - livewire/auth|settings — Existing auth/settings blades from starter
- routes/
  - `web.php` — Volt routes for public pages + authenticated settings routes
- config/
  - `services.php` — Adds `openaq` config (base, key)
- .env
  - `OPENAQ_BASE`, `OPENAQ_KEY`, `DEFAULT_ISO`, `DEFAULT_LAT`, `DEFAULT_LON`, `DEFAULT_RADIUS`

---

## Pages and Layouts (Current State)

### Layout: `resources/views/layouts/app.blade.php`
- Purpose: Unified shell for demo pages.
- Includes:
  - `@vite(['resources/css/app.css','resources/js/app.js'])`
  - `@livewireStyles` and `@livewireScripts`
  - Top navigation with links:
    - Discover (route `discover`)
    - Parameters (route `parameters`)
- Renders `{{ $slot }}` for page content.

### Discover: `resources/views/livewire/pages/discover.blade.php`
- State:
  - `iso`, `lat`, `lon`, `radius`, `parameterId`, `limit`, `page`, `results`, `quota`, `error`.
  - Defaults pulled from `.env` for Indonesia/Manado region.
- Actions:
  - `$load(OpenAQ $aq)` builds query with `iso`, optional `coordinates+radius`, optional `parameters_id` and fetches `/v3/locations`.
  - Mount triggers initial load.
- UI:
  - Filter form with Livewire bindings.
  - Rate-limit badge from response headers `x-ratelimit-*`.
  - Results grid:
    - Name
    - Country name/code and timezone
    - Parameter tags (`displayName` fallback to `display_name`/`name`)
    - Provider name
  - Card links to `locations.show` route with the location id.

### Location Detail: `resources/views/livewire/pages/location-show.blade.php`
- State:
  - `id` (route param), `location`, `latest`, `sensors`, `error`.
- Actions:
  - `$load(OpenAQ $aq)` fetches `/v3/locations/{id}`, `/latest`, and `/sensors`.
  - Mount triggers initial load.
- UI:
  - Header with location name
  - Meta line with country name/code, timezone, provider name
  - Latest table:
    - Parameter name (`displayName` fallback)
    - Value
    - Unit (`unit` or `units`)
    - Time (string or `{local|utc}` object normalized)
  - Sensors list:
    - Parameter (name with displayName fallback)
    - Sensor ID and units
    - Link to add `?sensor={id}` query param
  - When `sensor` is present:
    - Renders `<livewire:sensor-chart :sensor-id="..." />` below

### Parameters: `resources/views/livewire/pages/parameters.blade.php`
- State: `rows`, `error`.
- Actions: On mount, fetch `/v3/parameters` (cached for 3600s).
- UI: Simple table with `displayName`, `units`, `description`.

### Component: `resources/views/livewire/sensor-chart.blade.php`
- State:
  - `sensorId`, `from` (today minus 7 days), `to` (today), `series`, `error`.
- Actions:
  - `$load(OpenAQ $aq)` calls `/v3/sensors/{id}/hours` with date range, limit 1000.
- UI: A compact hourly table with datetime and value.

---

## API Integration Details

- Config & Secrets
  - `config/services.php` defines:
    ```php
    'openaq' => [
        'base' => env('OPENAQ_BASE', 'https://api.openaq.org'),
        'key'  => env('OPENAQ_KEY'),
    ],
    ```
  - `.env` supplies values for base URL, API key, and default geospatial filters.

- HTTP Client (OpenAQ Service)
  - Uses Laravel HTTP client with `X-API-Key` header on each request.
  - Central `get()` method caches responses using a deterministic key on path+query.
  - Returns both parsed JSON (`data`) and rate-limit headers (`headers`).

- Schema Notes (OpenAQ v3)
  - Locations:
    - `country` is an object: `{ id, code, name }`.
    - `timezone` is a string.
    - `parameters` array items often use `displayName` and `units`.
  - Latest & Sensors:
    - Parameter object commonly uses `displayName` and `units`.
    - Datetime fields may be strings or objects with `local` and `utc`.

- Caching strategy
  - Lists and detail calls: 60s default TTL.
  - Parameters list: 3600s TTL.

---

## Routes Summary

- Public
  - `GET /` → Volt page `pages.discover` (name: `discover`)
  - `GET /locations/{id}` → Volt page `pages.location-show` (name: `locations.show`)
  - `GET /parameters` → Volt page `pages.parameters` (name: `parameters`)

- Authenticated (from starter scaffold)
  - `GET /dashboard` → `dashboard.blade.php` (middleware: auth, verified)
  - Settings pages under `/settings/*` via Volt (profile, password, appearance, two-factor)

---

## Data Flow (Example: Discover → Location → Hours)

1. Discover loads using defaults from `.env` and queries `/v3/locations` with `iso`, optional `coordinates+radius`, optional `parameters_id`.
2. User clicks a station card → navigates to `/locations/{id}`.
3. Location detail page loads `/v3/locations/{id}`, `/latest`, `/sensors`.
4. If a sensor is chosen (`?sensor=...`), the page embeds `sensor-chart`, which queries `/v3/sensors/{id}/hours` to render an hourly table.

---

## Known UX/Data Constraints

- Use either `coordinates+radius` or `bbox`—not both.
- Respect rate limits; headers are displayed on Discover to increase transparency.
- Volt pages must live under `resources/views/livewire` to be resolved, hence `livewire/pages/*`.

---

## Next Steps / Improvements

- Replace the hourly table with a line chart (Chart.js) and small Alpine.js block.
- Pagination controls for Discover (`page`, `limit`).
- CSV export for sensor-hourly data.
- Saved searches (iso + lat/lon + radius + parameterId) with persistence.
- Provider filters and enriched provider details on cards.

---

## Appendix: Key Files

- `app/Services/OpenAQ.php` — API client (with caching and rate-limit headers)
- `resources/views/layouts/app.blade.php` — layout shell for demo pages
- `resources/views/livewire/pages/discover.blade.php` — stations search/filters
- `resources/views/livewire/pages/location-show.blade.php` — details + sensors
- `resources/views/livewire/sensor-chart.blade.php` — hourly table for sensor
- `resources/views/livewire/pages/parameters.blade.php` — parameters glossary
- `routes/web.php` — Volt route registration for public pages
