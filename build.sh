#!/bin/bash

# docker buildx build --tag akeb/monitoring-server --platform linux/amd64,linux/arm/v7,linux/arm64/v8 ./

docker build \
  --build-arg SERVER_VERSION=local \
  --tag akeb/monitoring-server:local \
  ${PWD}/
