<?php

namespace Api;

use Api\V2\Envelope;

/**
 * Loads the shared OpenAPI document for Swagger UI and MCP api_docs.
 */
class OpenApiSpec {

	/** @var array<string,mixed>|null */
	static private ?array $cached = null;

	static public function filePath(): string {
		return dirname(__DIR__, 2) . '/openapi/openapi.json';
	}

	/**
	 * @return array<string,mixed>
	 */
	static public function load(): array {
		if (self::$cached !== null) {
			return self::$cached;
		}
		$path = static::filePath();
		$raw = @file_get_contents($path);
		if ($raw === false || $raw === '') {
			throw new \RuntimeException('OpenAPI spec not found');
		}
		$data = json_decode($raw, true);
		if (!is_array($data)) {
			throw new \RuntimeException('OpenAPI spec is invalid JSON');
		}
		self::$cached = $data;
		return self::$cached;
	}

	/** Raw JSON bytes for HTTP responses. */
	static public function rawJson(): string {
		$path = static::filePath();
		$raw = @file_get_contents($path);
		if ($raw === false || $raw === '') {
			throw new \RuntimeException('OpenAPI spec not found');
		}
		return $raw;
	}

	/**
	 * MCP / filtered view of the spec.
	 *
	 * @param array<string,mixed> $arguments
	 * @return array{status:int,data?:mixed,error?:string,http?:int}
	 */
	static public function query(array $arguments = []): array {
		try {
			$spec = static::load();
		} catch (\Throwable $e) {
			return Envelope::error($e->getMessage()) + ['http' => 500];
		}

		$tag = trim(strval($arguments['tag'] ?? ''));
		$path = trim(strval($arguments['path'] ?? ''));
		$operation = strtolower(trim(strval($arguments['operation'] ?? '')));

		if ($tag === '' && $path === '') {
			return Envelope::ok(static::index($spec));
		}

		if ($path !== '') {
			$paths = $spec['paths'] ?? [];
			if (!is_array($paths) || !isset($paths[$path])) {
				$normalized = static::normalizePathKey($path, array_keys(is_array($paths) ? $paths : []));
				if ($normalized === null) {
					return Envelope::error('Path not found in OpenAPI spec') + ['http' => 404];
				}
				$path = $normalized;
			}
			$pathItem = $paths[$path];
			if (!is_array($pathItem)) {
				return Envelope::error('Path not found in OpenAPI spec') + ['http' => 404];
			}
			if ($operation !== '') {
				if (!isset($pathItem[$operation]) || !is_array($pathItem[$operation])) {
					return Envelope::error('Operation not found for path') + ['http' => 404];
				}
				$pathItem = [$operation => $pathItem[$operation]];
				if (isset($paths[$path]['parameters'])) {
					$pathItem['parameters'] = $paths[$path]['parameters'];
				}
			}
			return Envelope::ok([
				'openapi' => $spec['openapi'] ?? '3.0.3',
				'info' => $spec['info'] ?? new \stdClass(),
				'path' => $path,
				'pathItem' => $pathItem,
				'components' => static::relatedComponents($spec, $pathItem),
			]);
		}

		// tag filter
		$filteredPaths = [];
		foreach (($spec['paths'] ?? []) as $p => $item) {
			if (!is_array($item)) {
				continue;
			}
			$ops = [];
			foreach ($item as $method => $op) {
				if ($method === 'parameters' || !is_array($op)) {
					continue;
				}
				$tags = $op['tags'] ?? [];
				if (is_array($tags) && in_array($tag, $tags, true)) {
					$ops[$method] = $op;
				}
			}
			if ($ops) {
				if (isset($item['parameters'])) {
					$ops['parameters'] = $item['parameters'];
				}
				$filteredPaths[$p] = $ops;
			}
		}
		if (!$filteredPaths) {
			return Envelope::error('Tag not found in OpenAPI spec') + ['http' => 404];
		}
		return Envelope::ok([
			'openapi' => $spec['openapi'] ?? '3.0.3',
			'info' => $spec['info'] ?? new \stdClass(),
			'tag' => $tag,
			'paths' => $filteredPaths,
			'components' => static::relatedComponents($spec, $filteredPaths),
		]);
	}

	/**
	 * @param array<string,mixed> $spec
	 * @return array{info:mixed,tags:mixed,operations:list<array{method:string,path:string,tag?:string,summary?:string}>}
	 */
	static public function index(array $spec): array {
		$operations = [];
		foreach (($spec['paths'] ?? []) as $path => $item) {
			if (!is_array($item)) {
				continue;
			}
			foreach ($item as $method => $op) {
				if ($method === 'parameters' || !is_array($op)) {
					continue;
				}
				$tags = $op['tags'] ?? [];
				$operations[] = [
					'method' => strtoupper(strval($method)),
					'path' => strval($path),
					'tag' => is_array($tags) && $tags ? strval($tags[0]) : '',
					'summary' => strval($op['summary'] ?? ''),
				];
			}
		}
		return [
			'info' => $spec['info'] ?? new \stdClass(),
			'tags' => $spec['tags'] ?? [],
			'operations' => $operations,
			'ui' => '/api/docs/',
		];
	}

	/**
	 * @param list<string|int> $keys
	 */
	static private function normalizePathKey(string $path, array $keys): ?string {
		$candidates = [$path];
		if (!str_starts_with($path, '/')) {
			$candidates[] = '/' . $path;
		}
		if (!str_ends_with($path, '/')) {
			$candidates[] = $path . '/';
			if (!str_starts_with($path, '/')) {
				$candidates[] = '/' . $path . '/';
			}
		}
		foreach ($candidates as $c) {
			if (in_array($c, $keys, true)) {
				return $c;
			}
		}
		return null;
	}

	/**
	 * Collect component schemas referenced from a path slice.
	 *
	 * @param array<string,mixed> $spec
	 * @param mixed $slice
	 * @return array<string,mixed>
	 */
	static private function relatedComponents(array $spec, mixed $slice): array {
		$schemas = $spec['components']['schemas'] ?? [];
		if (!is_array($schemas)) {
			return [];
		}
		$names = [];
		static::collectSchemaRefs($slice, $names);
		$outSchemas = [];
		foreach (array_keys($names) as $name) {
			if (isset($schemas[$name])) {
				$outSchemas[$name] = $schemas[$name];
				// one more hop for nested $ref inside collected schemas
				static::collectSchemaRefs($schemas[$name], $names);
			}
		}
		foreach (array_keys($names) as $name) {
			if (isset($schemas[$name]) && !isset($outSchemas[$name])) {
				$outSchemas[$name] = $schemas[$name];
			}
		}
		$out = ['schemas' => $outSchemas ?: new \stdClass()];
		if (isset($spec['components']['securitySchemes'])) {
			$out['securitySchemes'] = $spec['components']['securitySchemes'];
		}
		return $out;
	}

	/**
	 * @param mixed $node
	 * @param array<string,true> $names
	 */
	static private function collectSchemaRefs(mixed $node, array &$names): void {
		if (!is_array($node)) {
			return;
		}
		if (isset($node['$ref']) && is_string($node['$ref'])) {
			if (preg_match('~#/components/schemas/([A-Za-z0-9_]+)~', $node['$ref'], $m)) {
				$names[$m[1]] = true;
			}
		}
		foreach ($node as $value) {
			static::collectSchemaRefs($value, $names);
		}
	}

	/** @internal for tests */
	static public function clearCache(): void {
		self::$cached = null;
	}
}
