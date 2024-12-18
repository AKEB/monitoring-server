<?php
require_once("./autoload.php");

error_log(\T::ServerVersion().": ".constant('SERVER_VERSION'));
echo \T::ServerVersion().": ".constant('SERVER_VERSION')."<br/>";

