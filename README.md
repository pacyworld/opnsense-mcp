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
- **PHP 8.1+** — zero runtime dependencies beyond `ext-curl` and `ext-json`
- **MCP 2025-03-26** — latest Model Context Protocol specification
- **Testable** — dependency injection for HTTP client, 47+ unit tests
- **Modular tools** — organized by domain (firewall, diagnostics, DHCP, DNS, VPN, etc.)

## Quick Start

### 1. Clone

```bash
git clone https://github.com/pacyworld/opnsense-mcp.git
cd opnsense-mcp
```

### 2. Configure

Copy the sample config and add your OPNsense instances:

```bash
cp config/instances.json.sample config/instances.json
```

Edit `config/instances.json`:

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
        "command": "php",
        "args": ["/path/to/opnsense-mcp/bin/opnsense-mcp"],
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
            "command": "php",
            "args": ["/path/to/opnsense-mcp/bin/opnsense-mcp"],
            "env": {
                "OPNSENSE_CONFIG": "/path/to/instances.json"
            }
        }
    }
}
```

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

- PHP 8.1 or later
- ext-curl
- ext-json
- Composer (for dev dependencies only)

### Setup

```bash
git clone https://github.com/pacyworld/opnsense-mcp.git
cd opnsense-mcp
composer install
```

### Testing

```bash
# Run all tests
vendor/bin/phpunit

# With colors
vendor/bin/phpunit --colors=always

# Syntax check
find classes tools bin -name "*.php" | xargs -n1 php -l
```

### Project Structure

```
opnsense-mcp/
├── bin/opnsense-mcp          # stdio entry point
├── classes/
│   ├── Mcp/                  # MCP protocol layer (JSON-RPC, tool registry)
│   └── OPNsense/             # API client and instance manager
├── tools/                    # MCP tool implementations
├── tests/                    # PHPUnit tests + fixtures
├── config/                   # Configuration templates
├── composer.json             # Dev dependencies (PHPUnit)
└── phpunit.xml               # Test configuration
```

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
