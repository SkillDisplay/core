#!/usr/bin/env bash

podman run --security-opt label=disable --rm -it -v "$PWD:/usr/src/app" -w /usr/src/app node:18 "$@"
