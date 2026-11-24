<?php

/**
 * Shared UI helpers for admin/section pages.
 */
class Ui {

	/**
	 * Section page title that reloads the current page when clicked.
	 */
	public static function print_section_title(string $iconClass, string $title): void {
		$href = htmlspecialchars(strval($_SERVER['REQUEST_URI'] ?? '/'), ENT_QUOTES, 'UTF-8');
		$icon = htmlspecialchars($iconClass, ENT_QUOTES, 'UTF-8');
		?>
		<div class="float-start"><h2><a href="<?=$href;?>" class="section-title-link text-reset text-decoration-none"><i class="<?=$icon;?>"></i> <?=$title;?></a></h2></div>
		<?php
	}

}
