# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

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
