#!/bin/bash
# for some reason the node image needs OPENSSL_CONF to be configured
docker compose run -e OPENSSL_CONF=/etc/ssl --rm node yarn "$@"
