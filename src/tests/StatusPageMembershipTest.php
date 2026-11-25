<?php

error_reporting(E_ALL);

/**
 * Status page membership must NOT depend on api_enabled.
 * api_enabled remains only for GET /api/v1/status/.
 */
class StatusPageMembershipTest extends PHPUnit\Framework\TestCase {

	function test_slug_normalization_and_validation() {
		$this->assertSame('my-status', \StatusPageMembership::normalizeSlug(' My Status '));
		$this->assertSame('a_b-1', \StatusPageMembership::normalizeSlug('a_b-1'));
		$this->assertTrue(\StatusPageMembership::isSlugValid('demo'));
		$this->assertTrue(\StatusPageMembership::isSlugValid('my-status_1'));
		$this->assertFalse(\StatusPageMembership::isSlugValid(''));
		$this->assertFalse(\StatusPageMembership::isSlugValid('Bad Slug'));
		$this->assertFalse(\StatusPageMembership::isSlugValid('абв'));
		$this->assertFalse(\StatusPageMembership::isSlugValid(str_repeat('a', 65)));
	}

	function test_folder_membership_includes_leaf_even_when_api_enabled_off() {
		$hash = [
			1 => [
				'id' => 1,
				'title' => 'Prod',
				'type' => \Monitors::TYPE_FOLDER,
				'status' => 1,
				'parent_id' => 0,
				'api_enabled' => 0,
			],
			2 => [
				'id' => 2,
				'title' => 'API',
				'type' => \Monitors::TYPE_HTTPS,
				'status' => 1,
				'parent_id' => 1,
				'api_enabled' => 0,
			],
			3 => [
				'id' => 3,
				'title' => 'OptedIn',
				'type' => \Monitors::TYPE_TCP,
				'status' => 1,
				'parent_id' => 1,
				'api_enabled' => 1,
			],
		];
		$resolved = \StatusPageMembership::resolveGroups([1], [], $hash);
		$this->assertContains(2, $resolved['monitor_ids']);
		$this->assertContains(3, $resolved['monitor_ids']);
		$this->assertCount(1, $resolved['groups']);
		$this->assertSame(1, $resolved['groups'][0]['id']);
		$this->assertEqualsCanonicalizing([2, 3], $resolved['groups'][0]['monitor_ids']);
	}

	function test_explicit_monitor_membership_ignores_api_enabled() {
		$hash = [
			10 => [
				'id' => 10,
				'title' => 'HiddenFromStatusApi',
				'type' => \Monitors::TYPE_PING,
				'status' => 1,
				'parent_id' => 0,
				'api_enabled' => 0,
			],
		];
		$resolved = \StatusPageMembership::resolveGroups([], [10], $hash);
		$this->assertSame([10], $resolved['monitor_ids']);
		$this->assertSame('Other', $resolved['groups'][0]['title']);
	}

	function test_disabled_monitor_excluded_even_if_selected() {
		$hash = [
			5 => [
				'id' => 5,
				'title' => 'Off',
				'type' => \Monitors::TYPE_HTTPS,
				'status' => 0,
				'parent_id' => 0,
				'api_enabled' => 1,
			],
		];
		$resolved = \StatusPageMembership::resolveGroups([], [5], $hash);
		$this->assertSame([], $resolved['monitor_ids']);
	}

	function test_folders_are_never_leaf_resources() {
		$hash = [
			1 => [
				'id' => 1,
				'title' => 'Folder',
				'type' => \Monitors::TYPE_FOLDER,
				'status' => 1,
				'parent_id' => 0,
				'api_enabled' => 1,
			],
		];
		$resolved = \StatusPageMembership::resolveGroups([1], [1], $hash);
		$this->assertSame([], $resolved['monitor_ids']);
	}

	function test_status_api_candidate_still_requires_api_enabled() {
		$hidden = [
			'id' => 2,
			'type' => \Monitors::TYPE_HTTPS,
			'api_enabled' => 0,
		];
		$optedIn = [
			'id' => 3,
			'type' => \Monitors::TYPE_HTTPS,
			'api_enabled' => 1,
		];
		$this->assertFalse(\EntityValidation::isStatusApiCandidate($hidden));
		$this->assertTrue(\EntityValidation::isStatusApiCandidate($optedIn));
		// Status PAGE leaf: type + enabled status only — api_enabled must not matter.
		$this->assertTrue(\StatusPageMembership::isStatusPageLeafCandidate([
			'id' => 2,
			'type' => \Monitors::TYPE_HTTPS,
			'status' => 1,
			'api_enabled' => 0,
		]));
		$this->assertFalse(\StatusPageMembership::isStatusPageLeafCandidate([
			'id' => 9,
			'type' => \Monitors::TYPE_FOLDER,
			'status' => 1,
			'api_enabled' => 1,
		]));
	}

	function test_incident_states() {
		$this->assertTrue(\StatusPageMembership::isIncidentStateValid('investigating'));
		$this->assertTrue(\StatusPageMembership::isIncidentStateValid('identified'));
		$this->assertTrue(\StatusPageMembership::isIncidentStateValid('monitoring'));
		$this->assertTrue(\StatusPageMembership::isIncidentStateValid('resolved'));
		$this->assertFalse(\StatusPageMembership::isIncidentStateValid('broken'));
	}

	function test_overall_status_summary() {
		$this->assertSame('ok', \StatusPageBuilder::overallFromStatuses(['up', 'up'])['code']);
		$this->assertSame('down', \StatusPageBuilder::overallFromStatuses(['down', 'down'])['code']);
		$this->assertSame('partial', \StatusPageBuilder::overallFromStatuses(['up', 'down'])['code']);
		$this->assertSame('partial', \StatusPageBuilder::overallFromStatuses(['up', 'degraded'])['code']);
		$this->assertSame('unknown', \StatusPageBuilder::overallFromStatuses(['unknown'])['code']);
		$this->assertSame('empty', \StatusPageBuilder::overallFromStatuses([])['code']);
		$this->assertSame('maint', \StatusPageBuilder::overallFromStatuses(['maintenance', 'up'])['code']);
	}
}
