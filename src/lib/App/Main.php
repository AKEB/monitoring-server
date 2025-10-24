<?php

namespace App;

class Main extends \Routing_Parent implements \Routing_Interface {

	public function Run() {
		$this->check_auth();
		$template = new \Template();
		echo '<h2>'.\T::MainPage().'</h2>';

		$monitors = \Monitors::data(false,' ORDER BY title ASC ');
		if ($monitors) {
			$monitors = make_hash($monitors, 'id');
		}
		foreach($monitors as $monitor_id=>$monitor) {
			if ($monitor['type'] != \Monitors::TYPE_FOLDER) {
				if (!\Sessions::checkPermission(\Monitors::PERMISSION_MONITOR, $monitor['id'], READ)) {
					unset($monitors[$monitor_id]);
				}
			}
			if (!isset($monitor['children'])) $monitor['children'] = [];
			if ($monitor['parent_id']) {
				if (!isset($monitors[$monitor['parent_id']]['children'])) {
					$monitors[$monitor['parent_id']]['children'] = [];
				}
				$monitors[$monitor['parent_id']]['children'][$monitor_id] = &$monitors[$monitor_id];
			}
		}
		foreach($monitors as $monitor_id=>$monitor) {
			if ($monitor['parent_id']) {
				unset($monitors[$monitor_id]);
			}
		}



		?>
		<div class="row">
			<div class="col-12 col-md-6 col-xl-6 col-xxl-5 monitor-list">
				<div class="row monitor-list-filter">
					<div class="col">

					</div>
				</div>
				<div class="row monitor-list-items">
					<div class="col">
						<?php
						$this->print_monitors($monitors, 0);
						?>
					</div>
				</div>
			</div>
			<div class="col">

			</div>
		</div>
		<script nonce="<?=\CSP::nonceRandom();?>">
			$(document).ready(function() {
				MonitorsUpdate(wss);
			});
		</script>
		<?php
	}


	private function print_monitors(array $monitors=[], int $level=0) {
		foreach($monitors as $item) {
			if ($item['type'] == \Monitors::TYPE_FOLDER) {
				// continue;
				if (!isset($item['children']) || !$item['children']) {
					continue;
				}
				?>
				<div class="row monitor-item" data-monitor-id="<?=$item['id'];?>">
					<div class="col-9 col-md-8 collapsed monitor-title" data-monitor-id="<?=$item['id'];?>" data-bs-toggle="collapse" href="#collapseMonitors-<?=$item['id'];?>" role="button" aria-expanded="false" aria-controls="collapseMonitors-<?=$item['id'];?>">
						<span class="ml-padding-<?=$level;?>"></span><span class="badge rounded-pill monitor-badge" title="24-часа" data-monitor-id="<?=$item['id'];?>">100%</span>
						<i class="bi bi-caret-down"></i>
						<?=$item['title'];?>
					</div>
					<div class="col-3 col-md-4">
						<div class="wrap">
							<div class="monitor-states" data-monitor-id="<?=$item['id'];?>"></div>
						</div>
					</div>
				</div>
				<div class="collapse" id="collapseMonitors-<?=$item['id'];?>">
					<?php
					$this->print_monitors($item['children'], $level+1);
					?>
				</div>
				<?php
			} else {
				?>
				<div class="row monitor-item" data-monitor-id="<?=$item['id'];?>">
					<div class="col-9 col-md-8 monitor-title" data-monitor-id="<?=$item['id'];?>">
						<span class="ml-padding-<?=$level;?>"></span><span class="badge rounded-pill monitor-badge" title="24-часа" data-monitor-id="<?=$item['id'];?>">100%</span>
						<?=$item['title'];?>
					</div>
					<div class="col-3 col-md-4">
						<div class="wrap">
							<div class="monitor-states" data-monitor-id="<?=$item['id'];?>"></div>
						</div>
					</div>
				</div>
				<?php
			}
		}
	}

}
