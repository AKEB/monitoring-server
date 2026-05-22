#!/bin/bash

# Cursor / VS Code: install extension "Run on Save" (achilleshr.runonsave).
# Config is in monitoring/.vscode/settings.json (achilleshr.runonsave).

BASEDIR=$(dirname "$0")
/opt/local/bin/php ${BASEDIR}/generateHelper.php