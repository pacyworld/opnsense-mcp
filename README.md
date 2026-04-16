# opnsense-mcp

[![CI](https://github.com/pacyworld/opnsense-mcp/actions/workflows/ci.yml/badge.svg)](https://github.com/pacyworld/opnsense-mcp/actions/workflows/ci.yml)
[![License: BSD-2-Clause](https://img.shields.io/badge/License-BSD_2--Clause-blue.svg)](LICENSE)
[![PHP: >=8.1](https://img.shields.io/badge/PHP-%3E%3D8.1-purple.svg)](https://www.php.net/)

A PHP MCP (Model Context Protocol) server for managing multiple OPNsense firewalls from a single server instance. Built for AI-assisted firewall management through IDEs like Windsurf, Cursor, and Claude Desktop.

## Why?

Existing OPNsense MCP servers only support a single firewall per server instance. If you have 5 firewalls, you need 5 copies of the MCP server in your IDE config.

**opnsense-mcp** manages all your firewalls from one server instance. Every tool accepts an optional `instance` parameter to target a specific firewall, with a configurable default.

## Features

- **Multi-instance** — manage N firewalls from a single MCP server
- **30 tools** — firewall, NAT, DHCP, DNS, VPN, interfaces, services, logs, backups
- **PHP 8.2+** — zero runtime dependencies beyond `ext-curl` and `ext-json`
- **MCP 2025-03-26** — latest Model Context Protocol specification
- **Testable** — dependency injection for HTTP client, 112 unit tests
- **Modular tools** — organized by domain across 13 tool classes

## Quick Start

### 1. Download

Download the PHAR (single file, no dependencies):

```bash
curl -LO https://github.com/pacyworld/opnsense-mcp/releases/latest/download/opnsense-mcp.phar
chmod +x opnsense-mcp.phar
sudo mv opnsense-mcp.phar /usr/local/bin/
```

Or clone the repo for development — see [Development](#development) below.

### 2. Configure

Create an `instances.json` file:

```json
{
    "default": "gateway",
    "instances": {
        "gateway": {
            "url": "https://192.168.1.1",
            "api_key": "your_api_key",
            "api_secret": "your_api_secret",
            "verify_ssl": false,
            "description": "Main gateway"
        },
        "branch": {
            "url": "https://10.0.0.1",
            "api_key": "your_api_key",
            "api_secret": "your_api_secret",
            "verify_ssl": false,
            "description": "Branch office firewall"
        }
    }
}
```

> **Generating API keys:** In OPNsense, go to **System → Access → Users**, edit a user, scroll to **API keys**, and click **+** to generate a key/secret pair.

### 3. Add to your IDE

#### Windsurf / Cursor

Add to your MCP server configuration:

```json
{
    "opnsense": {
        "command": "/usr/local/bin/opnsense-mcp.phar",
        "env": {
            "OPNSENSE_CONFIG": "/path/to/instances.json"
        }
    }
}
```

#### Claude Desktop

Add to `claude_desktop_config.json`:

```json
{
    "mcpServers": {
        "opnsense": {
            "command": "/usr/local/bin/opnsense-mcp.phar",
            "env": {
                "OPNSENSE_CONFIG": "/path/to/instances.json"
            }
        }
    }
}
```

> **Tip:** If your system doesn't support the PHAR shebang, use `"command": "php"` with `"args": ["/usr/local/bin/opnsense-mcp.phar"]` instead.

### 4. Use

Ask your AI assistant to manage your firewalls:

- *"List my firewall instances"*
- *"Show the firewall rules on the gateway"*
- *"What's the firmware status of the branch firewall?"*
- *"Show the ARP table on gateway"*

## Configuration

The config file path is resolved in order:

1. `OPNSENSE_CONFIG` environment variable
2. `--config=/path/to/instances.json` CLI argument
3. `config/instances.json` (relative to project)
4. `~/.config/opnsense-mcp/instances.json`
5. `/usr/local/etc/opnsense-mcp/instances.json`

## Development

### Requirements

- PHP 8.2 or later (PHPUnit 11 requires 8.2+)
- ext-curl
- ext-json
- PHPUnit 11 (system package or via `shivammathur/setup-php`)

No Composer required — the project uses the Enchilada Framework autoloader with vendored libraries.

### Setup

```bash
git clone https://pacyworld.dev/pacyworld/opnsense-mcp.git
cd opnsense-mcp

# Copy config and add your OPNsense API credentials
cp config/instances.json.sample config/instances.json
# Edit config/instances.json with your firewall URL, API key, and secret
```

### Testing

```bash
# Run all unit tests (113 tests, no network required)
phpunit --colors=always

# Syntax check
find classes tools bin system libraries -name "*.php" | xargs -n1 php -l
```

### Testing Against a Real OPNsense Instance

To test the MCP server against a real OPNsense firewall:

1. Create `config/instances.json` with your firewall credentials (see [Configuration](#configuration))
2. Run the server interactively via stdin:

```bash
# Start the server
OPNSENSE_CONFIG=config/instances.json php bin/opnsense-mcp

# Then paste JSON-RPC requests on stdin, for example:
{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-03-26","capabilities":{},"clientInfo":{"name":"test","version":"1.0"}}}
{"jsonrpc":"2.0","id":2,"method":"tools/list","params":{}}
{"jsonrpc":"2.0","id":3,"method":"tools/call","params":{"name":"system_status","arguments":{}}}
{"jsonrpc":"2.0","id":4,"method":"tools/call","params":{"name":"arp_table","arguments":{}}}
```

> **Generating API keys:** In OPNsense, go to **System → Access → Users**, edit a user, scroll to **API keys**, and click **+** to generate a key/secret pair. The user needs appropriate permissions for the endpoints you want to access.

### Building the PHAR

```bash
php -d phar.readonly=0 bin/build-phar.php
# Output: build/opnsense-mcp.phar (~97 KB)
```

### Project Structure

```
opnsense-mcp/
├── bin/opnsense-mcp          # stdio entry point
├── classes/
│   ├── Mcp/                  # MCP protocol layer (JSON-RPC, tool registry)
│   └── OPNsense/             # API client and instance manager
├── libraries/
│   └── EnchiladaHTTP/        # Vendored HTTP client
├── system/                   # Enchilada Framework (autoloader, app config)
├── tools/                    # MCP tool implementations (13 classes)
├── tests/                    # PHPUnit tests + fixtures (113 tests)
├── config/                   # Configuration templates
└── phpunit.xml               # Test configuration
```

## Tools

| Category | Tool | Description |
|----------|------|-------------|
| **Instances** | `list_instances` | List all configured firewall instances |
| | `instance_info` | Live system/firmware info for an instance |
| **System** | `system_status` | Product version, architecture, update status |
| | `firmware_status` | Detailed firmware and update information |
| **Firewall** | `firewall_rules` | List/get/create/update/delete/toggle filter rules |
| | `firewall_aliases` | List/get/create/update/delete aliases |
| | `firewall_apply` | Apply pending firewall changes |
| **Diagnostics** | `arp_table` | MAC-to-IP mappings |
| | `ndp_table` | IPv6 neighbor discovery |
| | `gateway_status` | Gateway online/offline, latency, packet loss |
| | `routing_table` | Static routes |
| **Interfaces** | `interfaces` | All interfaces with IPs, status, media |
| | `vlans` | VLAN CRUD |
| **DHCP** | `dhcp_leases` | Active DHCP leases (Kea + legacy) |
| | `dhcp_reservations` | Static reservation CRUD |
| **DNS** | `dns_host_overrides` | Unbound host override CRUD |
| | `dns_domain_overrides` | Unbound domain override CRUD |
| **NAT** | `nat_outbound` | Source NAT rule CRUD |
| | `nat_port_forward` | Destination NAT rule CRUD |
| **VPN** | `vpn_status` | WireGuard, OpenVPN, IPsec tunnel status |
| | `openvpn_instances` | OpenVPN instance CRUD |
| **Services** | `service_list` | All services with running status |
| | `service_control` | Start/stop/restart a service |
| **HAProxy** | `haproxy_servers` | Server CRUD (requires os-haproxy) |
| | `haproxy_backends` | Backend CRUD (requires os-haproxy) |
| **Backup** | `backup_list` | List config backups |
| | `backup_create` | Create a new backup |
| | `backup_delete` | Delete a backup |
| **Logs** | `firewall_log` | Recent blocked/passed packets |
| | `system_log` | Recent system log entries |

Every tool accepts an optional `instance` parameter to target a specific firewall.

## Architecture

```
IDE (Windsurf/Cursor/Claude) ──stdio──> php bin/opnsense-mcp ──HTTPS──> OPNsense 1
                                                               ──HTTPS──> OPNsense 2
                                                               ──HTTPS──> OPNsense N
```

The MCP server runs as a local child process, communicating via stdin/stdout JSON-RPC 2.0. HTTP traffic is outbound only — from the PHP process to your OPNsense firewalls' REST APIs.

## License

BSD 2-Clause — see [LICENSE](LICENSE).

Copyright (c) 2026, The Daniel Morante Company, Inc.
