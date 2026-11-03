<?php

/**
 * CSRF tokens for mutating admin UI actions (delete/copy).
 * Token is bound to the current Sessions id via Cache.
 */
class Csrf {

	public const FIELD = '_csrf';

	static public function token(): string {
		$sessionId = \Sessions::get_session_id();
		if ($sessionId === '') {
			return '';
		}
		$cache = new \Cache('csrf_'.md5($sessionId), 'default', true);
		if ($cache->isValid()) {
			$existing = $cache->get();
			if (is_string($existing) && strlen($existing) >= 32) {
				return $existing;
			}
		}
		$token = bin2hex(random_bytes(32));
		$cache->update($token, 7 * 86400);
		return $token;
	}

	static public function validate(?string $token = null): bool {
		$expected = static::token();
		if ($expected === '') {
			return false;
		}
		$got = $token;
		if ($got === null) {
			$got = $_POST[self::FIELD] ?? '';
		}
		return is_string($got) && $got !== '' && hash_equals($expected, $got);
	}

	/** Require POST + valid CSRF token; otherwise 403. */
	static public function requirePost(): void {
		$method = strtoupper(strval($_SERVER['REQUEST_METHOD'] ?? 'GET'));
		if ($method !== 'POST' || !static::validate()) {
			e403();
		}
	}

	/**
	 * JS statements: create and submit a POST form with CSRF.
	 * $urlExpr — JS expression for the form action (e.g. "'/path/' + id + '/'").
	 */
	static public function jsPostToUrl(string $urlExpr): string {
		$token = json_encode(static::token(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
		$field = json_encode(self::FIELD, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
		return 'var __csrfForm=document.createElement("form");__csrfForm.method="POST";__csrfForm.action=('.$urlExpr.');'
			.'var __csrfInput=document.createElement("input");__csrfInput.type="hidden";__csrfInput.name='.$field.';__csrfInput.value='.$token.';'
			.'__csrfForm.appendChild(__csrfInput);document.body.appendChild(__csrfForm);__csrfForm.submit();';
	}
}
