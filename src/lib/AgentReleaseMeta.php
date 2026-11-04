<?php

/** @deprecated Use GithubReleaseMeta::resolve(GithubReleaseMeta::PRODUCT_AGENT, ...) */
class AgentReleaseMeta {

	/**
	 * @return array{version:string,arch:string,url:string,sha256:string}|null
	 */
	static public function resolve(string $version, string $arch): ?array {
		return \GithubReleaseMeta::resolve(\GithubReleaseMeta::PRODUCT_AGENT, $version, $arch);
	}

	static public function normalizeArch(string $arch): string {
		return \GithubReleaseMeta::normalizeArch($arch);
	}
}
