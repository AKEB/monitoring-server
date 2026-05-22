#!/bin/bash

nohup ./mngr_aggregate.sh 1 &
nohup ./mngr_aggregate_today.sh 1 &
nohup ./mngr_clear.sh 1 &
nohup ./mngr_ssl_notify.sh 1 &
