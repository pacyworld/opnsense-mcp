# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

### Changed
- Re-vendored the Enchilada libraries onto the EnchiladaCurl split: `EnchiladaCurl` (abstract curl base) and `EnchiladaWorkerHTTP` (worker-pool client) are new, `EnchiladaMultiHTTP` now sits on the shared base, and `EnchiladaHTTP`, `EnchiladaMCP`, and `Enchilada\Tortilla` (including `OAuthCallbackHelper`) are refreshed from canonical upstream master

### Added
- `tests/enchilada-curl/` vendored from `daniel/engineering-docs` enchilada-extras/curl-behaviour-suite/: curl behaviour suite and door linter, now run in CI (FreeBSD and Windows jobs) against the vendored `libraries/`

## [1.1.3] - 2026-09-27

### Changed
- Tool descriptions tightened to cut `tools/list` size (2,290 -> 2,190 Qwen tokens); defaults for `limit` and `service_control` action are now stated

## [1.1.2] - 2026-09-13

### Fixed
- Re-vendored `EnchiladaMultiHTTP`: a response body that fails JSON decoding (e.g. a proxy error page) now completes the request with an error instead of leaving the await loop spinning forever
- Re-vendored Tortilla `StdioTransport`: in-flight requests are answered before stdin EOF is honoured, and a regular-file stdin degrades to blocking mode (`server < request.json` no longer hangs)

## [1.1.1] - 2026-09-13

### Fixed
- stdio stalls with hosts that close or never drain their pipes (Zed on Windows): re-vendored Tortilla `StdioTransport` 8367bb8 bounds no-EOF read retries, and diagnostics go through `EnchiladaMCP\Logger` (silent by default; `OPNSENSE_MCP_LOG` / `OPNSENSE_MCP_LOG_STDERR` opt-in) in both `bin/opnsense-mcp` and the PHAR stub. Adds `--log`/`--log-level`/`--io-mode` flags

## [1.1.0] - 2026-09-09

### Changed
- Vendored HTTP library layout moved from `libraries/HTTP/` to eponymous directories (`libraries/EnchiladaHTTP/`, `libraries/EnchiladaMultiHTTP/`), which the framework autoloader resolves natively for legacy global classes — the guarded `require_once` in `OPNsense\Client` is gone
- Replaced the internal `Mcp\*` protocol core with the canonical vendored Enchilada libraries: `EnchiladaMCP` (protocol core), `Enchilada\Tortilla` (stdio transport, loop-aware `HttpClient`), and `EnchiladaMultiHTTP` for the OPNsense API client. Tool names, descriptions, schemas, and annotations are unchanged. Unknown-tool calls now return tool-level error results with closest-name suggestions instead of protocol errors, and protocol version negotiation follows the shared core (2025-06-18 echoed, `2025-11-25` ceiling for the legacy handshake).

## [1.0.1] - 2026-06-30

### Added
- MCP tool annotations (`readOnlyHint`) on read-only tools: arp_table, gateway_status, routing_table, ndp_table, service_list, system_status, firmware_status, dhcp_leases, backup_list, list_instances, instance_info, interfaces, firewall_log, system_log, vpn_status. Patched directly into the internal `Mcp\McpTool`/`Mcp\ToolRegistry` classes (this server predates the canonical EnchiladaMCP library). Multi-action tools (list/get/create/update/delete in one tool) are intentionally left untagged since they can mutate state depending on the `action` parameter.

## [1.0.0] - 2026-04-16

### Added
- **Multi-instance management** — single MCP server for N OPNsense firewalls
- **MCP protocol layer** — JSON-RPC 2.0 over stdio (protocol version 2025-03-26)
- **30 MCP tools** across 13 tool classes:
  - InstanceTools: `list_instances`, `instance_info`
  - SystemTools: `system_status`, `firmware_status`
  - FirewallTools: `firewall_rules`, `firewall_aliases`, `firewall_apply`
  - DiagnosticsTools: `arp_table`, `ndp_table`, `gateway_status`, `routing_table`
  - InterfaceTools: `interfaces`, `vlans`
  - DhcpTools: `dhcp_leases`, `dhcp_reservations`
  - DnsTools: `dns_host_overrides`, `dns_domain_overrides`
  - NatTools: `nat_outbound`, `nat_port_forward`
  - VpnTools: `vpn_status`, `openvpn_instances`
  - ServiceTools: `service_list`, `service_control`
  - HaproxyTools: `haproxy_servers`, `haproxy_backends`
  - BackupTools: `backup_list`, `backup_create`, `backup_delete`
  - LogTools: `firewall_log`, `system_log`
- **OPNsense 26.x support** — Kea DHCP with legacy fallback, addr4/addr6 string format
- **Plugin-aware** — HAProxy tools gracefully handle missing os-haproxy plugin
- **CI** — Forgejo Actions (FreeBSD) + GitHub Actions (PHP 8.1–8.4 matrix)
- **113 unit tests**, 275 assertions
- Standalone autoloader fallback (works without Composer at runtime)

## [0.1.0] - 2026-04-16

### Added
- Initial release with 11 essential tools
- Core framework: MCP protocol, Client, InstanceManager
- Forgejo and GitHub Actions CI
