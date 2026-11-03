<?php

namespace Api\V2;

/**
 * JSON envelope for /api/v2 and MCP tool results.
 */
class Envelope {

	/**
	 * @param mixed $data
	 * @param array<string,mixed> $meta
	 * @return array{status:int,data:mixed,meta?:array}
	 */
	static public function ok(mixed $data, array $meta = []): array {
		$out = ['status' => 0, 'data' => $data];
		if ($meta) {
			$out['meta'] = $meta;
		}
		return $out;
	}

	/**
	 * @return array{status:int,error:string}
	 */
	static public function error(string $message): array {
		return ['status' => 1, 'error' => $message];
	}
}
