#!/bin/bash
#
# Run PHPStan analysis against a PrestaShop Docker image.
#
# Usage:
#   bash tests/phpstan.sh <PS_VERSION>
#
# Example:
#   bash tests/phpstan.sh 9.1.x
#   bash tests/phpstan.sh develop
#
PS_VERSION=$1

if [ -z "$PS_VERSION" ]; then
  echo "Usage: bash tests/phpstan.sh <PS_VERSION>"
  echo "Example: bash tests/phpstan.sh 9.1.x"
  exit 1
fi

set -e

echo "Pull PrestaShop files (Tag ${PS_VERSION})"
docker rm -f temp-ps 2>/dev/null || true
docker volume rm -f ps-volume 2>/dev/null || true
docker run -tid --rm -v ps-volume:/var/www/html --name temp-ps prestashop/prestashop:$PS_VERSION

# Wait for initialization
until docker exec temp-ps ls /var/www/html/vendor/autoload.php 2>/dev/null; do
  echo "Waiting for Docker initialization..."
  sleep 5
done

echo "Clear previous module"
docker exec -t temp-ps rm -rf /var/www/html/modules/ps_apiresources

echo "Run PHPStan using phpstan-${PS_VERSION}.neon"
docker run --rm --volumes-from temp-ps \
    -v $PWD:/var/www/html/modules/ps_apiresources \
    -e _PS_ROOT_DIR_=/var/www/html \
    --workdir=/var/www/html/modules/ps_apiresources ghcr.io/phpstan/phpstan:2 \
    analyse \
    --configuration=/var/www/html/modules/ps_apiresources/tests/PHPStan/phpstan-$PS_VERSION.neon

echo "Cleaning up..."
docker rm -f temp-ps 2>/dev/null || true
docker volume rm -f ps-volume 2>/dev/null || true
