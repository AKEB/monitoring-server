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
		$this->save_server($data);
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
			$this->server['create_date'] = !empty($this->server['create_time']) ? date('Y-m-d H:i:s', intval($this->server['create_time'])) : '';
			$this->server['update_date'] = !empty($this->server['update_time']) ? date('Y-m-d H:i:s', intval($this->server['update_time'])) : '';
			$this->server['last_seen_date'] = !empty($this->server['last_seen_time']) ? date('Y-m-d H:i:s', intval($this->server['last_seen_time'])) : '';
			$this->server['online'] = \Servers::isOnline($this->server);
			if (!empty($this->server['last_payload'])) {
				$decoded = @json_decode(strval($this->server['last_payload']), true);
				$this->payload = is_array($decoded) ? $decoded : null;
			}
			$since = time() - 24 * 3600;
			$this->metrics = array_values(\ServerMetrics::data(
				false,
				sql_pholder(' AND `server_id` = ? AND `collected_at` >= ? ORDER BY `collected_at` ASC LIMIT 1500', $this->server['id'], $since)
			) ?: []);
			$this->auth_events = array_values(\ServerAuthEvents::data(
				false,
				sql_pholder(' AND `server_id` = ? ORDER BY `event_time` DESC LIMIT 100', $this->server['id'])
			) ?: []);
		} else {
			$this->server['status'] ??= 1;
			$this->server['online'] = false;
		}
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
						echo '<p class="mb-2 text-muted">'.\T::Server_Edit_PermissionsHint().'</p>';
					}
					?>
					<div class="d-flex flex-row-reverse">
						<button type="submit" class="btn btn-primary me-2" name="<?=!empty($this->server['id']) ? 'editServer' : 'createServer';?>" value="true"><?=(!empty($this->server['id']) ? \T::Server_Edit_ChangeButton() : \T::Server_Edit_CreateButton());?></button>
					</div>
				</form>
			</div>

			<?php if (!empty($this->server['id'])) {
				$this->print_status_cards();
				$this->print_charts();
				$this->print_docker();
				$this->print_processes();
				$this->print_auth();
			} ?>
		</div>
		<?php
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
				</div>
				<?php if ($disks) { ?>
					<h5 class="mt-3"><?=\T::Server_Edit_Disks();?></h5>
					<table class="table table-sm table-transparent"><thead><tr><th>Mount</th><th>Device</th><th>FS</th><th>Used %</th></tr></thead><tbody>
					<?php foreach ($disks as $d) {
						if (!is_array($d)) continue;
						?>
						<tr>
							<td><?=htmlspecialchars(strval($d['mount'] ?? ''));?></td>
							<td><?=htmlspecialchars(strval($d['device'] ?? ''));?></td>
							<td><?=htmlspecialchars(strval($d['fstype'] ?? ''));?></td>
							<td><?=number_format(floatval($d['used_pct'] ?? 0), 1);?>%</td>
						</tr>
					<?php } ?>
					</tbody></table>
				<?php } ?>
				<?php if ($network) { ?>
					<h5 class="mt-3"><?=\T::Server_Edit_Network();?></h5>
					<table class="table table-sm table-transparent"><thead><tr><th>Iface</th><th>IPs</th><th>Rx B/s</th><th>Tx B/s</th></tr></thead><tbody>
					<?php foreach ($network as $n) {
						if (!is_array($n)) continue;
						$addrs = is_array($n['addrs'] ?? null) ? $n['addrs'] : [];
						?>
						<tr>
							<td><?=htmlspecialchars(strval($n['name'] ?? ''));?></td>
							<td><small><?=htmlspecialchars(implode(', ', $addrs));?></small></td>
							<td><?=number_format(floatval($n['rx_bps'] ?? 0), 0);?></td>
							<td><?=number_format(floatval($n['tx_bps'] ?? 0), 0);?></td>
						</tr>
					<?php } ?>
					</tbody></table>
				<?php } ?>
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
		foreach ($this->metrics as $m) {
			$labels[] = date('H:i', intval($m['collected_at'] ?? 0));
			$cpu[] = floatval($m['cpu_usage_pct'] ?? 0);
			$mem[] = floatval($m['mem_used_pct'] ?? 0);
			$disk[] = floatval($m['disk_used_pct_max'] ?? 0);
		}
		?>
		<div class="card bg-transparent col-xl-10 p-4 mt-2 mb-3">
			<div class="card-header bg-transparent"><h4><?=\T::Server_Edit_ChartsTitle();?></h4></div>
			<div class="card-body">
				<div id="serverMetricsChart" style="width:100%;height:320px;"></div>
			</div>
		</div>
		<script nonce="<?=\CSP::nonceRandom();?>">
			(function(){
				const el = document.getElementById('serverMetricsChart');
				if (!el || typeof echarts === 'undefined') return;
				const chart = echarts.init(el);
				chart.setOption({
					tooltip: { trigger: 'axis' },
					legend: { data: ['CPU %', 'RAM %', 'Disk %'] },
					xAxis: { type: 'category', data: <?=json_encode($labels);?> },
					yAxis: { type: 'value', min: 0, max: 100 },
					series: [
						{ name: 'CPU %', type: 'line', showSymbol: false, data: <?=json_encode($cpu);?> },
						{ name: 'RAM %', type: 'line', showSymbol: false, data: <?=json_encode($mem);?> },
						{ name: 'Disk %', type: 'line', showSymbol: false, data: <?=json_encode($disk);?> }
					]
				});
				window.addEventListener('resize', function(){ chart.resize(); });
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
				<table class="table table-sm table-transparent">
					<thead><tr><th>Name</th><th>State</th><th>Status</th><th>Image</th><th>Ports</th><th>ID</th></tr></thead>
					<tbody>
					<?php foreach ($containers as $c) {
						if (!is_array($c)) continue;
						$state = strval($c['state'] ?? '');
						$class = $state === 'running' ? 'text-success' : 'text-secondary';
						?>
						<tr>
							<td><?=htmlspecialchars(strval($c['name'] ?? ''));?></td>
							<td class="<?=$class;?>"><?=htmlspecialchars($state);?></td>
							<td><?=htmlspecialchars(strval($c['status'] ?? ''));?></td>
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

	private function print_processes() {
		$procs = is_array($this->payload['processes'] ?? null) ? $this->payload['processes'] : [];
		if (!$procs) {
			return;
		}
		?>
		<div class="card bg-transparent col-xl-10 p-4 mt-2 mb-3">
			<div class="card-header bg-transparent"><h4><?=\T::Server_Edit_ProcessesTitle();?></h4></div>
			<div class="card-body table-responsive">
				<table class="table table-sm table-transparent">
					<thead><tr><th>PID</th><th>Name</th><th>User</th><th>CPU %</th><th>RSS</th><th>Cmd</th></tr></thead>
					<tbody>
					<?php foreach ($procs as $p) {
						if (!is_array($p)) continue;
						?>
						<tr>
							<td><?=intval($p['pid'] ?? 0);?></td>
							<td><?=htmlspecialchars(strval($p['name'] ?? ''));?></td>
							<td><?=htmlspecialchars(strval($p['user'] ?? ''));?></td>
							<td><?=number_format(floatval($p['cpu_pct'] ?? 0), 1);?></td>
							<td><?=number_format(intval($p['mem_rss'] ?? 0) / 1024 / 1024, 1);?> MB</td>
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
				<table class="table table-sm table-transparent">
					<thead><tr><th>Time</th><th>User</th><th>From</th><th>Method</th><th>OK</th><th>Raw</th></tr></thead>
					<tbody>
					<?php foreach ($this->auth_events as $e) { ?>
						<tr>
							<td><?=!empty($e['event_time']) ? date('Y-m-d H:i:s', intval($e['event_time'])) : '';?></td>
							<td><?=htmlspecialchars(strval($e['user'] ?? ''));?></td>
							<td><?=htmlspecialchars(strval($e['from_host'] ?? ''));?></td>
							<td><?=htmlspecialchars(strval($e['method'] ?? ''));?></td>
							<td><?=!empty($e['success']) ? '<span class="text-success">yes</span>' : '<span class="text-danger">no</span>';?></td>
							<td><small><?=htmlspecialchars(strval($e['raw'] ?? ''));?></small></td>
						</tr>
					<?php } ?>
					</tbody>
				</table>
			</div>
		</div>
		<?php
	}

	private function print_javascript() {}
}
