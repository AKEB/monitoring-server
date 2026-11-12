<?php

namespace App\Events;

class Show extends \Routing_Parent implements \Routing_Interface {

	private int $from = 0;
	private int $to = 0;
	private int $monitor_id = 0;
	private int $worker_id = 0;
	private ?int $status = null;
	private string $q = '';
	private int $page = 1;
	private int $per_page = 50;

	/** @var list<array<string,mixed>> */
	private array $events = [];
	private int $total = 0;

	/** @var array<int,array<string,mixed>> */
	private array $monitors = [];
	/** @var array<int,array<string,mixed>> */
	private array $workers = [];

	public function Run() {
		$this->check_auth();
		$this->parse_filters();
		$this->load_lookups();
		$this->get_data();

		$this->template = new \Template(\T::Events_PageTitle());
		$this->print_header();
		$this->print_filters();
		$this->print_table();
		$this->print_pagination();
		$this->print_javascript();
	}

	private function parse_filters() {
		$from_raw = trim(strval($_GET['from'] ?? ''));
		$to_raw = trim(strval($_GET['to'] ?? ''));

		if ($from_raw !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from_raw)) {
			$parsed = \UserTime::parseDateStart($from_raw);
			if ($parsed !== null) {
				$this->from = $parsed;
			}
		}
		if ($to_raw !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to_raw)) {
			$parsed = \UserTime::parseDateEnd($to_raw);
			if ($parsed !== null) {
				$this->to = $parsed;
			}
		}

		if (!$this->from || !$this->to) {
			$this->to = \UserTime::endOfDay(time());
			$this->from = \UserTime::startOfDay($this->to - 7 * 86400 + 1);
		}
		if ($this->from > $this->to) {
			$tmp = $this->from;
			$this->from = $this->to;
			$this->to = $tmp;
		}

		$this->monitor_id = intval($_GET['monitor_id'] ?? 0);
		$this->worker_id = intval($_GET['worker_id'] ?? 0);
		if (isset($_GET['status']) && $_GET['status'] !== '') {
			$this->status = intval($_GET['status']);
		}
		$this->q = trim(strval($_GET['q'] ?? ''));
		$this->page = max(1, intval($_GET['page'] ?? 1));
		$this->per_page = intval($_GET['per_page'] ?? 50);
		if (!in_array($this->per_page, [25, 50, 100], true)) {
			$this->per_page = 50;
		}
	}

	private function load_lookups() {
		$monitors = \Monitors::get_hash();
		foreach ($monitors as $id => $monitor) {
			if (intval($monitor['type'] ?? -1) === \Monitors::TYPE_FOLDER) continue;
			if (!\Sessions::checkPermission(\Monitors::PERMISSION_ACCESS, intval($id), READ)) continue;
			$this->monitors[intval($id)] = $monitor;
		}
		uasort($this->monitors, static function ($a, $b) {
			return strcasecmp(strval($a['title'] ?? ''), strval($b['title'] ?? ''));
		});

		$workers = \Workers::data(false, ' ORDER BY `title` ASC', '`id`, `title`');
		$this->workers = make_hash($workers ?: [], 'id');
		// Virtual worker ids are never Workers rows (External push / server agent).
		$this->workers[\Monitors::EXTERNAL_WORKER_ID] = [
			'id' => \Monitors::EXTERNAL_WORKER_ID,
			'title' => \ExternalMonitors::workerTitle(),
		];
		$this->workers[\Servers::SERVER_WORKER_ID] = [
			'id' => \Servers::SERVER_WORKER_ID,
			'title' => \Servers::workerTitle(),
		];
	}

	/** Resolve display title for real or virtual worker_id. */
	private function workerTitleForEvent(int $worker_id, int $monitor_id): string {
		if ($worker_id === \Monitors::EXTERNAL_WORKER_ID) {
			return \ExternalMonitors::workerTitle();
		}
		if ($worker_id === \Servers::SERVER_WORKER_ID) {
			return \Servers::workerTitleForMonitor($this->monitors[$monitor_id] ?? []);
		}
		return strval($this->workers[$worker_id]['title'] ?? 'Unknown worker');
	}

	private function get_data() {
		$result = \MonitorsEvents::getEventsFiltered([
			'from' => $this->from,
			'to' => $this->to,
			'monitor_id' => $this->monitor_id,
			'worker_id' => $this->worker_id,
			'status' => $this->status,
			'q' => $this->q,
			'page' => $this->page,
			'per_page' => $this->per_page,
		]);
		$this->events = $result['items'];
		$this->total = intval($result['total']);
		$this->page = intval($result['page']);
		$this->per_page = intval($result['per_page']);
	}

	private function filter_query(array $overrides = []): string {
		$params = [
			'from' => \UserTime::format($this->from, 'Y-m-d'),
			'to' => \UserTime::format($this->to, 'Y-m-d'),
			'monitor_id' => $this->monitor_id,
			'worker_id' => $this->worker_id,
			'status' => $this->status === null ? '' : $this->status,
			'q' => $this->q,
			'page' => $this->page,
			'per_page' => $this->per_page,
		];
		foreach ($overrides as $k => $v) {
			$params[$k] = $v;
		}
		$parts = [];
		foreach ($params as $k => $v) {
			if ($v === '' || $v === null) continue;
			if (($k === 'monitor_id' || $k === 'worker_id') && intval($v) <= 0) continue;
			if ($k === 'page' && intval($v) <= 1) continue;
			$parts[] = rawurlencode(strval($k)).'='.rawurlencode(strval($v));
		}
		return $parts ? ('?'.implode('&', $parts)) : '';
	}

	private function print_header() {
		?>
		<div class="float-start"><h2><i class="bi bi-journal-text"></i> <?=\T::Events_PageTitle();?></h2></div>
		<div class="clearfix"></div>
		<?php
	}

	private function print_filters() {
		$from = htmlspecialchars(\UserTime::format($this->from, 'Y-m-d'));
		$to = htmlspecialchars(\UserTime::format($this->to, 'Y-m-d'));
		$q = htmlspecialchars($this->q);
		?>
		<form method="get" action="/events/" class="card mb-3">
			<div class="card-body">
				<div class="row g-2 align-items-end">
					<div class="col-md-2">
						<label class="form-label"><?=\T::Events_Filter_From();?></label>
						<input type="date" class="form-control" name="from" value="<?=$from;?>">
					</div>
					<div class="col-md-2">
						<label class="form-label"><?=\T::Events_Filter_To();?></label>
						<input type="date" class="form-control" name="to" value="<?=$to;?>">
					</div>
					<div class="col-md-2">
						<?php
						echo $this->template?->html_select(
							'monitor_id',
							\Monitors::get_folder_hash_options_hash(false),
							$this->monitor_id,
							\T::Events_Filter_Monitor(),
							false,
							[
								'with-undefined' => true,
								'undefined-value' => '0',
								'undefined-title' => \T::Events_Filter_All(),
								'data-container' => 'body',
								'vertical' => true,
								'class1' => 'form-label',
								'class2' => '',
							]
						);
						?>
					</div>
					<div class="col-md-2">
						<label class="form-label"><?=\T::Events_Filter_Worker();?></label>
						<select class="form-select" name="worker_id">
							<option value="0"><?=\T::Events_Filter_All();?></option>
							<?php foreach ($this->workers as $id => $worker) {
								$selected = $this->worker_id === intval($id) ? ' selected' : '';
								$title = htmlspecialchars(strval($worker['title'] ?? ''));
								?>
								<option value="<?=intval($id);?>"<?=$selected;?>><?=$title;?></option>
							<?php } ?>
						</select>
					</div>
					<div class="col-md-2">
						<label class="form-label"><?=\T::Events_Filter_Status();?></label>
						<select class="form-select" name="status">
							<option value=""><?=\T::Events_Filter_All();?></option>
							<option value="<?= \MonitoringLogs::STATUS_GOOD; ?>"<?= $this->status === \MonitoringLogs::STATUS_GOOD ? ' selected' : ''; ?>><?=\T::Status_Success();?></option>
							<option value="<?= \MonitoringLogs::STATUS_FAIL; ?>"<?= $this->status === \MonitoringLogs::STATUS_FAIL ? ' selected' : ''; ?>><?=\T::Status_Error();?></option>
						</select>
					</div>
					<div class="col-md-2">
						<label class="form-label"><?=\T::Events_Filter_PerPage();?></label>
						<select class="form-select" name="per_page">
							<?php foreach ([25, 50, 100] as $n) {
								$selected = $this->per_page === $n ? ' selected' : '';
								?>
								<option value="<?=$n;?>"<?=$selected;?>><?=$n;?></option>
							<?php } ?>
						</select>
					</div>
					<div class="col-md-8">
						<label class="form-label"><?=\T::Events_Filter_Error();?></label>
						<input type="text" class="form-control" name="q" value="<?=$q;?>" placeholder="<?=\T::Events_Filter_ErrorPlaceholder();?>">
					</div>
					<div class="col-md-4 text-end">
						<button type="submit" class="btn btn-primary"><?=\T::Events_Filter_Apply();?></button>
					</div>
				</div>
			</div>
		</form>
		<?php
	}

	private function print_table() {
		?>
		<div class="row d-flex justify-content-center">
			<table class="table table-transparent table-responsive" id="Table">
				<thead>
					<tr>
						<th scope="col" class="align-middle"><?=\T::Events_Table_Status();?></th>
						<th scope="col" class="align-middle"><?=\T::Events_Table_Monitor();?></th>
						<th scope="col" class="align-middle"><?=\T::Events_Table_Worker();?></th>
						<th scope="col" class="align-middle"><?=\T::Events_Table_Time();?></th>
						<th scope="col" class="align-middle"><?=\T::Events_Table_StatusCode();?></th>
						<th scope="col" class="align-middle"><?=\T::Events_Table_ResponseError();?></th>
					</tr>
				</thead>
				<tbody>
					<?php
					if (!$this->events) {
						?>
						<tr>
							<td colspan="6" class="text-center text-secondary"><?=\T::Events_Empty();?></td>
						</tr>
						<?php
					}
					foreach ($this->events as $event) {
						$icon = intval($event['status']) === \MonitoringLogs::STATUS_GOOD
							? '<i class="bi bi-check-square text-success"></i>'
							: '<i class="bi bi-exclamation-square text-danger"></i>';
						$monitor_id = intval($event['monitor_id']);
						$worker_id = intval($event['worker_id']);
						$monitor_title = htmlspecialchars(
							\Monitors::get_monitor_title_with_folders($monitor_id)
							?: strval($this->monitors[$monitor_id]['title'] ?? '')
						);
						$worker_title = htmlspecialchars($this->workerTitleForEvent($worker_id, $monitor_id));
						$error = htmlspecialchars(strval($event['response_error'] ?? ''));
						?>
						<tr>
							<td class="align-middle"><?=$icon;?></td>
							<td class="align-middle"><?=$monitor_title;?></td>
							<td class="align-middle"><?=$worker_title;?></td>
							<td class="align-middle"><?=htmlspecialchars(strval($event['event_time']));?></td>
							<td class="align-middle"><?=intval($event['status_code']);?></td>
							<td class="align-middle"><?=$error;?></td>
						</tr>
						<?php
					}
					?>
				</tbody>
			</table>
		</div>
		<?php
	}

	private function print_pagination() {
		$pages = $this->per_page > 0 ? intval(ceil($this->total / $this->per_page)) : 1;
		if ($pages < 1) $pages = 1;
		?>
		<div class="d-flex justify-content-between align-items-center mb-3">
			<div class="text-secondary">
				<?=\T::Events_Pagination_Total($this->total);?>
			</div>
			<?php if ($pages > 1) { ?>
			<nav>
				<ul class="pagination mb-0">
					<li class="page-item<?= $this->page <= 1 ? ' disabled' : ''; ?>">
						<a class="page-link" href="/events/<?=htmlspecialchars($this->filter_query(['page' => max(1, $this->page - 1)]));?>">&laquo;</a>
					</li>
					<?php
					$start = max(1, $this->page - 2);
					$end = min($pages, $this->page + 2);
					for ($p = $start; $p <= $end; $p++) {
						$active = $p === $this->page ? ' active' : '';
						?>
						<li class="page-item<?=$active;?>">
							<a class="page-link" href="/events/<?=htmlspecialchars($this->filter_query(['page' => $p]));?>"><?=$p;?></a>
						</li>
						<?php
					}
					?>
					<li class="page-item<?= $this->page >= $pages ? ' disabled' : ''; ?>">
						<a class="page-link" href="/events/<?=htmlspecialchars($this->filter_query(['page' => min($pages, $this->page + 1)]));?>">&raquo;</a>
					</li>
				</ul>
			</nav>
			<?php } ?>
		</div>
		<?php
	}

	private function print_javascript() {
		?>
		<script nonce="<?=\CSP::nonceRandom();?>">
			$(document).ready(function(){
				if ($('#Table tbody tr').length && !$('#Table tbody tr td[colspan]').length) {
					new DataTable('#Table', {
						paging: false,
						searching: false,
						ordering: false,
						info: false,
					});
				}
			});
		</script>
		<?php
	}
}
