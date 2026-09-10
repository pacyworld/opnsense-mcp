# UPGRADING — OPNsense MCP Server

Developer/agent-facing. Read this **before** touching `libraries/`,
`bin/`, or any network I/O.

## 1. The transport split (September 2026) changed the rules

Three independent libraries, vendored byte-identical from canonical
upstream **master** (pull first, then copy — never from another
consumer's tree):

| Vendored dir | Source | What it is |
|---|---|---|
| `libraries/EnchiladaMCP/` | `Enchilada/Extras` MCP/ | Protocol core. No I/O, no loop, no framework deps. |
| `libraries/Enchilada/Tortilla/` | `Enchilada/Tortilla` src/ | Wire transports, `EventLoop` port, `HttpClient`, `RequestEra`. |
| `libraries/EnchiladaHTTP/` | Extras HTTP/ | Blocking engine (legacy global class). **Frozen.** |
| `libraries/EnchiladaMultiHTTP/` | Extras HTTP/ | curl_multi engine (legacy global class). **Frozen.** |

(No Comal → stdio runs its supported blocking mode; progress
notifications keep the host alive during long calls.)

## 2. Non-negotiables

1. **Eponymous vendoring, no guards.** Legacy global classes live in
   `libraries/<Class>/<Class>.class.php`. Never add
   `class_exists`/`require_once` guards: the framework autoloader is
   golden; a miss means wrong placement or namespace — fix that.
2. **Only `bin/opnsense-mcp` knows both sides.** Transports take
   primitives (`handler`, `progress` callables), never `McpServer`.
3. **`ping` is gone in MCP 2026-07-28** — `notifications/progress` is
   the only in-call liveness; bounded-poll waits must invoke the
   injected progress callable every slice.
4. **No blocking network I/O inside tools.** All OPNsense API traffic
   goes through `OPNsense\Client` → `Tortilla\HttpClient`. New endpoints
   extend the Client; nothing in `tools/` may instantiate raw HTTP.
   - `EnchiladaHTTP`/`EnchiladaMultiHTTP` are frozen: no MCP hooks, no
     behavior changes.
   - Raw-socket I/O, if ever added, uses the
     `setTransport(?EventLoop, ?progress)` pattern (reference: mail-mcp
     `SocketImapClient`); writability waits probe between parked slices
     (mail-mcp#27 review).

## 3. This server's wiring

- Composition root appears in **two places** — keep them in sync:
  `bin/opnsense-mcp` and `bin/build-phar.php` both create the
  `StdioTransport` and wire `$manager->setHttpTransport($loop,
  $server->tick(...))` (loop is null without Comal; blocking poll emits
  progress per slice).
- Releases: CI builds the phar on tag; GitHub mirror gets the release
  automatically.

## 4. Regression gates (before every commit)

- `phpunit` — green.
- Liveness suite:
  `php ~/Documents/Projects/engineering-docs/enchilada-extras/mcp-liveness-suite/transport-liveness.php --lib=libraries`
  (blocking regime) — 9/9.
- Phar build + smoke (init/version/tools/ping/stderr/EOF).

## 5. Canonical references

- `engineering-docs/enchilada-extras/PLAN-TRANSPORT-SPLIT.md`
- `engineering-docs/enchilada-extras/DESIGN-RATIONALE-TRANSPORT-SPLIT.md`
- `engineering-docs/enchilada-extras/mcp-liveness-suite/README.md`
- `Enchilada/Extras` README (vendoring rules), `Enchilada/Tortilla` README
