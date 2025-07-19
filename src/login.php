<?php
require_once("./autoload.php");

error_log(\T::ServerVersion().": ".constant('SERVER_VERSION'));
// echo \T::ServerVersion().": ".constant('SERVER_VERSION')."<br/>";

// \Sessions::session_init();


$template = new \Template();
?>
<div class="container" style="border:1px solid red; height: 100vh;">
	<div class="container text-center" style="">
		<div class="row">
			<div class="col align-self-center">
				<h1>Hello, world!</h1>
			</div>
		</div>
	</div>
</div>
