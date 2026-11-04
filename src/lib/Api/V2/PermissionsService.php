<?php

namespace Api\V2;

class PermissionsService {

	/** @param array<string,mixed> $user */
	static public function get(array $user, string $subject, int $subjectId): array {
		if ($subject === '' || $subjectId < 1) {
			return Envelope::error('Invalid subject') + ['http' => 422];
		}
		$store = Runtime::store();
		if ($store) {
			$rows = [];
			foreach ($store->all('permissions') as $row) {
				if (strval($row['subject'] ?? '') === $subject && intval($row['subject_id'] ?? 0) === $subjectId) {
					$rows[] = $row;
				}
			}
			return Envelope::ok($rows, ['total' => count($rows)]);
		}
		if (!Acl::can($user, $subject, $subjectId, ACCESS_READ) && !Acl::can($user, $subject, $subjectId, READ)) {
			return Envelope::error('Forbidden') + ['http' => 403];
		}
		$rows = \ObjectPermissions::data([
			'subject' => $subject,
			'subject_id' => $subjectId,
		]) ?: [];
		return Envelope::ok(array_values($rows), ['total' => count($rows)]);
	}

	/**
	 * @param array<string,mixed> $user
	 * @param list<array<string,mixed>> $rows
	 */
	static public function set(array $user, string $subject, int $subjectId, array $rows): array {
		if ($subject === '' || $subjectId < 1) {
			return Envelope::error('Invalid subject') + ['http' => 422];
		}
		$store = Runtime::store();
		if ($store) {
			foreach ($store->all('permissions') as $existing) {
				if (strval($existing['subject'] ?? '') === $subject && intval($existing['subject_id'] ?? 0) === $subjectId) {
					$store->delete('permissions', intval($existing['id']));
				}
			}
			$saved = [];
			foreach ($rows as $row) {
				$saved[] = $store->insert('permissions', [
					'subject' => $subject,
					'subject_id' => $subjectId,
					'object' => strval($row['object'] ?? 'user'),
					'object_id' => intval($row['object_id'] ?? 0),
					READ => intval($row['read'] ?? $row[READ] ?? 0),
					WRITE => intval($row['write'] ?? $row[WRITE] ?? 0),
					DELETE => intval($row['delete'] ?? $row[DELETE] ?? 0),
					ACCESS_READ => intval($row['access_read'] ?? $row[ACCESS_READ] ?? 0),
					ACCESS_WRITE => intval($row['access_write'] ?? $row[ACCESS_WRITE] ?? 0),
					ACCESS_CHANGE => intval($row['access_change'] ?? $row[ACCESS_CHANGE] ?? 0),
				]);
			}
			return Envelope::ok($saved, ['total' => count($saved)]);
		}
		if (!Acl::can($user, $subject, $subjectId, ACCESS_CHANGE) && !Acl::can($user, $subject, $subjectId, ACCESS_WRITE)) {
			return Envelope::error('Forbidden') + ['http' => 403];
		}
		$existing = \ObjectPermissions::data([
			'subject' => $subject,
			'subject_id' => $subjectId,
		]) ?: [];
		foreach ($existing as $row) {
			if (!empty($row['id'])) {
				\ObjectPermissions::delete(intval($row['id']));
			}
		}
		$saved = [];
		foreach ($rows as $row) {
			$objectPermission = [
				'subject' => $subject,
				'subject_id' => $subjectId,
				'object' => strval($row['object'] ?? 'user'),
				'object_id' => intval($row['object_id'] ?? 0),
				READ => intval($row['read'] ?? $row[READ] ?? 0),
				WRITE => intval($row['write'] ?? $row[WRITE] ?? 0),
				DELETE => intval($row['delete'] ?? $row[DELETE] ?? 0),
				ACCESS_READ => intval($row['access_read'] ?? $row[ACCESS_READ] ?? 0),
				ACCESS_WRITE => intval($row['access_write'] ?? $row[ACCESS_WRITE] ?? 0),
				ACCESS_CHANGE => intval($row['access_change'] ?? $row[ACCESS_CHANGE] ?? 0),
			];
			$objectPermission['id'] = \ObjectPermissions::save($objectPermission);
			$saved[] = $objectPermission;
		}
		return Envelope::ok($saved, ['total' => count($saved)]);
	}
}
