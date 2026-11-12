<?php
namespace App\Servers;

class Edit extends \Routing_Parent implements \Routing_Interface {
	private int $server_id = 0;
	/** @var array<string,mixed> */
	private array $server = [];
	private bool $can_read = false;
	private bool $can_write = false;
	private bool $can_create = false;
	private bool $can_secrets = false;
	/** @var array<string,mixed>|null */
	private ?array $payload = null;
	/** @var list<array<string,mixed>> */
	private array $metrics = [];
	/** @var list<array<string,mixed>> */
	private array $auth_events = [];

	public function Run(int $server_id = 0) {
		$this->server_id = intval($server_id ?? 0);
		$this->check_auth();
		$this->check_permissions();
		$this->processRequest();
		$this->get_data();
		$this->template = new \Template('Server edit');
		$this->print_header();
		$this->print_forms();
		$this->print_javascript();
	}

	protected function handlePostData(array $data) {
		if (!$data) return;
		if (!empty($data['requestAgentUpdate']) && !empty($data['id'])) {
			$this->request_agent_update(intval($data['id']));
			return;
		}
		$this->save_server($data);
	}

	private function request_agent_update(int $id): void {
		if ($id < 1 || !\Sessions::checkPermission(\Servers::PERMISSION_ACCESS, $id, WRITE)) {
			$this->error = \T::Server_Edit_PermissionDenied();
			return;
		}
		$server = \Servers::get(['id' => $id]);
		if (!$server) {
			$this->error = \T::Server_Edit_NotFound();
			return;
		}
		$override = \AppOptions::normalizeVersion(strval($this->server['desired_agent_version'] ?? $server['desired_agent_version'] ?? ''));
		if (isset($_POST['desired_agent_version'])) {
			$override = \AppOptions::normalizeVersion(strval($_POST['desired_agent_version']));
		}
		\AgentUpdate::requestNow($id, $override);
		$this->success = \T::Server_Update_Requested();
	}

	/** @param array<string,mixed> $data */
	private function save_server(array $data): void {
		$params = [];
		do {
			if (isset($data['id']) && trim(strval($data['id'])) !== '') {
				if ($this->server_id != intval($data['id'])) {
					$this->error = \T::Server_Edit_NotFound();
					break;
				}
				$params['id'] = intval($data['id']);
			}
			if (isset($data['title']) && trim(strval($data['title'])) !== '') {
				$title = trim(strval($data['title']));
				if (!\EntityValidation::isTitleLengthValid($title)) {
					$this->error = \T::Server_Edit_NameLengthError();
					break;
				}
				$params['title'] = $title;
			}
			if (isset($data['status']) && trim(strval($data['status'])) !== '') {
				$params['status'] = \EntityValidation::normalizeSwitchStatus(trim(strval($data['status'])));
			} else {
				$params['status'] = 0;
			}
			if (array_key_exists('desired_agent_version', $data)) {
				$params['desired_agent_version'] = \AppOptions::normalizeVersion(strval($data['desired_agent_version']));
			}

			if (!empty($params['id'])) {
				if (!\Sessions::checkPermission(\Servers::PERMISSION_ACCESS, $params['id'], WRITE)) {
					$this->error = \T::Server_Edit_PermissionDenied();
					break;
				}
				$old = \Servers::get(['id' => $params['id']]);
				if (!$old) {
					$this->error = \T::Server_Edit_NotFound();
					break;
				}
				if (!empty($data['regenerate_key'])) {
					if (!\Sessions::checkPermission(\Servers::PERMISSION_SECRETS, $params['id'], WRITE)
						&& !\Sessions::checkPermission(\Servers::PERMISSION_ACCESS, $params['id'], WRITE)) {
						$this->error = \T::Server_Edit_PermissionDenied();
						break;
					}
					$params['agent_key_hash'] = \Servers::generateAgentKeyHash();
				}
				if (isset($params['desired_agent_version'])
					&& strval($params['desired_agent_version']) !== strval($old['desired_agent_version'] ?? '')
				) {
					$params['update_requested_at'] = 0;
					$params['update_error'] = '';
				}
				foreach ($params as $k => $v) {
					if (($old[$k] ?? null) == $v && $k !== 'id') {
						unset($params[$k]);
					}
				}
				if (!$params || (count($params) === 1 && isset($params['id']))) {
					$this->success = \T::Server_Edit_NotingChanged();
					break;
				}
				$params['update_time'] = time();
				$params['_mode'] = \DB\Common::CSMODE_UPDATE;
				\Servers::save($params);
				\Logs::update_log(\Servers::LOGS_OBJECT, $params['id'], $old, $params);
				common_redirect('/admin/servers/edit/'.$params['id'].'/');
			} else {
				if (!\Sessions::checkPermission(\Servers::PERMISSION_CREATE, 0, WRITE)) {
					$this->error = \T::Server_Edit_PermissionDenied();
					break;
				}
				if (empty($params['title'])) {
					$this->error = \T::Server_Edit_NameLengthError();
					break;
				}
				$params['agent_key_hash'] = \Servers::generateAgentKeyHash();
				$params['create_time'] = time();
				$params['update_time'] = time();
				$params['_mode'] = \DB\Common::CSMODE_INSERT;
				$id = \Servers::save($params);
				if (!$id) {
					$this->error = \T::Server_Edit_NotFound();
					break;
				}
				\Logs::create_log(\Servers::LOGS_OBJECT, $id, $params);
				if (!\Sessions::in_group(\Groups::ADMIN_GROUP_ID, intval(\Sessions::currentUser()['id']))) {
					foreach (\Servers::permissions_hash() as $permission => $_) {
						$object_permission = [
							'object' => 'user',
							'object_id' => intval(\Sessions::currentUser()['id']),
							'subject' => $permission,
							'subject_id' => $id,
							READ => 1,
							WRITE => 1,
							DELETE => 1,
							ACCESS_READ => 1,
							ACCESS_WRITE => 1,
							ACCESS_CHANGE => 1,
							'create_time' => time(),
							'update_time' => time(),
							'_mode' => \DB\Common::CSMODE_INSERT,
						];
						$object_permission['id'] = \ObjectPermissions::save($object_permission);
						$log_id = \Logs::create_log(\ObjectPermissions::LOGS_OBJECT, $object_permission['id'], $object_permission);
						\Logs::add_tag($log_id, \Servers::LOGS_OBJECT, $id);
					}
				}
				common_redirect('/admin/servers/edit/'.$id.'/');
			}
		} while (false);
		foreach ($data as $key => $value) {
			$this->server[$key] = $value;
		}
	}

	private function check_permissions() {
		if ($this->server_id) {
			$this->server = \Servers::get(['id' => $this->server_id]) ?: [];
			if (!$this->server) {
				common_redirect('/admin/servers/');
			}
			$this->can_read = \Sessions::checkPermission(\Servers::PERMISSION_ACCESS, $this->server_id, READ);
			$this->can_write = \Sessions::checkPermission(\Servers::PERMISSION_ACCESS, $this->server_id, WRITE);
			$this->can_secrets = \Sessions::checkPermission(\Servers::PERMISSION_SECRETS, $this->server_id, READ)
				|| $this->can_write;
			if (!$this->can_read) {
				e403();
			}
		} else {
			$this->can_read = \Sessions::checkPermission(\Servers::PERMISSION_ACCESS, -1, READ);
			$this->can_create = \Sessions::checkPermission(\Servers::PERMISSION_CREATE, 0, WRITE);
			$this->can_secrets = true;
			if (!$this->can_read && !$this->can_create) {
				e403();
			}
		}
	}

	private function get_data() {
		if (!empty($this->server['id'])) {
			$this->server['create_date'] = !empty($this->server['create_time']) ? \UserTime::format(intval($this->server['create_time'])) : '';
			$this->server['update_date'] = !empty($this->server['update_time']) ? \UserTime::format(intval($this->server['update_time'])) : '';
			$this->server['last_seen_date'] = !empty($this->server['last_seen_time']) ? \UserTime::format(intval($this->server['last_seen_time'])) : '';
			$this->server['online'] = \Servers::isOnline($this->server);
			if (!empty($this->server['last_payload'])) {
				$decoded = @json_decode(strval($this->server['last_payload']), true);
				$this->payload = is_array($decoded) ? $decoded : null;
			}
			$since = time() - 24 * 3600;
			// Agents push ~every 15s (~5760 rows/day). Taking ASC LIMIT 1500 showed only the
			// oldest ~6h of the window (looks like "future" morning labels when viewed at night).
			$rawMetrics = array_values(\ServerMetrics::data(
				false,
				sql_pholder(' AND `server_id` = ? AND `collected_at` >= ? ORDER BY `collected_at` ASC', $this->server['id'], $since)
			) ?: []);
			$this->metrics = $this->downsampleMetrics($rawMetrics, 1500);
			$this->auth_events = array_values(\ServerAuthEvents::data(
				false,
				sql_pholder(' AND `server_id` = ? ORDER BY `event_time` DESC LIMIT 100', $this->server['id'])
			) ?: []);
		} else {
			$this->server['status'] ??= 1;
			$this->server['online'] = false;
		}
	}

	/**
	 * Evenly sample metric rows so charts cover the full requested window.
	 *
	 * @param list<array<string,mixed>> $rows
	 * @return list<array<string,mixed>>
	 */
	private function downsampleMetrics(array $rows, int $maxPoints): array {
		$n = count($rows);
		if ($n <= $maxPoints || $maxPoints < 2) {
			return $rows;
		}
		$out = [];
		$last = $n - 1;
		for ($i = 0; $i < $maxPoints; $i++) {
			$idx = (int) round($i * $last / ($maxPoints - 1));
			$out[] = $rows[$idx];
		}
		return $out;
	}

	private function print_header() {
		?>
		<div class="float-start"><h2><i class="bi bi-hdd-rack"></i> <?=\T::Server_PageTitle();?></h2></div>
		<div class="clearfix"></div>
		<?php
	}

	private function print_forms() {
		?>
		<div class="row d-flex justify-content-center">
			<div class="card bg-transparent col-xl-10 p-4 mt-2 mb-3">
				<div class="card-header bg-transparent"><h3>
					<?php
					if (!empty($this->server['id'])) {
						echo \T::Server_Edit_EditTitle(htmlspecialchars(strval($this->server['title'] ?? ''), ENT_QUOTES, 'UTF-8'), $this->server['id']);
					} else {
						echo \T::Server_Edit_CreateTitle();
					}
					?>
				</h3></div>
				<form class="card-body needs-validation" method="post" novalidate>
					<?= \Csrf::field(); ?>
					<input type="hidden" name="id" value="<?=htmlspecialchars(strval($this->server['id'] ?? ''), ENT_QUOTES, 'UTF-8');?>"/>
					<?php
					echo $this->template->html_input("title", htmlspecialchars(strval($this->server['title'] ?? ''), ENT_QUOTES, 'UTF-8'), \T::Server_Edit_Title(), true, [
						'class2' => 'col-xs-12 col-sm-12 col-md-9 col-lg-8 col-xl-7 col-xxl-6',
						'readonly' => !($this->can_write || $this->can_create),
					]);
					echo $this->template->html_switch("status", intval($this->server['status'] ?? 0), \T::Server_Edit_Status(), false, [
						'readonly' => !($this->can_write || $this->can_create),
					]);
					if (!empty($this->server['id'])) {
						$key = $this->can_secrets ? strval($this->server['agent_key_hash'] ?? '') : '***';
						echo $this->template->html_input("agent_key_hash", $key, \T::Server_Edit_AgentKeyHash(), false, [
							'class2' => 'col-xs-12 col-sm-12 col-md-9 col-lg-8 col-xl-7 col-xxl-6',
							'readonly' => true,
						]);
						if ($this->can_write) {
							echo '<div class="mb-3 form-check"><input class="form-check-input" type="checkbox" name="regenerate_key" value="1" id="regenerate_key"><label class="form-check-label" for="regenerate_key">'.\T::Server_Edit_RegenerateKey().'</label></div>';
						}
						echo $this->template->html_input("create_date", $this->server['create_date'] ?? '', \T::Server_Edit_CreateTime(), false, ['readonly' => true]);
						echo $this->template->html_input("last_seen_date", $this->server['last_seen_date'] ?? '', \T::Server_Edit_LastSeen(), false, ['readonly' => true]);
						$currentAgent = htmlspecialchars(strval($this->server['agent_version'] ?? ''), ENT_QUOTES, 'UTF-8');
						$effectiveDesired = htmlspecialchars(\Servers::effectiveDesiredAgentVersion($this->server), ENT_QUOTES, 'UTF-8');
						echo $this->template->html_input("agent_version_display", $currentAgent !== '' ? $currentAgent : '—', \T::Server_Table_AgentVersion(), false, ['readonly' => true]);
						echo $this->template->html_input("desired_agent_version", htmlspecialchars(strval($this->server['desired_agent_version'] ?? ''), ENT_QUOTES, 'UTF-8'), \T::Server_Update_OverrideVersion(), false, [
							'class2' => 'col-xs-12 col-sm-12 col-md-9 col-lg-8 col-xl-7 col-xxl-6',
							'readonly' => !$this->can_write,
							'placeholder' => \T::Server_Update_OverridePlaceholder(),
						]);
						echo '<p class="mb-2 text-muted">'.\T::Server_Update_EffectiveDesired($effectiveDesired !== '' ? $effectiveDesired : '—').'</p>';
						if (!empty($this->server['update_error'])) {
							echo '<p class="mb-2 text-danger"><i class="bi bi-exclamation-triangle"></i> '.htmlspecialchars(strval($this->server['update_error']), ENT_QUOTES, 'UTF-8').'</p>';
						}
						echo '<p class="mb-2 text-muted">'.\T::Server_Edit_PermissionsHint().'</p>';
					} else {
						echo '<p class="mb-3 text-muted">'.\T::Server_Edit_CreateInstallHint().'</p>';
					}
					?>
					<div class="d-flex flex-row-reverse">
						<button type="submit" class="btn btn-primary me-2" name="<?=!empty($this->server['id']) ? 'editServer' : 'createServer';?>" value="true"><?=(!empty($this->server['id']) ? \T::Server_Edit_ChangeButton() : \T::Server_Edit_CreateButton());?></button>
						<?php if (!empty($this->server['id']) && $this->can_write) { ?>
							<button type="submit" class="btn btn-outline-warning me-2" name="requestAgentUpdate" value="true"><?=\T::Server_Update_RequestNow();?></button>
						<?php } ?>
					</div>
				</form>
			</div>

			<?php if (!empty($this->server['id'])) {
				if (empty($this->server['online'])) {
					$this->print_install_docs();
				}
				$this->print_status_cards();
				$this->print_listeners();
				$this->print_charts();
				$this->print_docker();
				$this->print_systemd();
				$this->print_proxmox();
				$this->print_processes();
				$this->print_auth();
			} ?>
		</div>
		<?php
	}

	private function print_install_docs() {
		$key = $this->can_secrets ? strval($this->server['agent_key_hash'] ?? '') : '***';
		// Prefer APP_BASE_URL; else request scheme (incl. X-Forwarded-Proto behind proxy).
		$serverHost = function_exists('common_app_base_url')
			? common_app_base_url()
			: rtrim(strval(\Config::getInstance()->app_base_url ?? ''), '/');
		$script = $this->agentInstallScript($serverHost, $key);
		?>
		<div class="card bg-transparent col-xl-10 p-4 mt-2 mb-3">
			<div class="card-header bg-transparent d-flex justify-content-between align-items-center">
				<h4 class="mb-0"><?=\T::Server_Edit_InstallTitle();?></h4>
				<button class="btn btn-outline-secondary btn-sm copy-agent-install" type="button" title="Copy">
					<i class="bi bi-clipboard"></i> <?=\T::Server_Edit_InstallCopy();?>
				</button>
			</div>
			<div class="card-body">
				<p class="text-muted"><?=\T::Server_Edit_InstallHint();?></p>
				<pre class="bg-dark text-light p-3 rounded" id="agent-install-script" style="white-space:pre-wrap;"><?=htmlspecialchars($script, ENT_QUOTES, 'UTF-8');?></pre>
			</div>
		</div>
		<?php
	}

	private function agentInstallScript(string $serverHost, string $agentKey): string {
		$serverHost = rtrim($serverHost, '/').'/';
		$version = 'v1.3.0';
		return <<<SH
#!/bin/bash
set -euo pipefail

VERSION="{$version}"
ARCH="\$(uname -m)"
case "\$ARCH" in
  x86_64|amd64) BINARY="monitoring-agent-linux-amd64" ;;
  aarch64|arm64) BINARY="monitoring-agent-linux-arm64" ;;
  *) echo "Unsupported arch: \$ARCH"; exit 1 ;;
esac

mkdir -p /opt/monitoring-agent
cd /opt/monitoring-agent

wget -q -O "\$BINARY" "https://github.com/AKEB/monitoring-agent/releases/download/\${VERSION}/\${BINARY}"
wget -q -O monitoring-agent.service "https://raw.githubusercontent.com/AKEB/monitoring-agent/refs/heads/main/deploy/monitoring-agent.service"
chmod +x "\$BINARY"
mv -f "\$BINARY" monitoring-agent
cp -f monitoring-agent.service /etc/systemd/system/monitoring-agent.service

cat >/opt/monitoring-agent/.env <<EOF
TZ=Europe/Moscow
SERVER_HOST={$serverHost}
AGENT_KEY_HASH={$agentKey}
REPORT_INTERVAL_SEC=15
EOF

systemctl daemon-reload
systemctl enable --now monitoring-agent
systemctl --no-pager --full status monitoring-agent || true
SH;
	}

	private function print_status_cards() {
		$host = is_array($this->payload['host'] ?? null) ? $this->payload['host'] : [];
		$cpu = is_array($this->payload['cpu'] ?? null) ? $this->payload['cpu'] : [];
		$mem = is_array($this->payload['memory'] ?? null) ? $this->payload['memory'] : [];
		$swap = is_array($this->payload['swap'] ?? null) ? $this->payload['swap'] : [];
		$disks = is_array($this->payload['disks'] ?? null) ? $this->payload['disks'] : [];
		$network = is_array($this->payload['network']['interfaces'] ?? null) ? $this->payload['network']['interfaces'] : [];
		$online = !empty($this->server['online']);
		?>
		<div class="card bg-transparent col-xl-10 p-4 mt-2 mb-3">
			<div class="card-header bg-transparent"><h4><?=\T::Server_Edit_StatusTitle();?> —
				<?=$online ? '<span class="text-success">online</span>' : '<span class="text-danger">offline</span>';?>
			</h4></div>
			<div class="card-body">
				<div class="row">
					<div class="col-md-4 mb-2"><strong><?=\T::Server_Table_Hostname();?>:</strong> <?=htmlspecialchars(strval($host['hostname'] ?? $this->server['hostname'] ?? ''));?></div>
					<div class="col-md-4 mb-2"><strong>OS:</strong> <?=htmlspecialchars(trim(strval($host['platform'] ?? '').' '.strval($host['platform_version'] ?? '')));?></div>
					<div class="col-md-4 mb-2"><strong>Arch:</strong> <?=htmlspecialchars(strval($host['arch'] ?? ''));?></div>
					<div class="col-md-4 mb-2"><strong>Kernel:</strong> <?=htmlspecialchars(strval($host['kernel'] ?? ''));?></div>
					<div class="col-md-4 mb-2"><strong>Virt:</strong> <?=htmlspecialchars(strval($host['virtualization'] ?? ''));?></div>
					<div class="col-md-4 mb-2"><strong>Agent:</strong> <?=htmlspecialchars(strval($this->server['agent_version'] ?? ''));?></div>
					<div class="col-md-4 mb-2"><strong>CPU:</strong> <?=number_format(floatval($cpu['usage_pct'] ?? $this->server['cpu_usage_pct'] ?? 0), 1);?>% (<?=intval($cpu['cores'] ?? 0);?> cores, load <?=number_format(floatval($cpu['load1'] ?? 0), 2);?>)</div>
					<div class="col-md-4 mb-2"><strong>RAM:</strong> <?=number_format(floatval($mem['used_pct'] ?? 0), 1);?>%</div>
					<div class="col-md-4 mb-2"><strong>Swap:</strong> <?=number_format(floatval($swap['used_pct'] ?? 0), 1);?>%</div>
					<div class="col-md-4 mb-2"><strong><?=\T::Server_Edit_Uptime();?>:</strong> <?=htmlspecialchars($this->formatUptime(intval($host['uptime_sec'] ?? $this->server['uptime_sec'] ?? 0)));?></div>
					<?php
					$tempMax = $this->server['temp_c_max'] ?? null;
					$temps = is_array(($this->payload ?? [])['temperatures'] ?? null) ? ($this->payload['temperatures'] ?? []) : [];
					if ($tempMax === null && $temps) {
						foreach ($temps as $t) {
							if (!is_array($t)) continue;
							$tc = floatval($t['temp_c'] ?? 0);
							if ($tempMax === null || $tc > floatval($tempMax)) {
								$tempMax = $tc;
							}
						}
					}
					if ($tempMax !== null && $tempMax !== '') { ?>
						<div class="col-md-4 mb-2"><strong><?=\T::Server_Edit_Temperature();?>:</strong> <?=number_format(floatval($tempMax), 1);?>°C</div>
					<?php } ?>
				</div>
				<?php if ($disks) { ?>
					<h5 class="mt-3"><?=\T::Server_Edit_Disks();?></h5>
					<table class="table table-sm table-transparent" id="serverDisksTable"><thead><tr><th>Mount</th><th>Device</th><th>FS</th><th>Used %</th></tr></thead><tbody>
					<?php foreach ($disks as $d) {
						if (!is_array($d)) continue;
						$usedPct = floatval($d['used_pct'] ?? 0);
						?>
						<tr>
							<td><?=htmlspecialchars(strval($d['mount'] ?? ''));?></td>
							<td><?=htmlspecialchars(strval($d['device'] ?? ''));?></td>
							<td><?=htmlspecialchars(strval($d['fstype'] ?? ''));?></td>
							<td data-order="<?=htmlspecialchars(sprintf('%.3f', $usedPct), ENT_QUOTES, 'UTF-8');?>"><?=number_format($usedPct, 1);?>%</td>
						</tr>
					<?php } ?>
					</tbody></table>
				<?php } ?>
				<?php if ($network) { ?>
					<h5 class="mt-3"><?=\T::Server_Edit_Network();?></h5>
					<table class="table table-sm table-transparent" id="serverNetworkTable"><thead><tr><th>Iface</th><th>IPs</th><th>Download Mbps</th><th>Upload Mbps</th></tr></thead><tbody>
					<?php foreach ($network as $n) {
						if (!is_array($n)) continue;
						$addrs = is_array($n['addrs'] ?? null) ? $n['addrs'] : [];
						$rxMbps = floatval($n['rx_bps'] ?? 0) * 8.0 / 1000000.0;
						$txMbps = floatval($n['tx_bps'] ?? 0) * 8.0 / 1000000.0;
						?>
						<tr>
							<td><?=htmlspecialchars(strval($n['name'] ?? ''));?></td>
							<td><small><?=htmlspecialchars(implode(', ', $addrs));?></small></td>
							<td data-order="<?=htmlspecialchars(sprintf('%.6f', $rxMbps), ENT_QUOTES, 'UTF-8');?>"><?=number_format($rxMbps, 2);?></td>
							<td data-order="<?=htmlspecialchars(sprintf('%.6f', $txMbps), ENT_QUOTES, 'UTF-8');?>"><?=number_format($txMbps, 2);?></td>
						</tr>
					<?php } ?>
					</tbody></table>
				<?php } ?>
			</div>
		</div>
		<?php
	}

	private function print_listeners() {
		$listeners = is_array($this->payload['listeners'] ?? null) ? $this->payload['listeners'] : [];
		if (!$listeners) {
			return;
		}
		$normalized = [];
		foreach ($listeners as $l) {
			if (!is_array($l)) {
				continue;
			}
			$protocol = strtolower(trim(strval($l['protocol'] ?? '')));
			if ($protocol !== 'tcp' && $protocol !== 'udp') {
				continue;
			}
			$port = intval($l['port'] ?? 0);
			if ($port < 1 || $port > 65535) {
				continue;
			}
			$normalized[] = [
				'protocol' => $protocol,
				'ip' => strval($l['ip'] ?? ''),
				'port' => $port,
				'service' => strval($l['service'] ?? ''),
				'process' => strval($l['process'] ?? ''),
				'pid' => intval($l['pid'] ?? 0),
			];
		}
		if (!$normalized) {
			return;
		}
		usort($normalized, static function (array $a, array $b): int {
			if ($a['protocol'] !== $b['protocol']) {
				return $a['protocol'] <=> $b['protocol'];
			}
			if ($a['port'] !== $b['port']) {
				return $a['port'] <=> $b['port'];
			}
			return strcmp($a['ip'], $b['ip']);
		});
		?>
		<div class="card bg-transparent col-xl-10 p-4 mt-2 mb-3">
			<div class="card-header bg-transparent"><h4><?=\T::Server_Edit_ListenersTitle();?></h4></div>
			<div class="card-body table-responsive">
				<table class="table table-sm table-transparent" id="serverListenersTable">
					<thead><tr>
						<th><?=\T::Server_Edit_ListenersProtocol();?></th>
						<th><?=\T::Server_Edit_ListenersIP();?></th>
						<th><?=\T::Server_Edit_ListenersPort();?></th>
						<th><?=\T::Server_Edit_ListenersService();?></th>
						<th><?=\T::Server_Edit_ListenersProcess();?></th>
						<th><?=\T::Server_Edit_ListenersPID();?></th>
					</tr></thead>
					<tbody>
					<?php foreach ($normalized as $l) { ?>
						<tr>
							<td><?=htmlspecialchars(strtoupper($l['protocol']));?></td>
							<td><code><?=htmlspecialchars($l['ip']);?></code></td>
							<td data-order="<?=intval($l['port']);?>"><?=intval($l['port']);?></td>
							<td><?=htmlspecialchars($l['service']);?></td>
							<td><?=htmlspecialchars($l['process']);?></td>
							<td data-order="<?=intval($l['pid']);?>"><?=$l['pid'] > 0 ? intval($l['pid']) : '';?></td>
						</tr>
					<?php } ?>
					</tbody>
				</table>
			</div>
		</div>
		<?php
	}

	private function print_charts() {
		if (!$this->metrics) {
			return;
		}
		$labels = [];
		$cpu = [];
		$mem = [];
		$disk = [];
		$netRx = [];
		$netTx = [];
		$uptimeMin = [];
		$tempMax = [];
		$tempSeries = [];
		$dockerCpuSeries = [];
		$dockerMemSeries = [];
		$firstTs = intval($this->metrics[0]['collected_at'] ?? 0);
		$lastTs = intval($this->metrics[count($this->metrics) - 1]['collected_at'] ?? 0);
		$crossMidnight = $firstTs > 0 && $lastTs > 0 && \UserTime::format(intval($firstTs), 'Y-m-d') !== \UserTime::format(intval($lastTs), 'Y-m-d');
		$labelFmt = $crossMidnight ? 'd.m H:i' : 'H:i';
		foreach ($this->metrics as $m) {
			$labels[] = \UserTime::format(intval($m['collected_at'] ?? 0), $labelFmt);
			$cpu[] = floatval($m['cpu_usage_pct'] ?? 0);
			$mem[] = floatval($m['mem_used_pct'] ?? 0);
			$disk[] = floatval($m['disk_used_pct_max'] ?? 0);
			$netRx[] = round(floatval($m['net_rx_bps'] ?? 0) * 8.0 / 1000000.0, 3);
			$netTx[] = round(floatval($m['net_tx_bps'] ?? 0) * 8.0 / 1000000.0, 3);
			$uptimeMin[] = round(floatval($m['uptime_sec'] ?? 0) / 60.0, 2);
			$tm = $m['temp_c_max'] ?? null;
			$tempMax[] = ($tm === null || $tm === '') ? null : floatval($tm);
			$details = [];
			if (!empty($m['details'])) {
				$decoded = @json_decode(strval($m['details']), true);
				if (is_array($decoded)) {
					$details = $decoded;
				}
			}
			$temps = is_array($details['temps'] ?? null) ? $details['temps'] : [];
			foreach ($temps as $sensor => $val) {
				$sensor = strval($sensor);
				if (!isset($tempSeries[$sensor])) {
					$tempSeries[$sensor] = array_fill(0, count($labels) - 1, null);
				}
				$tempSeries[$sensor][] = floatval($val);
			}
			foreach ($tempSeries as $sensor => $_) {
				if (!array_key_exists($sensor, $temps)) {
					$tempSeries[$sensor][] = null;
				}
			}
			$dCpu = is_array($details['docker_cpu'] ?? null) ? $details['docker_cpu'] : [];
			foreach ($dCpu as $name => $val) {
				$name = strval($name);
				if (!isset($dockerCpuSeries[$name])) {
					$dockerCpuSeries[$name] = array_fill(0, count($labels) - 1, 0);
				}
				$dockerCpuSeries[$name][] = floatval($val);
			}
			foreach ($dockerCpuSeries as $name => $_) {
				if (!array_key_exists($name, $dCpu)) {
					$dockerCpuSeries[$name][] = 0;
				}
			}
			$dMem = is_array($details['docker_mem'] ?? null) ? $details['docker_mem'] : [];
			foreach ($dMem as $name => $val) {
				$name = strval($name);
				if (!isset($dockerMemSeries[$name])) {
					$dockerMemSeries[$name] = array_fill(0, count($labels) - 1, 0);
				}
				$dockerMemSeries[$name][] = round(floatval($val) / (1024 * 1024), 2);
			}
			foreach ($dockerMemSeries as $name => $_) {
				if (!array_key_exists($name, $dMem)) {
					$dockerMemSeries[$name][] = 0;
				}
			}
		}
		$hasTemp = false;
		foreach ($tempMax as $v) {
			if ($v !== null) { $hasTemp = true; break; }
		}
		if (!$hasTemp && $tempSeries) {
			$hasTemp = true;
		}
		$hasUptime = false;
		foreach ($uptimeMin as $v) {
			if ($v > 0) { $hasUptime = true; break; }
		}
		?>
		<div class="card bg-transparent col-xl-10 p-4 mt-2 mb-3">
			<div class="card-header bg-transparent"><h4><?=\T::Server_Edit_ChartsTitle();?></h4></div>
			<div class="card-body">
				<div id="serverMetricsChart" class="ServerMetricsChart"></div>
				<div id="serverNetworkChart" class="ServerNetworkChart mt-4"></div>
				<?php if ($hasTemp) { ?>
					<h5 class="mt-4"><?=\T::Server_Edit_TempChartTitle();?></h5>
					<div id="serverTempChart" class="ServerTempChart"></div>
				<?php } ?>
				<?php if ($hasUptime) { ?>
					<h5 class="mt-4"><?=\T::Server_Edit_UptimeChartTitle();?></h5>
					<div id="serverUptimeChart" class="ServerUptimeChart"></div>
				<?php } ?>
				<?php if ($dockerCpuSeries) { ?>
					<div class="d-flex justify-content-between align-items-center mt-4">
						<h5 class="mb-0"><?=\T::Server_Edit_DockerCpuChartTitle();?></h5>
						<input type="search" class="form-control form-control-sm w-auto" id="dockerCpuFilter" placeholder="<?=\T::Server_Edit_SystemdFilter();?>">
					</div>
					<div id="serverDockerCpuChart" class="ServerDockerCpuChart mt-2"></div>
				<?php } ?>
				<?php if ($dockerMemSeries) { ?>
					<div class="d-flex justify-content-between align-items-center mt-4">
						<h5 class="mb-0"><?=\T::Server_Edit_DockerMemChartTitle();?></h5>
						<input type="search" class="form-control form-control-sm w-auto" id="dockerMemFilter" placeholder="<?=\T::Server_Edit_SystemdFilter();?>">
					</div>
					<div id="serverDockerMemChart" class="ServerDockerMemChart mt-2"></div>
				<?php } ?>
			</div>
		</div>
		<script nonce="<?=\CSP::nonceRandom();?>">
			(function(){
				const labels = <?=json_encode($labels, JSON_UNESCAPED_UNICODE);?>;
				const cpu = <?=json_encode($cpu);?>;
				const mem = <?=json_encode($mem);?>;
				const disk = <?=json_encode($disk);?>;
				const netRx = <?=json_encode($netRx);?>;
				const netTx = <?=json_encode($netTx);?>;
				const uptimeMin = <?=json_encode($uptimeMin);?>;
				const tempMax = <?=json_encode($tempMax);?>;
				const tempSeries = <?=json_encode($tempSeries, JSON_UNESCAPED_UNICODE);?>;
				const dockerCpuSeries = <?=json_encode($dockerCpuSeries, JSON_UNESCAPED_UNICODE);?>;
				const dockerMemSeries = <?=json_encode($dockerMemSeries, JSON_UNESCAPED_UNICODE);?>;

				function isDarkTheme() {
					const theme = (document.documentElement.getAttribute('data-bs-theme') || '').toLowerCase();
					if (theme === 'dark') return true;
					if (theme === 'light') return false;
					return window.matchMedia('(prefers-color-scheme: dark)').matches;
				}

				function baseOption(extra) {
					const isDark = isDarkTheme();
					return Object.assign({
						backgroundColor: 'rgba(0,0,0,0)',
						darkMode: isDark,
						tooltip: { trigger: 'axis' },
						grid: { left: '3%', right: '4%', bottom: '12%', top: '18%', containLabel: true },
						xAxis: { type: 'category', data: labels, boundaryGap: false },
					}, extra);
				}

				function stackedSeries(map, filterText) {
					const q = (filterText || '').toLowerCase();
					const names = Object.keys(map).filter(function(n){ return !q || n.toLowerCase().indexOf(q) >= 0; }).sort();
					return names.map(function(name){
						return {
							name: name,
							type: 'line',
							stack: 'total',
							areaStyle: {},
							showSymbol: false,
							data: map[name]
						};
					});
				}

				function initServerCharts() {
					if (typeof echarts === 'undefined') return;
					const theme = isDarkTheme() ? 'dark' : 'light';
					const charts = [];

					const el = document.getElementById('serverMetricsChart');
					if (el) {
						const chart = echarts.init(el, theme);
						chart.setOption(baseOption({
							legend: { data: ['CPU %', 'RAM %', 'Disk %'] },
							yAxis: { type: 'value', min: 0, max: 100, name: '%' },
							series: [
								{ name: 'CPU %', type: 'line', showSymbol: false, data: cpu },
								{ name: 'RAM %', type: 'line', showSymbol: false, data: mem },
								{ name: 'Disk %', type: 'line', showSymbol: false, data: disk }
							]
						}));
						charts.push(chart);
					}

					const netEl = document.getElementById('serverNetworkChart');
					if (netEl) {
						const netChart = echarts.init(netEl, theme);
						netChart.setOption(baseOption({
							legend: { data: ['Download Mbps', 'Upload Mbps'] },
							yAxis: { type: 'value', min: 0, name: 'Mbps' },
							series: [
								{ name: 'Download Mbps', type: 'line', showSymbol: false, data: netRx },
								{ name: 'Upload Mbps', type: 'line', showSymbol: false, data: netTx }
							]
						}));
						charts.push(netChart);
					}

					const tempEl = document.getElementById('serverTempChart');
					if (tempEl) {
						const tempChart = echarts.init(tempEl, theme);
						const sensorNames = Object.keys(tempSeries);
						let series;
						if (sensorNames.length) {
							series = sensorNames.map(function(name){
								return { name: name, type: 'line', showSymbol: false, data: tempSeries[name] };
							});
						} else {
							series = [{ name: 'Max °C', type: 'line', showSymbol: false, data: tempMax }];
						}
						tempChart.setOption(baseOption({
							legend: { type: 'scroll', data: series.map(function(s){ return s.name; }) },
							yAxis: { type: 'value', name: '°C' },
							series: series
						}));
						charts.push(tempChart);
					}

					const upEl = document.getElementById('serverUptimeChart');
					if (upEl) {
						const upChart = echarts.init(upEl, theme);
						upChart.setOption(baseOption({
							legend: { data: ['Uptime min'] },
							yAxis: { type: 'value', min: 0, name: 'min' },
							tooltip: {
								trigger: 'axis',
								valueFormatter: function(v) {
									const m = Number(v) || 0;
									const d = Math.floor(m / 1440);
									const h = Math.floor((m % 1440) / 60);
									const mm = Math.floor(m % 60);
									return d + 'd ' + h + 'h ' + mm + 'm';
								}
							},
							series: [{ name: 'Uptime min', type: 'line', showSymbol: false, data: uptimeMin }]
						}));
						charts.push(upChart);
					}

					function bindStacked(elId, filterId, map, yName) {
						const chartEl = document.getElementById(elId);
						if (!chartEl) return null;
						const chart = echarts.init(chartEl, theme);
						function render() {
							const filter = (document.getElementById(filterId) || {}).value || '';
							const series = stackedSeries(map, filter);
							chart.setOption(baseOption({
								legend: { type: 'scroll', data: series.map(function(s){ return s.name; }) },
								yAxis: { type: 'value', min: 0, name: yName },
								series: series
							}), true);
						}
						render();
						const filterEl = document.getElementById(filterId);
						if (filterEl) {
							filterEl.addEventListener('input', render);
						}
						charts.push(chart);
						return chart;
					}
					bindStacked('serverDockerCpuChart', 'dockerCpuFilter', dockerCpuSeries, '%');
					bindStacked('serverDockerMemChart', 'dockerMemFilter', dockerMemSeries, 'MB');

					const resize = function(){ charts.forEach(function(c){ c.resize(); }); };
					window.addEventListener('resize', resize);
					requestAnimationFrame(resize);
				}

				if (document.readyState === 'loading') {
					document.addEventListener('DOMContentLoaded', initServerCharts);
				} else {
					initServerCharts();
				}
			})();
		</script>
		<?php
	}

	private function print_docker() {
		$docker = is_array($this->payload['docker'] ?? null) ? $this->payload['docker'] : null;
		if (!$docker || empty($docker['available'])) {
			return;
		}
		$containers = is_array($docker['containers'] ?? null) ? $docker['containers'] : [];
		?>
		<div class="card bg-transparent col-xl-10 p-4 mt-2 mb-3">
			<div class="card-header bg-transparent"><h4><?=\T::Server_Edit_DockerTitle();?></h4></div>
			<div class="card-body table-responsive">
				<table class="table table-sm table-transparent" id="serverDockerTable">
					<thead><tr><th>Name</th><th>State</th><th>Status</th><th>CPU</th><th>Memory</th><th>Image</th><th>Ports</th><th>ID</th></tr></thead>
					<tbody>
					<?php foreach ($containers as $c) {
						if (!is_array($c)) continue;
						$state = strval($c['state'] ?? '');
						$class = $state === 'running' ? 'text-success' : 'text-secondary';
						$cpuPct = floatval($c['cpu_pct'] ?? 0);
						$memBytes = intval($c['mem_bytes'] ?? 0);
						$memLimit = intval($c['mem_limit_bytes'] ?? 0);
						$memPct = floatval($c['mem_pct'] ?? 0);
						$memText = $memBytes > 0
							? $this->formatBytes($memBytes).($memLimit > 0 ? ' / '.$this->formatBytes($memLimit) : '').($memPct > 0 ? ' ('.number_format($memPct, 1).'%)' : '')
							: '—';
						$cpuText = ($state === 'running') ? number_format($cpuPct, 2).'%' : '—';
						?>
						<tr>
							<td><?=htmlspecialchars(strval($c['name'] ?? ''));?></td>
							<td class="<?=$class;?>"><?=htmlspecialchars($state);?></td>
							<td><?=htmlspecialchars(strval($c['status'] ?? ''));?></td>
							<td data-order="<?=htmlspecialchars(sprintf('%.3f', $cpuPct), ENT_QUOTES, 'UTF-8');?>"><?=htmlspecialchars($cpuText);?></td>
							<td data-order="<?=$memBytes;?>"><?=htmlspecialchars($memText);?></td>
							<td><small><?=htmlspecialchars(strval($c['image'] ?? ''));?></small></td>
							<td><small><?=htmlspecialchars(strval($c['ports'] ?? ''));?></small></td>
							<td><code><?=htmlspecialchars(strval($c['id'] ?? ''));?></code></td>
						</tr>
					<?php } ?>
					</tbody>
				</table>
			</div>
		</div>
		<?php
	}

	private function print_systemd() {
		$systemd = is_array($this->payload['systemd'] ?? null) ? $this->payload['systemd'] : null;
		if (!$systemd || empty($systemd['available'])) {
			return;
		}
		$units = is_array($systemd['units'] ?? null) ? $systemd['units'] : [];
		if (!$units) {
			return;
		}
		usort($units, static function ($a, $b) {
			if (!is_array($a) || !is_array($b)) {
				return 0;
			}
			return strcmp(strval($a['name'] ?? ''), strval($b['name'] ?? ''));
		});
		?>
		<div class="card bg-transparent col-xl-10 p-4 mt-2 mb-3">
			<div class="card-header bg-transparent d-flex justify-content-between align-items-center flex-wrap gap-2">
				<h4 class="mb-0"><?=\T::Server_Edit_SystemdTitle();?> <small class="text-muted">(<?=count($units);?>)</small></h4>
				<input type="search" class="form-control form-control-sm w-auto" id="systemdFilter" placeholder="<?=\T::Server_Edit_SystemdFilter();?>">
			</div>
			<div class="card-body table-responsive">
				<table class="table table-sm table-transparent" id="serverSystemdTable">
					<thead><tr>
						<th>Name</th>
						<th>State</th>
						<th>Sub-state</th>
						<th>CPU</th>
						<th>Peak CPU</th>
						<th>Memory</th>
						<th>Peak Memory</th>
					</tr></thead>
					<tbody>
					<?php foreach ($units as $u) {
						if (!is_array($u)) continue;
						$name = strval($u['name'] ?? '');
						$active = strval($u['active_state'] ?? '');
						$sub = strval($u['sub_state'] ?? '');
						$class = strtolower($active) === 'active' ? 'text-success' : (strtolower($active) === 'failed' ? 'text-danger' : 'text-secondary');
						$cpu = floatval($u['cpu_pct'] ?? 0);
						$cpuPeak = floatval($u['cpu_peak_pct'] ?? 0);
						$mem = intval($u['mem_bytes'] ?? 0);
						$memPeak = intval($u['mem_peak_bytes'] ?? 0);
						?>
						<tr>
							<td><?=htmlspecialchars($name);?></td>
							<td class="<?=$class;?>"><?=htmlspecialchars($active);?></td>
							<td><?=htmlspecialchars($sub);?></td>
							<td data-order="<?=htmlspecialchars(sprintf('%.3f', $cpu), ENT_QUOTES, 'UTF-8');?>"><?=number_format($cpu, 2);?>%</td>
							<td data-order="<?=htmlspecialchars(sprintf('%.3f', $cpuPeak), ENT_QUOTES, 'UTF-8');?>"><?=number_format($cpuPeak, 2);?>%</td>
							<td data-order="<?=$mem;?>"><?=$mem > 0 ? htmlspecialchars($this->formatBytes($mem)) : '—';?></td>
							<td data-order="<?=$memPeak;?>"><?=$memPeak > 0 ? htmlspecialchars($this->formatBytes($memPeak)) : '—';?></td>
						</tr>
					<?php } ?>
					</tbody>
				</table>
			</div>
		</div>
		<?php
	}

	private function print_proxmox() {
		$proxmox = is_array($this->payload['proxmox'] ?? null) ? $this->payload['proxmox'] : null;
		if (!$proxmox || empty($proxmox['available'])) {
			return;
		}
		$guests = is_array($proxmox['guests'] ?? null) ? $proxmox['guests'] : [];
		$node = strval($proxmox['node'] ?? '');
		$title = \T::Server_Edit_ProxmoxTitle();
		if ($node !== '') {
			$title .= ' <small class="text-muted">('.htmlspecialchars($node, ENT_QUOTES, 'UTF-8').')</small>';
		}
		?>
		<div class="card bg-transparent col-xl-10 p-4 mt-2 mb-3">
			<div class="card-header bg-transparent"><h4><?=$title;?></h4></div>
			<div class="card-body table-responsive">
				<table class="table table-sm table-transparent" id="serverProxmoxTable">
					<thead><tr><th>Type</th><th>VMID</th><th>Name</th><th>Status</th><th>CPU</th><th>RAM</th><th>Disk</th><th>Uptime</th></tr></thead>
					<tbody>
					<?php foreach ($guests as $g) {
						if (!is_array($g)) continue;
						$status = strval($g['status'] ?? '');
						$class = $status === 'running' ? 'text-success' : 'text-secondary';
						$type = strval($g['type'] ?? '');
						$typeLabel = $type === 'lxc' ? 'LXC' : ($type === 'qemu' ? 'VM' : strtoupper($type));
						$cpus = intval($g['cpus'] ?? 0);
						$cpuFrac = floatval($g['cpu'] ?? 0);
						$cpuPct = $cpuFrac * 100.0;
						$cpuText = number_format($cpuPct, 1).'%';
						if ($cpus > 0) {
							$cpuText .= ' / '.$cpus;
						}
						$memUsed = intval($g['mem_bytes'] ?? 0);
						$memMax = intval($g['maxmem_bytes'] ?? 0);
						$diskUsed = intval($g['disk_bytes'] ?? 0);
						$diskMax = intval($g['maxdisk_bytes'] ?? 0);
						$uptimeSec = intval($g['uptime_sec'] ?? 0);
						?>
						<tr>
							<td><?=htmlspecialchars($typeLabel);?></td>
							<td data-order="<?=intval($g['vmid'] ?? 0);?>"><?=intval($g['vmid'] ?? 0);?></td>
							<td><?=htmlspecialchars(strval($g['name'] ?? ''));?></td>
							<td class="<?=$class;?>"><?=htmlspecialchars($status);?></td>
							<td data-order="<?=htmlspecialchars(sprintf('%.3f', $cpuPct), ENT_QUOTES, 'UTF-8');?>"><?=htmlspecialchars($cpuText);?></td>
							<td data-order="<?=$memUsed;?>"><?=htmlspecialchars($this->formatBytesPair($memUsed, $memMax));?></td>
							<td data-order="<?=$diskUsed;?>"><?=htmlspecialchars($this->formatBytesPair($diskUsed, $diskMax));?></td>
							<td data-order="<?=$uptimeSec;?>"><?=htmlspecialchars($this->formatUptime($uptimeSec));?></td>
						</tr>
					<?php } ?>
					</tbody>
				</table>
			</div>
		</div>
		<?php
	}

	private function formatBytesPair(int $used, int $max): string {
		if ($max <= 0 && $used <= 0) {
			return '—';
		}
		if ($max > 0) {
			return $this->formatBytes($used).' / '.$this->formatBytes($max);
		}
		return $this->formatBytes($used);
	}

	private function formatBytes(int $bytes): string {
		if ($bytes <= 0) {
			return '0';
		}
		$units = ['B', 'KB', 'MB', 'GB', 'TB'];
		$i = 0;
		$val = floatval($bytes);
		while ($val >= 1024 && $i < count($units) - 1) {
			$val /= 1024;
			$i++;
		}
		$precision = $i >= 2 ? 1 : 0;
		return number_format($val, $precision).' '.$units[$i];
	}

	private function formatUptime(int $sec): string {
		if ($sec <= 0) {
			return '—';
		}
		$days = intdiv($sec, 86400);
		$hours = intdiv($sec % 86400, 3600);
		$mins = intdiv($sec % 3600, 60);
		if ($days > 0) {
			return $days.'d '.$hours.'h';
		}
		if ($hours > 0) {
			return $hours.'h '.$mins.'m';
		}
		return $mins.'m';
	}

	private function print_processes() {
		$procs = is_array($this->payload['processes'] ?? null) ? $this->payload['processes'] : [];
		if (!$procs) {
			return;
		}
		?>
		<div class="card bg-transparent col-xl-10 p-4 mt-2 mb-3">
			<div class="card-header bg-transparent"><h4><?=\T::Server_Edit_ProcessesTitle();?></h4></div>
			<div class="card-body table-responsive">
				<table class="table table-sm table-transparent" id="serverProcessesTable">
					<thead><tr><th>PID</th><th>Name</th><th>User</th><th>CPU %</th><th>RSS</th><th>Cmd</th></tr></thead>
					<tbody>
					<?php foreach ($procs as $p) {
						if (!is_array($p)) continue;
						$cpuPct = floatval($p['cpu_pct'] ?? 0);
						$rss = intval($p['mem_rss'] ?? 0);
						?>
						<tr>
							<td data-order="<?=intval($p['pid'] ?? 0);?>"><?=intval($p['pid'] ?? 0);?></td>
							<td><?=htmlspecialchars(strval($p['name'] ?? ''));?></td>
							<td><?=htmlspecialchars(strval($p['user'] ?? ''));?></td>
							<td data-order="<?=htmlspecialchars(sprintf('%.3f', $cpuPct), ENT_QUOTES, 'UTF-8');?>"><?=number_format($cpuPct, 1);?></td>
							<td data-order="<?=$rss;?>"><?=number_format($rss / 1024 / 1024, 1);?> MB</td>
							<td><small><?=htmlspecialchars(strval($p['cmdline'] ?? ''));?></small></td>
						</tr>
					<?php } ?>
					</tbody>
				</table>
			</div>
		</div>
		<?php
	}

	private function print_auth() {
		if (!$this->auth_events) {
			return;
		}
		?>
		<div class="card bg-transparent col-xl-10 p-4 mt-2 mb-3">
			<div class="card-header bg-transparent"><h4><?=\T::Server_Edit_AuthTitle();?></h4></div>
			<div class="card-body table-responsive">
				<table class="table table-sm table-transparent" id="serverAuthTable">
					<thead><tr><th>Time</th><th>User</th><th>From</th><th>Method</th><th>OK</th><th>Raw</th></tr></thead>
					<tbody>
					<?php foreach ($this->auth_events as $e) {
						$eventTime = intval($e['event_time'] ?? 0);
						$success = !empty($e['success']);
						?>
						<tr>
							<td data-order="<?=$eventTime;?>"><?=$eventTime > 0 ? \UserTime::format(intval($eventTime)) : '';?></td>
							<td><?=htmlspecialchars(strval($e['user'] ?? ''));?></td>
							<td><?=htmlspecialchars(strval($e['from_host'] ?? ''));?></td>
							<td><?=htmlspecialchars(strval($e['method'] ?? ''));?></td>
							<td data-order="<?=$success ? '1' : '0';?>"><?=$success ? '<span class="text-success">yes</span>' : '<span class="text-danger">no</span>';?></td>
							<td><small><?=htmlspecialchars(strval($e['raw'] ?? ''));?></small></td>
						</tr>
					<?php } ?>
					</tbody>
				</table>
			</div>
		</div>
		<?php
	}

	private function print_javascript() {
		?>
		<script nonce="<?=\CSP::nonceRandom();?>">
			$(document).ready(function(){
				const tableIds = [
					'#serverDisksTable',
					'#serverNetworkTable',
					'#serverListenersTable',
					'#serverDockerTable',
					'#serverSystemdTable',
					'#serverProxmoxTable',
					'#serverProcessesTable',
					'#serverAuthTable',
				];
				tableIds.forEach(function(sel) {
					const $table = $(sel);
					if (!$table.length || !$table.find('tbody tr').length) {
						return;
					}
					const opts = {};
					if (sel === '#serverSystemdTable') {
						// Keep the custom header filter; hide DataTables' own search box.
						opts.layout = {
							topStart: 'pageLength',
							topEnd: null,
							bottomStart: 'info',
							bottomEnd: 'paging',
						};
					}
					const dt = new DataTable(sel, opts);
					if (sel === '#serverSystemdTable') {
						$('#systemdFilter').on('input', function() {
							dt.search(String(this.value || '')).draw();
						});
					}
				});

				$('body').on('click', '.copy-agent-install', function() {
					const text = ($('#agent-install-script').text() || '').toString();
					if (!text) return;
					const $btn = $(this);
					const done = function() {
						const $icon = $btn.find('i');
						$icon.removeClass('bi-clipboard').addClass('bi-clipboard-check');
						setTimeout(function() {
							$icon.removeClass('bi-clipboard-check').addClass('bi-clipboard');
						}, 1500);
					};
					if (navigator.clipboard && navigator.clipboard.writeText) {
						navigator.clipboard.writeText(text).then(done).catch(function() {
							done();
						});
					} else {
						done();
					}
				});
			});
		</script>
		<?php
	}
}
