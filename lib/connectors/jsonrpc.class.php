<?php
namespace rrd\connectors {
	require_once __DIR__ . '/../json-rpc-php/jsonRPCClient.php';

	class jsonrpc extends \jsonRPCClient{
		function __construct($cfg) {
			parent::__construct((string) $cfg->url);
		}
	}
}
?>