<?php

/**
 * Extra Mattermost post helpers (get/update) without patching vendor Mattermost.
 */
class MattermostPosts {

	static public function getPost(\Mattermost $mattermost, string $postId): ?array {
		$postId = trim($postId);
		if ($postId === '') {
			return null;
		}
		$result = $mattermost->getDriver()?->getPostModel()?->getPost($postId);
		if (!$result) {
			return null;
		}
		if ($result->getStatusCode() != 200 && $result->getStatusCode() != 201) {
			return null;
		}
		$post = json_decode($result->getBody(), true);
		return is_array($post) ? $post : null;
	}

	static public function updatePost(\Mattermost $mattermost, string $postId, string $message, ?array $props = null): bool {
		$postId = trim($postId);
		if ($postId === '') {
			return false;
		}
		$payload = [
			'id' => $postId,
			'message' => $message,
		];
		if (isset($props) && is_array($props)) {
			$payload['props'] = $props;
		}
		$result = $mattermost->getDriver()?->getPostModel()?->updatePost($postId, $payload);
		if (!$result) {
			return false;
		}
		if ($result->getStatusCode() != 200 && $result->getStatusCode() != 201) {
			return false;
		}
		return true;
	}
}
