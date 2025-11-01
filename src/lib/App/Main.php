<?php

namespace App;

class Main extends \Routing_Parent implements \Routing_Interface {

	private ?int $monitor_id=null;

	public function Run(?int $monitor_id=null) {
		$this->check_auth();
		$this->monitor_id = intval($monitor_id);
		if ($this->monitor_id && !\Sessions::checkPermission(\Monitors::PERMISSION_ACCESS, $this->monitor_id, READ)) {
			e403();
		}

		$this->template = new \Template('Main page');
		?>
		<div class="row">
			<div class="col-12 col-lg-8 col-xl-5 monitor-list" id="monitor-list">
				<div class="row monitor-list-filter">
					<div class="col">
						<div class="row d-flex">
							<div class="col-6 col-md-4">
							</div>
							<div class="col-6 col-md-8 justify-content-end">
								<div class="input-group mb-3">
									<span class="input-group-text" id="basic-addon1"><i class="bi bi-search"></i></span>
									<input type="text" class="form-control" id="monitorSearch" placeholder="<?=\T::Framework_Common_Search();?>" aria-label="Search" aria-describedby="basic-addon1">
								</div>
							</div>
						</div>
						<div class="row">
						</div>
					</div>
				</div>
				<div class="row monitor-list-items"><div class="col">
					<div class="d-flex justify-content-center">
						<div class="spinner-border text-warning" role="status">
							<span class="visually-hidden">Loading...</span>
						</div>
					</div>
				</div></div>
			</div>
			<div class="col-12 col-xl-7 monitor-dashboard" id="monitor-dashboard"></div>
		</div>
		<div class="d-lg-none fixed-bottom border-top p-2">
			<nav class="navbar bg-dark fixed-bottom bg-body-tertiary">
				<div class="container-fluid justify-content-around">
					<button class="btn btn-outline-info<?=($this->monitor_id ? '' : ' active');?>" data-target="monitor-list" type="button"><i class="bi fs-4 bi-list-task"></i><br/>Список</button>
					<button class="btn btn-outline-info<?=($this->monitor_id ? ' active' : '');?>" data-target="monitor-dashboard" type="button"><i class="bi fs-4 bi-speedometer2"></i><br/>Данные</button>
				</div>
			</nav>
		</div>
		<?php

		// $user = \Sessions::currentUser();

		?>
		<script nonce="<?=\CSP::nonceRandom();?>">
			$(document).ready(function() {
				$('footer').addClass('footer-main');
				list_dashboard_mobile(<?=$this->monitor_id ? 'true' : 'false';?>);
				MonitorsUpdate();
				MonitorsCollapse();
				<?php if ($this->monitor_id) { ?>
					MonitorShowData(<?=$this->monitor_id;?>);
				<?php } ?>
			});
		</script>
		<?php
	}

}
