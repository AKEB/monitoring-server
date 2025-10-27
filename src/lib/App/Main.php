<?php

namespace App;

class Main extends \Routing_Parent implements \Routing_Interface {

	public function Run() {
		$this->check_auth();
		$template = new \Template();
		echo '<h2>'.\T::MainPage().'</h2>';
		?>
		<div class="row">
			<div class="col-12 col-md-6 col-xl-6 col-xxl-5 monitor-list">
				<div class="row monitor-list-filter">
					<div class="col">

					</div>
				</div>
				<div class="row monitor-list-items"><div class="col"></div></div>
			</div>
			<div class="col">
				<?php

				?>
			</div>
		</div>
		<script nonce="<?=\CSP::nonceRandom();?>">
			$(document).ready(function() {
				MonitorsUpdate(wss);
				MonitorsCollapse();
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
				<div class="row monitor-folder mb-1" data-monitor-id="<?=$item['id'];?>">
					<div class="col-9 col-md-8 collapsed monitor-title" data-monitor-id="<?=$item['id'];?>" aria-controls="collapseMonitors_<?=$item['id'];?>">
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
				<div class="collapse" id="collapseMonitors_<?=$item['id'];?>">
					<?php
					$this->print_monitors($item['children'], $level+1);
					?>
				</div>
				<?php
			} else {
				?>
				<div class="row monitor-item mb-1" data-monitor-id="<?=$item['id'];?>">
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
