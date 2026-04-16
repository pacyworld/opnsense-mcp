<?php
/**
 * OPNsense MCP Server - Client Exception
 *
 * Exception thrown by the OPNsense API client.
 *
 * @package    OPNsenseMCP\OPNsense
 * @author     Daniel Morante
 * @copyright  2026 The Daniel Morante Company, Inc.
 * @license    BSD-2-Clause
 */

namespace OPNsense;

/**
 * ClientException - Exception for OPNsense API client errors.
 *
 * Thrown when the API returns an error, authentication fails,
 * or a connection issue occurs.
 */
class ClientException extends \RuntimeException
{
}
