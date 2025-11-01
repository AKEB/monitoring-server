#!/bin/bash

cd ./

source .env

run_cmd() {
	local base_cmd="$CMD"
	local full_cmd="${base_cmd} ${1}"
	if [ "$other_params" != "" ]; then
		full_cmd="${full_cmd} $other_params"
	fi
	echo "Running command: ${full_cmd}"
	${full_cmd}
}

build() {
	echo "Building..."
	run_cmd "build" 2>/dev/null
}

start() {
	echo "Starting..."
	# rm -rf src/vendor/ src/composer.lock
	if [ "$build" = "true" ]; then
		run_cmd "up -d --build" 2>/dev/null
	else
		run_cmd "up -d" 2>/dev/null
	fi
}

serve() {
	echo "Starting..."
	# Регистрируем обработчики
	trap stop SIGINT SIGTERM
	
	# rm -rf src/vendor/ src/composer.lock
	if [ "$build" = "true" ]; then
		run_cmd "up --build" 2>/dev/null
	else
		run_cmd "up" 2>/dev/null
	fi
}

stop() {
	echo "Stopping..."
	run_cmd "down" 2>/dev/null
}

restart() {
	echo "Restarting..."
	stop
	start
}

status() {
	echo "Status..."
	run_cmd "ps" 2>/dev/null
}

composer_update() {
	echo "Updating composer..."
	run_cmd "exec server composer update" 2>/dev/null
}

composer_install() {
	echo "Installing composer..."
	run_cmd "exec server composer install" 2>/dev/null
}

bash_cmd() {
	echo "Running bash in container..."
	run_cmd "exec server bash" 2>/dev/null
}

mysql_cmd() {
	echo "Running mysql in container..."
	run_cmd "exec mysql mysql -uroot -h127.0.0.1 -P3306 -p${MYSQL_ROOT_PASSWORD}" 2>/dev/null
}

help_cmd() {
	echo "Usage: $0 [--dev|-development|-d] [--build|-b] {start|stop|restart|build|status|serve|composer_update|composer_install|bash|mysql}"; 
	echo "Example: $0 start";
	echo "Example: $0 --build start";
	echo "Example: $0 stop";
	echo "Example: $0 -b serve";
}


development=false
build=false
action=""
other_params=""
while [ -n "$1" ]
do
	if [ "$1" = "--help" ] || [ "$1" = "-h" ]; then
		help_cmd
		exit 0
	fi
	if [ "$1" = "--dev" ] || [ "$1" = "-d" ] || [ "$1" = "--development" ]; then
		development=true
		shift
		continue
	fi
	if [ "$1" = "--build" ] || [ "$1" = "-b" ]; then
		build=true
		shift
		continue
	fi
	if [ "$action" = "" ]; then
		action=$1
		shift
		continue
	fi
	other_params="${other_params} $1"
	shift
done

CMD="docker compose -f docker-compose.yml"

if [ "$development" = "true" ]; then
	CMD="${CMD} -f docker-compose.dev.yml"
fi

case $action in
	start) start;;
	stop) stop;;
	restart) restart;;
	status) status;;
	serve) serve;;
	build) build;;
	composer_update) composer_update;;
	composer_install) composer_install;;
	mysql) mysql_cmd;;
	bash) bash_cmd;;
	help) help_cmd;;
	*) echo "Unknown action: $action";
	help_cmd;
	exit 1;
	;;
esac
