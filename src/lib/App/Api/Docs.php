<?php

namespace App\Api;

/**
 * Swagger UI for /api/docs/ — same OpenAPI document as MCP api_docs.
 */
class Docs extends \Routing_Parent implements \Routing_Interface {

	public function Run() {
		$this->check_auth();
		$this->template = new \Template(\T::Api_Docs_PageTitle());
		$this->print_header();
		$this->print_swagger();
	}

	private function print_header() {
		?>
		<div class="float-start">
			<h2><i class="bi bi-book"></i> <?=\T::Api_Docs_PageTitle();?></h2>
		</div>
		<div class="float-end">
			<a class="btn btn-outline-secondary" href="/settings/api/"><?=\T::Api_Docs_BackToTokens();?></a>
		</div>
		<div class="clearfix"></div>
		<p class="text-body-secondary"><?=\T::Api_Docs_Intro();?></p>
		<?php
	}

	private function print_swagger() {
		$specUrl = '/api/docs/openapi.json';
		?>
		<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5.17.14/swagger-ui.css">
		<style>
			.swagger-ui .topbar { display: none; }
			#swagger-ui { margin: 0 -0.5rem 2rem; background: #fff; border-radius: 0.375rem; }
		</style>
		<div id="swagger-ui"></div>
		<script src="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5.17.14/swagger-ui-bundle.js" crossorigin></script>
		<script src="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5.17.14/swagger-ui-standalone-preset.js" crossorigin></script>
		<script>
		window.onload = function () {
			SwaggerUIBundle({
				url: <?=json_encode($specUrl, JSON_UNESCAPED_SLASHES);?>,
				dom_id: '#swagger-ui',
				deepLinking: true,
				persistAuthorization: true,
				presets: [SwaggerUIBundle.presets.apis, SwaggerUIStandalonePreset],
				layout: 'StandaloneLayout',
				tryItOutEnabled: true
			});
		};
		</script>
		<?php
	}
}
