#!/bin/bash

# For VSCode You need install https://marketplace.visualstudio.com/items?itemName=emeraldwalk.RunOnSave
# And add this code in .vscode/settings.json
# 
# "emeraldwalk.runonsave": {
# 	"commands": [
# 		{
# 			"match": ".yml",
# 			"isAsync": true,
# 			"cmd": "${workspaceFolder}/lang/generateHelper.sh"
# 		}
# 	]
# }

BASEDIR=$(dirname "$0")
/opt/local/bin/php ${BASEDIR}/generateHelper.php