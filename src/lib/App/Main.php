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
					// vardump(\MonitoringLogs::getMonitorsWithData(5));
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

}
