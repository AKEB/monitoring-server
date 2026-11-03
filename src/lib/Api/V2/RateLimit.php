<?php

namespace Api\V2;

/**
 * Softer rate limit for management API / MCP (not the 5s status lock).
 */
class RateLimit {

	const WINDOW_SECONDS = 60;
	const MAX_REQUESTS = 120;

	static public function cacheKey(int $tokenId): string {
		return 'api_v2_rl_'.$tokenId;
	}
}
